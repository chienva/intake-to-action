<?php

declare(strict_types=1);

namespace App\Domain\Extraction;

interface ExtractionGateway
{
    public function extract(string $text, ExtractionContext $context): GatewayResult|GatewayFailure;
}
