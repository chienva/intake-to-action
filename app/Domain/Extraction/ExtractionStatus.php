<?php

declare(strict_types=1);

namespace App\Domain\Extraction;

enum ExtractionStatus: string
{
    public const STATUS_SET = 'v1';

    case AcceptedForReview = 'accepted_for_review';
    case RejectedByModel = 'rejected_by_model';
    case RejectedByValidation = 'rejected_by_validation';
    case FailedProvider = 'failed_provider';
}
