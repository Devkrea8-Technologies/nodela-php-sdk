<?php

declare(strict_types=1);

namespace Nodelapay\Nodela;

class Config
{
    private const BASE_URL = 'https://api.nodela.co';
    private const API_VERSION = 'v1';

    private string $apiKey;
    private int $timeout;
    private array $headers;

    public function __construct(
        string $apiKey,
        int $timeout = 30,
        array $headers = []
    ) {
        $this->apiKey = $apiKey;
        $this->timeout = $timeout;
        $this->headers = $headers;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function getBaseUrl(): string
    {
        return self::BASE_URL;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function getApiVersion(): string
    {
        return self::API_VERSION;
    }

    public function getHeaders(): array
    {
        return array_merge([
          'Accept' => 'application/json',
          'Content-Type' => 'application/json',
          'User-Agent' => 'NodelaPHP/1.0',
        ], $this->headers);
    }

    public function getFullUrl(string $endpoint): string
    {
        return sprintf(
            '%s/%s/%s',
            rtrim(self::BASE_URL, '/'),
            self::API_VERSION,
            ltrim($endpoint, '/')
        );
    }
}
