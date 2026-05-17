<?php

namespace Veloquent\Core\Domain\Collections\ValueObjects;

use Veloquent\Core\Support\Exceptions\DomainValidationException;

final class ValidationResult
{
    public function __construct(private readonly array $errors) {}

    public function passes(): bool
    {
        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function throwIfFailed(): void
    {
        if (!$this->passes()) {
            throw new DomainValidationException($this->getErrors());
        }
    }
}
