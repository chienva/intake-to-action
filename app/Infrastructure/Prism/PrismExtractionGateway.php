<?php

declare(strict_types=1);

namespace App\Infrastructure\Prism;

use App\Domain\Extraction\ExtractionContext;
use App\Domain\Extraction\ExtractionGateway;
use App\Domain\Extraction\GatewayFailure;
use App\Domain\Extraction\GatewayIntegrity;
use App\Domain\Extraction\GatewayResult;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Facades\Prism;

final readonly class PrismExtractionGateway implements ExtractionGateway
{
    public function __construct(private PrismSchemaFactory $schema) {}

    public function extract(string $text, ExtractionContext $context): GatewayResult|GatewayFailure
    {
        try {
            $config = config('intake.extraction');
            $response = Prism::structured()->using($config['provider'], $config['model'])
                ->withSchema($this->schema->make())
                ->withSystemPrompt(file_get_contents(resource_path('prompts/extraction_p1.md')))
                ->withPrompt($text)
                ->withClientOptions(['timeout' => $config['timeout_seconds']])
                ->withProviderOptions($config['provider_options'])
                ->asStructured();
            $integrity = match (true) {
                $response->finishReason === FinishReason::Refusal => GatewayIntegrity::Refusal,
                trim($response->text) === '' => GatewayIntegrity::TextMissing,
                $response->structured === null || json_decode($response->text, true) !== $response->structured => GatewayIntegrity::StructuredMismatch,
                default => GatewayIntegrity::Ok,
            };

            return new GatewayResult($response->text, $integrity, $response->usage->promptTokens, $response->usage->completionTokens);
        } catch (\Throwable $exception) {
            return new GatewayFailure($exception::class);
        }
    }
}
