<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Http\Middleware;

use Illuminate\Http\Request;
use ImranDevBd\AiOcr\AiOcrManager;

final class AuthorizeStudio
{
    public function handle(Request $request, \Closure $next): mixed
    {
        abort_unless(app(AiOcrManager::class)->authorized($request), 403);

        return $next($request);
    }
}
