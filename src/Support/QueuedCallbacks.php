<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Support;

use ImranDevBd\AiOcr\DTO\ExtractionResult;
use Laravel\SerializableClosure\SerializableClosure;

final class QueuedCallbacks
{
    private ?SerializableClosure $success = null;

    private ?SerializableClosure $failure = null;

    private ?SerializableClosure $always = null;

    public function then(\Closure $callback): void
    {
        $this->success = new SerializableClosure($callback);
    }

    public function catch(\Closure $callback): void
    {
        $this->failure = new SerializableClosure($callback);
    }

    public function finally(\Closure $callback): void
    {
        $this->always = new SerializableClosure($callback);
    }

    public function completed(ExtractionResult $result): void
    {
        try {
            if ($this->success) {
                ($this->success)($result);
            }
        } finally {
            if ($this->always) {
                ($this->always)($result, null);
            }
        }
    }

    public function failed(\Throwable $error): void
    {
        try {
            if ($this->failure) {
                ($this->failure)($error);
            }
        } finally {
            if ($this->always) {
                ($this->always)(null, $error);
            }
        }
    }
}
