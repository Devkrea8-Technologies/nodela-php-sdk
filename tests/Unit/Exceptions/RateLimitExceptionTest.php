<?php

declare(strict_types=1);

namespace Nodelapay\Nodela\Tests\Unit\Exceptions;

use Nodelapay\Nodela\Exceptions\ApiException;
use Nodelapay\Nodela\Exceptions\RateLimitException;
use PHPUnit\Framework\TestCase;

class RateLimitExceptionTest extends TestCase
{
    public function testIsInstanceOfApiException(): void
    {
        $e = new RateLimitException('Too Many Requests', null, 429);
        $this->assertInstanceOf(ApiException::class, $e);
    }

    public function testDefaultStatusCodeIs429(): void
    {
        $e = new RateLimitException('Too Many Requests');
        $this->assertSame(429, $e->getStatusCode());
    }

    public function testGetMessage(): void
    {
        $e = new RateLimitException('Rate limit exceeded');
        $this->assertSame('Rate limit exceeded', $e->getMessage());
    }

    public function testGetRetryAfterWithIntegerValue(): void
    {
        $e = new RateLimitException('Too Many Requests', 60);
        $this->assertSame(60, $e->getRetryAfter());
    }

    public function testGetRetryAfterDefaultsToNull(): void
    {
        $e = new RateLimitException('Too Many Requests');
        $this->assertNull($e->getRetryAfter());
    }

    public function testGetRetryAfterWithZeroSeconds(): void
    {
        $e = new RateLimitException('Too Many Requests', 0);
        $this->assertSame(0, $e->getRetryAfter());
    }

    public function testGetRetryAfterWithLargeValue(): void
    {
        $e = new RateLimitException('Too Many Requests', 3600);
        $this->assertSame(3600, $e->getRetryAfter());
    }

    public function testGetResponseBody(): void
    {
        $body = ['message' => 'Rate limit exceeded', 'retry_after' => 60];
        $e = new RateLimitException('Rate limit exceeded', 60, 429, $body);
        $this->assertSame($body, $e->getResponse());
    }

    public function testCanBeCaughtAsApiException(): void
    {
        $this->expectException(ApiException::class);

        throw new RateLimitException('Too Many Requests');
    }

    public function testCustomStatusCodeIsAccepted(): void
    {
        $e = new RateLimitException('Too Many Requests', null, 503);
        $this->assertSame(503, $e->getStatusCode());
    }
}
