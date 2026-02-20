<?php

declare(strict_types=1);

namespace Nodelapay\Nodela\Http;

class Response
{
    private int $statusCode;
    private array $data;
    private array $headers;

    public function __construct(int $statusCode, array $data, array $headers = [])
    {
        $this->statusCode = $statusCode;
        $this->data = $data;
        $this->headers = $headers;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function toJson(): string
    {
        return json_encode($this->data);
    }
}
