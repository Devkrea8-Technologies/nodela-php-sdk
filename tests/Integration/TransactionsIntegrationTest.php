<?php

declare(strict_types=1);

namespace Nodelapay\Nodela\Tests\Integration;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Nodelapay\Nodela\Config;
use Nodelapay\Nodela\Exceptions\ApiException;
use Nodelapay\Nodela\Exceptions\AuthenticationException;
use Nodelapay\Nodela\Http\Request;
use Nodelapay\Nodela\Resources\Transactions;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Integration tests for the Transactions resource.
 *
 * Tests the full pipeline: Transactions → Request → Response/Exception,
 * using a Guzzle MockHandler to avoid real network calls.
 */
class TransactionsIntegrationTest extends TestCase
{
    private function makeTransactions(MockHandler $mockHandler, string $apiKey = 'test-api-key'): Transactions
    {
        $config = new Config($apiKey);
        $guzzle = new GuzzleClient(['handler' => HandlerStack::create($mockHandler)]);

        $rc = new ReflectionClass(Request::class);
        $request = $rc->newInstanceWithoutConstructor();
        $rc->getProperty('client')->setValue($request, $guzzle);
        $rc->getProperty('config')->setValue($request, $config);

        return new Transactions($request);
    }

    // -------------------------------------------------------------------------
    // list() — happy path
    // -------------------------------------------------------------------------

    public function testListReturnsCollectionOfTransactions(): void
    {
        $data = [
            ['id' => 'txn_001', 'amount' => 5000,  'currency' => 'USD', 'status' => 'completed'],
            ['id' => 'txn_002', 'amount' => 2500,  'currency' => 'NGN', 'status' => 'pending'],
            ['id' => 'txn_003', 'amount' => 10000, 'currency' => 'GBP', 'status' => 'failed'],
        ];
        $mockHandler = new MockHandler([
            new GuzzleResponse(200, [], json_encode($data)),
        ]);

        $response = $this->makeTransactions($mockHandler)->list();

        $this->assertTrue($response->isSuccessful());
        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(3, $response->getData());
        $this->assertSame('txn_001', $response->getData()[0]['id']);
        $this->assertSame('txn_003', $response->getData()[2]['id']);
    }

    public function testListReturnsEmptyCollectionWhenNoTransactions(): void
    {
        $mockHandler = new MockHandler([
            new GuzzleResponse(200, [], json_encode([])),
        ]);

        $response = $this->makeTransactions($mockHandler)->list();

        $this->assertTrue($response->isSuccessful());
        $this->assertEmpty($response->getData());
    }

    public function testListWithPaginationParameters(): void
    {
        $paginatedResponse = [
            'data' => [['id' => 'txn_010']],
            'page' => 2,
            'limit' => 10,
            'total' => 45,
        ];
        $mockHandler = new MockHandler([
            new GuzzleResponse(200, [], json_encode($paginatedResponse)),
        ]);

        $response = $this->makeTransactions($mockHandler)->list(['page' => 2, 'limit' => 10]);

        $this->assertTrue($response->isSuccessful());
        $this->assertSame(2, $response->getData()['page']);
        $this->assertSame(45, $response->getData()['total']);
    }

    public function testListFirstPage(): void
    {
        $mockHandler = new MockHandler([
            new GuzzleResponse(200, [], json_encode([['id' => 'txn_first']])),
        ]);

        $response = $this->makeTransactions($mockHandler)->list(['page' => 1, 'limit' => 5]);

        $this->assertTrue($response->isSuccessful());
        $this->assertSame('txn_first', $response->getData()[0]['id']);
    }

    public function testListResponseCanBeConvertedToArray(): void
    {
        $data = [['id' => 'txn_arr', 'amount' => 1000]];
        $mockHandler = new MockHandler([
            new GuzzleResponse(200, [], json_encode($data)),
        ]);

        $response = $this->makeTransactions($mockHandler)->list();

        $this->assertSame($data, $response->toArray());
    }

    public function testListResponseCanBeConvertedToJson(): void
    {
        $data = [['id' => 'txn_json', 'amount' => 500]];
        $mockHandler = new MockHandler([
            new GuzzleResponse(200, [], json_encode($data)),
        ]);

        $response = $this->makeTransactions($mockHandler)->list();

        $this->assertJson($response->toJson());
        $decoded = json_decode($response->toJson(), true);
        $this->assertSame('txn_json', $decoded[0]['id']);
    }

    public function testListReturnsResponseWithHeaders(): void
    {
        $mockHandler = new MockHandler([
            new GuzzleResponse(200, ['X-Request-Id' => ['req_xyz123']], json_encode([])),
        ]);

        $response = $this->makeTransactions($mockHandler)->list();

        $this->assertArrayHasKey('X-Request-Id', $response->getHeaders());
    }

    // -------------------------------------------------------------------------
    // list() — HTTP error handling
    // -------------------------------------------------------------------------

    public function testListHandles401ThrowsAuthenticationException(): void
    {
        $mockHandler = new MockHandler([
            new GuzzleResponse(401, [], json_encode(['message' => 'Unauthorized'])),
        ]);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Unauthorized');

        $this->makeTransactions($mockHandler)->list();
    }

    public function testListHandles403ThrowsApiException(): void
    {
        $mockHandler = new MockHandler([
            new GuzzleResponse(403, [], json_encode(['message' => 'Forbidden'])),
        ]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Forbidden');

        $this->makeTransactions($mockHandler)->list();
    }

    public function testListHandles500ThrowsApiException(): void
    {
        $mockHandler = new MockHandler([
            new GuzzleResponse(500, [], json_encode(['message' => 'Internal Server Error'])),
        ]);

        $this->expectException(ApiException::class);

        $this->makeTransactions($mockHandler)->list();
    }

    public function testListHandles503ThrowsApiException(): void
    {
        $mockHandler = new MockHandler([
            new GuzzleResponse(503, [], json_encode(['message' => 'Service Unavailable'])),
        ]);

        $this->expectException(ApiException::class);

        $this->makeTransactions($mockHandler)->list();
    }

    public function testListApiExceptionContainsStatusCode(): void
    {
        $mockHandler = new MockHandler([
            new GuzzleResponse(500, [], json_encode(['message' => 'Server Error'])),
        ]);

        try {
            $this->makeTransactions($mockHandler)->list();
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame(500, $e->getStatusCode());
        }
    }

    public function testListAuthExceptionContainsStatusCodeAndBody(): void
    {
        $body = ['message' => 'Invalid API key', 'error' => 'unauthorized'];
        $mockHandler = new MockHandler([
            new GuzzleResponse(401, [], json_encode($body)),
        ]);

        try {
            $this->makeTransactions($mockHandler)->list();
            $this->fail('Expected AuthenticationException');
        } catch (AuthenticationException $e) {
            $this->assertSame(401, $e->getStatusCode());
            $this->assertSame($body, $e->getResponse());
        }
    }
}
