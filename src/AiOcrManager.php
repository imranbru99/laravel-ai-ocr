<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use ImranDevBd\AiOcr\DTO\ExtractionResult;
use ImranDevBd\AiOcr\Facades\AiOcr;
use ImranDevBd\AiOcr\Support\Doctor;
use ImranDevBd\AiOcr\Support\Estimator;
use ImranDevBd\AiOcr\Testing\AiOcrFake;
use ImranDevBd\AiOcr\Types\DocumentType;
use ImranDevBd\AiOcr\Types\TypeRegistry;

class AiOcrManager
{
    private ?\Closure $authorization = null;

    /** @var array<string, \Closure> */
    private array $extensions = [];

    public function __construct(private readonly Pipeline\Pipeline $pipeline, private readonly TypeRegistry $types) {}

    public function file(mixed $input): ExtractionRequest
    {
        return new ExtractionRequest($this, $input);
    }

    /** @param list<mixed> $inputs */
    public function files(array $inputs): BatchRequest
    {
        return new BatchRequest($this, $inputs);
    }

    public function url(string $url): ExtractionRequest
    {
        return $this->file($url);
    }

    public function auth(\Closure $callback): void
    {
        $this->authorization = $callback;
    }

    public function authorized(Request $request): bool
    {
        if ($this->authorization) {
            return (bool) ($this->authorization)($request);
        } if (app()->environment('local')) {
            return true;
        }

        $user = $request->user();
        if ($user === null) {
            return false;
        }
        $emails = array_filter(array_map('trim', explode(',', (string) config('ai-ocr.studio.emails', ''))));
        if (in_array(data_get($user, 'email'), $emails, true)) {
            return true;
        }
        $roles = array_filter(array_map('trim', explode(',', (string) config('ai-ocr.studio.roles', ''))));
        if (method_exists($user, 'hasRole')) {
            foreach ($roles as $role) {
                if ($user->hasRole($role)) {
                    return true;
                }
            }
        }

        return Gate::allows('viewAiOcr');
    }

    /** @return array<string,mixed>|null */
    public function progress(string $id): ?array
    {
        return app(Support\JobTracker::class)->get($id);
    }

    public function registerType(DocumentType|string $type): void
    {
        $this->types->register($type);
    }

    public function extend(string $name, \Closure $resolver): void
    {
        $this->extensions[$name] = $resolver;
    }

    public function driver(string $name): mixed
    {
        return isset($this->extensions[$name]) ? ($this->extensions[$name])(app()) : throw new \InvalidArgumentException('Unknown OCR driver: '.$name);
    }

    /** @return array<string, mixed> */
    public function doctor(): array
    {
        return app(Doctor::class)->report();
    }

    /** @return array<string, mixed> */
    public function estimate(mixed $input): array
    {
        return app(Estimator::class)->estimate($input);
    }

    public function fake(mixed $responses = []): AiOcrFake
    {
        $fake = new AiOcrFake($responses);
        AiOcr::swap($fake);

        return $fake;
    }

    /** @param array<string,mixed> $options */
    public function extract(mixed $input, array $options = []): ExtractionResult
    {
        return $this->pipeline->extract($input, $options);
    }
}
