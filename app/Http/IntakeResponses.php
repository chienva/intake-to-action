<?php

declare(strict_types=1);

namespace App\Http;

use App\Domain\Extraction\StorageFailure;
use App\Infrastructure\Persistence\RunStateReader;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class IntakeResponses
{
    public static function requestId(Request $request): string
    {
        if (! $request->attributes->has('intake_request_id')) {
            $incoming = $request->header('X-Request-Id');
            $request->attributes->set('intake_request_id', is_string($incoming) && Str::isUuid($incoming) ? $incoming : (string) Str::uuid());
        }

        return $request->attributes->get('intake_request_id');
    }

    public static function error(Request $request, int $status, string $error, array $extra = [], array $headers = []): Response
    {
        $id = self::requestId($request);

        return response()->json(['request_id' => $id, 'error' => $error, ...$extra], $status, ['X-Request-Id' => $id, ...$headers]);
    }

    public static function exception(\Throwable $exception, Request $request): Response
    {
        $id = $request->attributes->get('pending_run_id');
        if ($id === null) {
            return self::error($request, 500, 'server_error');
        }
        try {
            $row = app(RunStateReader::class)->byId($id);
        } catch (\Throwable) {
            return self::error($request, 500, 'server_error', ['run_state' => 'unknown']);
        }
        if ($row !== null) {
            return self::error($request, 500, 'server_error', ['run_id' => $id]);
        }

        return $exception instanceof StorageFailure
            ? self::error($request, 503, 'storage_unavailable')
            : self::error($request, 500, 'server_error');
    }
}
