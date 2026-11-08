<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\IntakeResponses;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

final class AuthorizeExtraction
{
    public function handle(Request $request, Closure $next): Response
    {
        $plaintext = $request->bearerToken();
        $token = is_string($plaintext) ? PersonalAccessToken::findToken($plaintext) : null;
        if ($token === null || ($token->expires_at !== null && $token->expires_at->isPast()) || $token->tokenable === null) {
            return IntakeResponses::error($request, 401, 'unauthenticated');
        }
        if (! $token->can('extractions:create')) {
            return IntakeResponses::error($request, 403, 'forbidden');
        }
        $key = 'intake:token:'.$token->getKey();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return IntakeResponses::error($request, 429, 'too_many_requests', [], ['Retry-After' => (string) RateLimiter::availableIn($key)]);
        }
        RateLimiter::hit($key, 60);
        $request->setUserResolver(fn () => $token->tokenable->withAccessToken($token));
        $token->forceFill(['last_used_at' => now()])->save();

        return $next($request);
    }
}
