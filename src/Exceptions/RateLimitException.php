<?php

declare(strict_types=1);

namespace Nodelapay\Nodela\Exceptions;

class RateLimitException extends ApiException
{
    private ?int $retryAfter;

    public function __construct(
        string $message,
        ?int $retryAfter = null,
        int $statusCode = 429,
        array $response = []
    ) {
        parent::__construct($message, $statusCode, $response);
        $this->retryAfter = $retryAfter;
    }

    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
