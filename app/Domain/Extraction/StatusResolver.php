<?php

declare(strict_types=1);

namespace App\Domain\Extraction;

final readonly class StatusResolver
{
    public function __construct(
        private BusinessRules $business,
        private ContainerCheck $container = new ContainerCheck,
        private ShapeValidator $shape = new ShapeValidator,
        private RejectInvariants $reject = new RejectInvariants,
        private ExtractInvariants $extract = new ExtractInvariants,
    ) {}

    public function resolve(GatewayResult|GatewayFailure $gateway): ValidationResult
    {
        if ($gateway instanceof GatewayFailure) {
            return new ValidationResult(ExtractionStatus::FailedProvider, []);
        }
        $container = $this->container->validate($gateway->rawJson, $gateway->integrity);
        if ($container->errors !== []) {
            return new ValidationResult(ExtractionStatus::RejectedByValidation, $container->errors);
        }
        $payload = $container->payload ?? [];
        $errors = $this->shape->validate($payload);
        if ($errors !== []) {
            return new ValidationResult(ExtractionStatus::RejectedByValidation, $errors);
        }
        if (($payload['outcome'] ?? null) === 'rejected') {
            $errors = $this->reject->validate($payload);

            return new ValidationResult($errors === [] ? ExtractionStatus::RejectedByModel : ExtractionStatus::RejectedByValidation, $errors);
        }
        if (($payload['outcome'] ?? null) === 'extracted') {
            $errors = [...$this->extract->validate($payload), ...$this->business->validate($payload)];
            if ($errors === []) {
                return new ValidationResult(ExtractionStatus::AcceptedForReview, [], $payload);
            }

            return new ValidationResult(ExtractionStatus::RejectedByValidation, $errors);
        }

        return new ValidationResult(ExtractionStatus::RejectedByValidation, []);
    }
}
