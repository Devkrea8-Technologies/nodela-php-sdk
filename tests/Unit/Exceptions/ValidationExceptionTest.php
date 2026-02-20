<?php

declare(strict_types=1);

namespace Nodelapay\Nodela\Tests\Unit\Exceptions;

use Nodelapay\Nodela\Exceptions\ApiException;
use Nodelapay\Nodela\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

class ValidationExceptionTest extends TestCase
{
    public function testIsInstanceOfApiException(): void
    {
        $e = new ValidationException('Validation failed');
        $this->assertInstanceOf(ApiException::class, $e);
    }

    public function testDefaultStatusCodeIs422(): void
    {
        $e = new ValidationException('Validation failed');
        $this->assertSame(422, $e->getStatusCode());
    }

    public function testGetMessage(): void
    {
        $e = new ValidationException('The given data was invalid');
        $this->assertSame('The given data was invalid', $e->getMessage());
    }

    public function testGetErrors(): void
    {
        $errors = [
            'amount' => ['The amount field is required.'],
            'currency' => ['The currency must be a valid ISO 4217 code.'],
        ];
        $e = new ValidationException('Validation failed', $errors);
        $this->assertSame($errors, $e->getErrors());
    }

    public function testGetErrorsDefaultsToEmptyArray(): void
    {
        $e = new ValidationException('Validation failed');
        $this->assertSame([], $e->getErrors());
    }

    public function testGetResponseBody(): void
    {
        $body = [
            'message' => 'Validation failed',
            'errors' => ['field' => ['error']],
        ];
        $e = new ValidationException('Validation failed', [], 422, $body);
        $this->assertSame($body, $e->getResponse());
    }

    public function testCanUseCustomStatusCode(): void
    {
        $e = new ValidationException('Error', [], 400);
        $this->assertSame(400, $e->getStatusCode());
    }

    public function testCanBeCaughtAsApiException(): void
    {
        $this->expectException(ApiException::class);

        throw new ValidationException('Validation failed');
    }

    public function testErrorsCanContainMultipleMessagesPerField(): void
    {
        $errors = [
            'amount' => [
                'The amount is required.',
                'The amount must be a number.',
                'The amount must be greater than 0.',
            ],
        ];
        $e = new ValidationException('Multiple errors', $errors);
        $this->assertCount(3, $e->getErrors()['amount']);
    }
}
