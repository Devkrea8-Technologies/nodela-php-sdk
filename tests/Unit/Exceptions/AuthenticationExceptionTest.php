<?php

declare(strict_types=1);

namespace Nodelapay\Nodela\Tests\Unit\Exceptions;

use Nodelapay\Nodela\Exceptions\ApiException;
use Nodelapay\Nodela\Exceptions\AuthenticationException;
use PHPUnit\Framework\TestCase;

class AuthenticationExceptionTest extends TestCase
{
    public function testIsInstanceOfApiException(): void
    {
        $e = new AuthenticationException('Unauthorized', 401);
        $this->assertInstanceOf(ApiException::class, $e);
    }

    public function testGetMessage(): void
    {
        $e = new AuthenticationException('Invalid API key', 401);
        $this->assertSame('Invalid API key', $e->getMessage());
    }

    public function testGetStatusCode(): void
    {
        $e = new AuthenticationException('Unauthorized', 401);
        $this->assertSame(401, $e->getStatusCode());
    }

    public function testGetResponseBody(): void
    {
        $body = ['message' => 'Unauthorized', 'error' => 'invalid_token'];
        $e = new AuthenticationException('Unauthorized', 401, $body);
        $this->assertSame($body, $e->getResponse());
    }

    public function testGetResponseDefaultsToEmpty(): void
    {
        $e = new AuthenticationException('Unauthorized', 401);
        $this->assertSame([], $e->getResponse());
    }

    public function testCanBeCaughtAsApiException(): void
    {
        $this->expectException(ApiException::class);

        throw new AuthenticationException('Unauthorized', 401);
    }

    public function testDefaultStatusCodeIsZeroWhenNotProvided(): void
    {
        $e = new AuthenticationException('Unauthorized');
        $this->assertSame(0, $e->getStatusCode());
    }
}
