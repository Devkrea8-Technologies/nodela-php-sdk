<?php

declare(strict_types=1);

namespace Nodelapay\Nodela\Tests\Integration;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use InvalidArgumentException;
use Nodelapay\Nodela\Config;
use Nodelapay\Nodela\Exceptions\ApiException;
use Nodelapay\Nodela\Exceptions\AuthenticationException;
use Nodelapay\Nodela\Exceptions\ValidationException;
use Nodelapay\Nodela\Http\Request;
use Nodelapay\Nodela\Resources\Invoices;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Integration tests for the Invoices resource.
 *
 * These tests exercise the full pipeline from Invoices → Request → Response (or Exception),
 * using a Guzzle MockHandler to simulate the HTTP layer without real network calls.
 *
 * Constructor injection is bypassed via reflection to work around the
 * Config::getHedaers() typo bug. Once that bug is fixed, a cleaner
 * helper can be used.
 */
class InvoicesIntegrationTest extends TestCase
{
    private function makeInvoices(MockHandler $mockHandler, string $apiKey = 'test-api-key'): Invoices
    {
        $config = new Config($apiKey);
        $guzzle = new GuzzleClient(['handler' => HandlerStack::create($mockHandler)]);

        $rc = new ReflectionClass(Request::class);
        $request = $rc->newInstanceWithoutConstructor();
        $rc->getProperty('client')->setValue($request, $guzzle);
        $rc->getProperty('config')->setValue($request, $config);

        return new Invoices($request);
    }

    // -------------------------------------------------------------------------
    // create() — happy path
    // -------------------------------------------------------------------------

    public function testCreateInvoiceReturnsSuccessfulResponse(): void
    {
        $responseBody = [
            'id' => 'inv_test_001',
            'amount' => 5000,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_url' => 'https://pay.nodela.co/inv_test_001',
            'created_at' => '2024-06-01T00:00:00Z',
        ];
        $mockHandler = new MockHandler([
            new GuzzleResponse(201, ['Content-Type' => ['application/json']], json_encode($responseBody)),
        ]);

        $response = $this->makeInvoices($mockHandler)->create([
            'amount' => 5000,
            'currency' => 'USD',
            'success_url' => 'https://merchant.example.com/success',
        ]);

        $this->assertTrue($response->isSuccessful());
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('inv_test_001', $response->getData()['id']);
        $this->assertSame('pending', $response->getData()['status']);
        $this->assertArrayHasKey('payment_url', $response->getData());
    }

    public function testCreateInvoiceWithNgnCurrency(): void
    {
        $mockHandler = new MockHandler([
            new GuzzleResponse(201, [], json_encode(['id' => 'inv_ngn_001', 'currency' => 'NGN'])),
        ]);

        $response = $this->makeInvoices($mockHandler)->create([
            'amount' => 50000,
            'currency' => 'ngn', // lowercase — should be normalized
        ]);

        $this->assertTrue($response->isSuccessful());
        $this->assertSame('inv_ngn_001', $response->getData()['id']);
    }

    public function testCreateInvoiceWithAllOptionalFields(): void
    {
        $mockHandler = new MockHandler([
            new GuzzleResponse(201, [], json_encode(['id' => 'inv_full'])),
        ]);

        $response = $this->makeInvoices($mockHandler)->create([
            'amount' => 10000,
            'currency' => 'GBP',
            'success_url' => 'https://shop.example.com/success',
            'cancel_url' => 'https://shop.example.com/cancel',
            'webhook_url' => 'https://shop.example.com/webhook/nodela',
            'reference' => 'ORDER-001',
            'customer' => ['name' => 'Alice Wonderland', 'email' => 'alice@example.com'],
            'title' => 'Order #001 — 2 items',
            'description' => 'Books and stationery',
        ]);

        $this->assertTrue($response->isSuccessful());
    }

    public function testCreateResponseDataCanBeConvertedToArray(): void
    {
        $body = ['id' => 'inv_arr', 'amount' => 200];
        $mockHandler = new MockHandler([
            new GuzzleResponse(201, [], json_encode($body)),
        ]);

        $response = $this->makeInvoices($mockHandler)->create(['amount' => 200, 'currency' => 'USD']);

        $this->assertSame($body, $response->toArray());
    }

    public function testCreateResponseDataCanBeConvertedToJson(): void
    {
        $body = ['id' => 'inv_json', 'amount' => 300];
        $mockHandler = new MockHandler([
            new GuzzleResponse(201, [], json_encode($body)),
        ]);

        $response = $this->makeInvoices($mockHandler)->create(['amount' => 300, 'currency' => 'EUR']);

        $this->assertJson($response->toJson());
        $decoded = json_decode($response->toJson(), true);
        $this->assertSame('inv_json', $decoded['id']);
    }

    // -------------------------------------------------------------------------
    // create() — validation errors before HTTP call
    // -------------------------------------------------------------------------

    public function testCreateWithUnsupportedCurrencyThrowsBeforeAnyHttpRequest(): void
    {
        // MockHandler has no responses queued — it should never be called.
        $mockHandler = new MockHandler([]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported currency: "DOGECOIN"');

        $this->makeInvoices($mockHandler)->create([
            'amount' => 1000,
            'currency' => 'DOGECOIN',
        ]);
    }

    // -------------------------------------------------------------------------
    // create() — HTTP error handling
    // -------------------------------------------------------------------------

    public function testCreateHandles401ThrowsAuthenticationException(): void
    {
        $mockHandler = new MockHandler([
            new GuzzleResponse(401, [], json_encode([
                'message' => 'Invalid API key provided',
                'error' => 'unauthorized',
            ])),
        ]);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid API key provided');

        $this->makeInvoices($mockHandler)->create(['amount' => 1000, 'currency' => 'USD']);
    }

    public function testCreateHandles422ThrowsValidationException(): void
    {
        $mockHandler = new MockHandler([
            new GuzzleResponse(422, [], json_encode([
                'message' => 'The given data was invalid',
                'errors' => ['amount' => ['The amount must be a positive number.']],
            ])),
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The given data was invalid');

        $this->makeInvoices($mockHandler)->create(['amount' => -1, 'currency' => 'USD']);
    }

    public function testCreate422ExceptionContainsFieldErrors(): void
    {
        $errors = [
            'amount' => ['The amount is required.'],
            'currency' => ['Unsupported currency.'],
        ];
        $mockHandler = new MockHandler([
            new GuzzleResponse(422, [], json_encode(['message' => 'Validation failed', 'errors' => $errors])),
        ]);

        try {
            $this->makeInvoices($mockHandler)->create(['amount' => 0, 'currency' => 'USD']);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertArrayHasKey('amount', $e->getErrors());
        }
    }

    public function testCreateHandles500ThrowsApiException(): void
    {
        $mockHandler = new MockHandler([
            new GuzzleResponse(500, [], json_encode(['message' => 'Internal Server Error'])),
        ]);

        $this->expectException(ApiException::class);

        $this->makeInvoices($mockHandler)->create(['amount' => 1000, 'currency' => 'USD']);
    }

    // -------------------------------------------------------------------------
    // verify() — happy path
    // -------------------------------------------------------------------------

    public function testVerifyPaidInvoiceReturnsSuccessfulResponse(): void
    {
        $body = [
            'id' => 'inv_paid_001',
            'status' => 'paid',
            'amount' => 5000,
            'currency' => 'USD',
            'paid_at' => '2024-06-01T12:00:00Z',
        ];
        $mockHandler = new MockHandler([
            new GuzzleResponse(200, [], json_encode($body)),
        ]);

        $response = $this->makeInvoices($mockHandler)->verify('inv_paid_001');

        $this->assertTrue($response->isSuccessful());
        $this->assertSame('paid', $response->getData()['status']);
        $this->assertSame('inv_paid_001', $response->getData()['id']);
    }

    public function testVerifyPendingInvoice(): void
    {
        $mockHandler = new MockHandler([
            new GuzzleResponse(200, [], json_encode(['id' => 'inv_pending', 'status' => 'pending'])),
        ]);

        $response = $this->makeInvoices($mockHandler)->verify('inv_pending');

        $this->assertTrue($response->isSuccessful());
        $this->assertSame('pending', $response->getData()['status']);
    }

    // -------------------------------------------------------------------------
    // verify() — HTTP error handling
    // -------------------------------------------------------------------------

    public function testVerifyNonExistentInvoiceThrowsApiException(): void
    {
        $mockHandler = new MockHandler([
            new GuzzleResponse(404, [], json_encode([
                'message' => 'Invoice not found',
                'error' => 'not_found',
            ])),
        ]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Invoice not found');

        $this->makeInvoices($mockHandler)->verify('inv_does_not_exist');
    }

    public function testVerifyHandles401ThrowsAuthenticationException(): void
    {
        $mockHandler = new MockHandler([
            new GuzzleResponse(401, [], json_encode(['message' => 'Unauthorized'])),
        ]);

        $this->expectException(AuthenticationException::class);

        $this->makeInvoices($mockHandler)->verify('inv_abc');
    }
}
