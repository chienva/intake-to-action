<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\IntakeResponses;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class IntakeRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = IntakeResponses::requestId($request);

        $response = $next($request);
        if (! $response->headers->has('X-Request-Id')) {
            $response->headers->set('X-Request-Id', $id);
        }

        return $response;
    }
}
