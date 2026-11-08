<?php

declare(strict_types=1);

namespace App\Domain\Extraction;

enum GatewayIntegrity: string
{
    case Ok = 'ok';
    case TextMissing = 'text_missing';
    case Refusal = 'refusal';
    case StructuredMismatch = 'structured_mismatch';
}
