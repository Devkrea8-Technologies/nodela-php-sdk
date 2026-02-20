<?php

declare(strict_types=1);

namespace Nodelapay\Nodela\Exceptions;

class ValidationException extends ApiException
{
    private array $errors;

    public function __construct(
        string $message,
        array $errors = [],
        int $statusCode = 422,
        array $response = []
    ) {
        parent::__construct($message, $statusCode, $response);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
