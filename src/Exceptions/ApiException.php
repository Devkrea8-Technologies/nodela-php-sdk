<?php

declare(strict_types=1);

namespace Nodelapay\Nodela\Exceptions;

use Exception;

class ApiException extends Exception
{
    protected array $response;
    protected int $statusCode;

    public function __construct(
        string $message,
        int $statusCode = 0,
        array $response = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
        $this->response = $response;
    }

    public function getResponse(): array
    {
        return $this->response;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
