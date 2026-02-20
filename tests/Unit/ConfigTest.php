<?php

declare(strict_types=1);

namespace Nodelapay\Nodela\Tests\Unit;

use Nodelapay\Nodela\Config;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Config class.
 *
 * NOTE: testGetHeaders* tests document the intended public API `getHeaders()`.
 * The current implementation has a typo: `getHedaers()` (missing 'a').
 * These tests WILL FAIL until the typo in Config.php is corrected.
 * This is the same method that Request.php calls — meaning every HTTP request
 * will throw a fatal error until the typo is fixed.
 */
class ConfigTest extends TestCase
{
    private Config $config;

    protected function setUp(): void
    {
        $this->config = new Config('test-api-key-123');
    }

    public function testGetApiKeyReturnsProvidedKey(): void
    {
        $this->assertSame('test-api-key-123', $this->config->getApiKey());
    }

    public function testGetBaseUrlReturnsCorrectUrl(): void
    {
        $this->assertSame('https://api.nodela.co', $this->config->getBaseUrl());
    }

    public function testGetApiVersionReturnsV1(): void
    {
        $this->assertSame('v1', $this->config->getApiVersion());
    }

    public function testGetTimeoutReturnsDefaultOf30(): void
    {
        $this->assertSame(30, $this->config->getTimeout());
    }

    public function testGetTimeoutReturnsCustomValue(): void
    {
        $config = new Config('key', 60);
        $this->assertSame(60, $config->getTimeout());
    }

    public function testGetTimeoutReturnsZeroWhenSetToZero(): void
    {
        $config = new Config('key', 0);
        $this->assertSame(0, $config->getTimeout());
    }

    public function testGetHeadersIncludesDefaultAcceptHeader(): void
    {
        $headers = $this->config->getHeaders();
        $this->assertArrayHasKey('Accept', $headers);
        $this->assertSame('application/json', $headers['Accept']);
    }

    public function testGetHeadersIncludesDefaultContentTypeHeader(): void
    {
        $headers = $this->config->getHeaders();
        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertSame('application/json', $headers['Content-Type']);
    }

    public function testGetHeadersIncludesUserAgentHeader(): void
    {
        $headers = $this->config->getHeaders();
        $this->assertArrayHasKey('User-Agent', $headers);
        $this->assertSame('NodelaPHP/1.0', $headers['User-Agent']);
    }

    public function testGetHeadersMergesCustomHeaders(): void
    {
        $config = new Config('key', 30, ['X-Custom-Header' => 'custom-value']);
        $headers = $config->getHeaders();

        $this->assertArrayHasKey('X-Custom-Header', $headers);
        $this->assertSame('custom-value', $headers['X-Custom-Header']);
        $this->assertArrayHasKey('Accept', $headers);
    }

    public function testGetHeadersWithNoCustomHeadersReturnsOnlyDefaults(): void
    {
        $headers = $this->config->getHeaders();
        $this->assertCount(3, $headers);
    }

    public function testCustomHeadersCanOverrideDefaults(): void
    {
        $config = new Config('key', 30, ['Accept' => 'text/html']);
        $headers = $config->getHeaders();
        $this->assertSame('text/html', $headers['Accept']);
    }

    public function testGetFullUrlBuildsCorrectUrl(): void
    {
        $url = $this->config->getFullUrl('invoices');
        $this->assertSame('https://api.nodela.co/v1/invoices', $url);
    }

    public function testGetFullUrlStripsLeadingSlashFromEndpoint(): void
    {
        $url = $this->config->getFullUrl('/invoices');
        $this->assertSame('https://api.nodela.co/v1/invoices', $url);
    }

    public function testGetFullUrlHandlesNestedPath(): void
    {
        $url = $this->config->getFullUrl('invoices/inv_123/verify');
        $this->assertSame('https://api.nodela.co/v1/invoices/inv_123/verify', $url);
    }

    public function testGetFullUrlHandlesNestedPathWithLeadingSlash(): void
    {
        $url = $this->config->getFullUrl('/invoices/inv_123/verify');
        $this->assertSame('https://api.nodela.co/v1/invoices/inv_123/verify', $url);
    }

    public function testGetFullUrlHandlesTransactionsEndpoint(): void
    {
        $url = $this->config->getFullUrl('transactions');
        $this->assertSame('https://api.nodela.co/v1/transactions', $url);
    }
}
