<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Extraction\ExtractionContext;
use App\Domain\Extraction\ExtractionGateway;
use App\Domain\Extraction\ExtractionStatus;
use App\Domain\Extraction\GatewayResult;
use App\Domain\Extraction\OrderExtractionContract;
use App\Domain\Extraction\StatusResolver;
use App\Http\IntakeResponses;
use App\Infrastructure\Persistence\RunRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class ExtractionController
{
    public function __invoke(Request $request, ExtractionGateway $gateway, StatusResolver $resolver, RunRepository $runs): Response
    {
        if (strtolower(trim(explode(';', $request->header('Content-Type', ''))[0])) !== 'application/json') {
            return IntakeResponses::error($request, 415, 'unsupported_media_type');
        }
        $raw = $request->getContent();
        if (strlen($raw) > 65536) {
            return IntakeResponses::error($request, 413, 'payload_too_large');
        }
        try {
            $input = json_decode($raw, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return IntakeResponses::error($request, 400, 'malformed_json');
        }
        $fields = [];
        if (! $input instanceof \stdClass) {
            $fields[] = ['name' => '$', 'rule' => 'object'];
        } elseif (array_diff(array_keys(get_object_vars($input)), ['document_text']) !== []) {
            $fields[] = ['name' => '$', 'rule' => 'prohibited_key'];
        } elseif (! property_exists($input, 'document_text')) {
            $fields[] = ['name' => 'document_text', 'rule' => 'required'];
        } elseif (! is_string($input->document_text)) {
            $fields[] = ['name' => 'document_text', 'rule' => 'string'];
        } elseif (mb_strlen($input->document_text) === 0) {
            $fields[] = ['name' => 'document_text', 'rule' => 'min'];
        } elseif (mb_strlen($input->document_text) > 20000) {
            $fields[] = ['name' => 'document_text', 'rule' => 'max'];
        }
        if ($fields !== []) {
            return IntakeResponses::error($request, 422, 'validation_failed', ['fields' => $fields]);
        }
        $text = $input->document_text;
        $requestId = IntakeResponses::requestId($request);
        $userId = $request->user()?->getKey();
        $started = hrtime(true);
        $result = $gateway->extract($text, new ExtractionContext($requestId, $userId));
        $validation = $resolver->resolve($result);
        $id = (string) Str::ulid();
        $request->attributes->set('pending_run_id', $id);
        $failed = $validation->status === ExtractionStatus::FailedProvider;
        $body = $failed ? ['request_id' => $requestId, 'error' => 'provider_unavailable', 'run_id' => $id] : [
            'run_id' => $id, 'request_id' => $requestId, 'status' => $validation->status->value,
            'status_set' => ExtractionStatus::STATUS_SET, 'order' => $validation->order, 'errors' => $validation->errors,
            'schema_version' => OrderExtractionContract::VERSION, 'prompt_version' => 'p1',
        ];
        $attributes = [
            'id' => $id, 'request_id' => $requestId, 'user_id' => $userId,
            'input_sha256' => hash('sha256', $text), 'input_length' => mb_strlen($text),
            'status' => $validation->status->value, 'status_set' => ExtractionStatus::STATUS_SET,
            'errors' => json_encode($validation->errors, JSON_THROW_ON_ERROR),
            'order' => $validation->order === null ? null : json_encode($validation->order, JSON_THROW_ON_ERROR),
            'schema_version' => OrderExtractionContract::VERSION, 'prompt_version' => 'p1',
            'gateway' => config('intake.extraction.gateway'), 'provider' => config('intake.extraction.provider'), 'model' => config('intake.extraction.model'),
            'duration_ms' => (int) ((hrtime(true) - $started) / 1000000),
            'input_tokens' => $result instanceof GatewayResult ? $result->inputTokens : null,
            'output_tokens' => $result instanceof GatewayResult ? $result->outputTokens : null,
            'created_at' => now(),
        ];
        $response = response(json_encode($body, JSON_THROW_ON_ERROR), $failed ? 502 : 201, ['Content-Type' => 'application/json', 'X-Request-Id' => $requestId]);
        $runs->insert($attributes);

        return $response;
    }
}
