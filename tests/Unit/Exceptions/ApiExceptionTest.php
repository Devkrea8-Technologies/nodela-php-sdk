<?php

declare(strict_types=1);

namespace Nodelapay\Nodela\Tests\Unit\Exceptions;

use Exception;
use Nodelapay\Nodela\Exceptions\ApiException;
use PHPUnit\Framework\TestCase;

class ApiExceptionTest extends TestCase
{
    public function testGetMessage(): void
    {
        $e = new ApiException('Something went wrong', 500);
        $this->assertSame('Something went wrong', $e->getMessage());
    }

    public function testGetStatusCode(): void
    {
        $e = new ApiException('Error', 404);
        $this->assertSame(404, $e->getStatusCode());
    }

    public function testGetCodeMatchesStatusCode(): void
    {
        $e = new ApiException('Error', 400);
        $this->assertSame(400, $e->getCode());
    }

    public function testGetResponseBody(): void
    {
        $body = ['message' => 'Not found', 'code' => 'NOT_FOUND'];
        $e = new ApiException('Not found', 404, $body);
        $this->assertSame($body, $e->getResponse());
    }

    public function testGetResponseDefaultsToEmptyArray(): void
    {
        $e = new ApiException('Error', 500);
        $this->assertSame([], $e->getResponse());
    }

    public function testDefaultStatusCodeIsZero(): void
    {
        $e = new ApiException('Error');
        $this->assertSame(0, $e->getStatusCode());
    }

    public function testPreviousExceptionIsChained(): void
    {
        $previous = new Exception('Original network error');
        $e = new ApiException('Wrapped error', 500, [], $previous);
        $this->assertSame($previous, $e->getPrevious());
    }

    public function testNoPreviousExceptionByDefault(): void
    {
        $e = new ApiException('Error', 500);
        $this->assertNull($e->getPrevious());
    }

    public function testIsInstanceOfException(): void
    {
        $e = new ApiException('Error', 500);
        $this->assertInstanceOf(Exception::class, $e);
    }

    public function testIsThrowable(): void
    {
        $e = new ApiException('Error', 500);
        $this->assertInstanceOf(\Throwable::class, $e);
    }

    public function testResponseBodyCanContainNestedData(): void
    {
        $body = [
            'message' => 'Validation failed',
            'errors' => ['field' => ['error message']],
            'meta' => ['request_id' => 'req_123'],
        ];
        $e = new ApiException('Validation failed', 422, $body);
        $this->assertSame($body, $e->getResponse());
        $this->assertArrayHasKey('errors', $e->getResponse());
    }
}
