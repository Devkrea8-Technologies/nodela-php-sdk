<?php

declare(strict_types=1);

namespace Nodelapay\Nodela\Tests\Unit\Http;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Nodelapay\Nodela\Config;
use Nodelapay\Nodela\Exceptions\ApiException;
use Nodelapay\Nodela\Exceptions\AuthenticationException;
use Nodelapay\Nodela\Exceptions\RateLimitException;
use Nodelapay\Nodela\Exceptions\ValidationException;
use Nodelapay\Nodela\Http\Request;
use Nodelapay\Nodela\Http\Response;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for the HTTP Request class.
 *
 * Because Request instantiates GuzzleClient internally (no DI), we use
 * ReflectionClass::newInstanceWithoutConstructor() to bypass the constructor
 * and inject a Guzzle MockHandler directly into the private $client property.
 */
class RequestTest extends TestCase
{
    private Config $config;

    protected function setUp(): void
    {
        $this->config = new Config('test-api-key-123');
    }

    /**
     * Create a Request instance with a Guzzle MockHandler injected via reflection,
     * bypassing the buggy constructor entirely.
     */
    private function makeRequest(MockHandler $mockHandler): Request
    {
        $guzzleClient = new GuzzleClient([
            'handler' => HandlerStack::create($mockHandler),
        ]);

        $reflection = new ReflectionClass(Request::class);
        /** @var Request $request */
        $request = $reflection->newInstanceWithoutConstructor();

        $reflection->getProperty('client')->setValue($request, $guzzleClient);
        $reflection->getProperty('config')->setValue($request, $this->config);

        return $request;
    }

    // -------------------------------------------------------------------------
    // Successful responses
    // -------------------------------------------------------------------------

    public function testGetReturnsResponse(): void
    {
        $mockHandler = new MockHandler([
            new GuzzleResponse(200, [], json_encode(['data' => []])),
        ]);

        $response = $this->makeRequest($mockHandler)->get('/transactions');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->isSuccessful());
    }

    public function testGetDecodesJsonBody(): void
    {
        $body = ['id' => 'inv_123', 'status' => 'pending'];
        $mockHandler = new MockHandler([
            new GuzzleResponse(200, [], json_encode($body)),
        ]);

        $response = $this->makeRequest($mockHandler)->get('/invoices/inv_123');

        $this->assertSame($body, $response->getData());
    }

    public function testPostReturnsCreatedResponse(): void
    {
        $body = ['id' => 'inv_new', 'status' => 'pending', 'payment_url' => 'https://pay.nodela.co/inv_new'];
        $mockHandler = new MockHandler([
            new GuzzleResponse(201, [], json_encode($body)),
        ]);

        $response = $this->makeRequest($mockHandler)->post('/invoices', ['amount' => 5000, 'currency' => 'USD']);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('inv_new', $response->getData()['id']);
        $this->assertTrue($response->isSuccessful());
    }

    public function testPutReturnsSuccessfulResponse(): void
    {
        $mockHandler = new MockHandler([
            new GuzzleResponse(200, [], json_encode(['id' => 'res_123', 'updated' => true])),
        ]);

        $response = $this->makeRequest($mockHandler)->put('/resource/res_123', ['field' => 'value']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->isSuccessful());
    }

    public function testPatchReturnsSuccessfulResponse(): void
    {
        $mockHandler = new MockHandler([
            new GuzzleResponse(200, [], json_encode(['id' => 'res_123'])),
        ]);

        $response = $this->makeRequest($mockHandler)->patch('/resource/res_123', ['title' => 'New Title']);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testDeleteReturnsSuccessfulResponse(): void
    {
        $mockHandler = new MockHandler([
            new GuzzleResponse(204, [], ''),
        ]);

        $response = $this->makeRequest($mockHandler)->delete('/invoices/inv_123');

        $this->assertSame(204, $response->getStatusCode());
        $this->assertTrue($response->isSuccessful());
    }

    public function testResponseIncludesHttpHeaders(): void
    {
        $mockHandler = new MockHandler([
            new GuzzleResponse(200, ['X-Request-Id' => 'req_abc'], json_encode([])),
        ]);

        $response = $this->makeRequest($mockHandler)->get('/invoices');

        $this->assertArrayHasKey('X-Request-Id', $response->getHeaders());
    }

    public function testEmptyJsonBodyDecodesAsEmptyArray(): void
    {
        $mockHandler = new MockHandler([
            new GuzzleResponse(200, [], '{}'),
        ]);

        $response = $this->makeRequest($mockHandler)->get('/invoices');

        $this->assertSame([], $response->getData());
    }

    // -------------------------------------------------------------------------
    // Client error handling (4xx)
    // -------------------------------------------------------------------------

    public function test401ResponseThrowsAuthenticationException(): void
    {
        $mockHandler = new MockHandler([
            new GuzzleResponse(401, [], json_encode(['message' => 'Invalid API key'])),
        ]);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid API key');

        $this->makeRequest($mockHandler)->get('/invoices');
    }

    public function test401AuthenticationExceptionHasCorrectStatusCode(): void
    {
        $mockHandler = new MockHandler([
            new GuzzleResponse(401, [], json_encode(['message' => 'Unauthorized'])),
        ]);

        try {
            $this->makeRequest($mockHandler)->get('/invoices');
            $this->fail('Expected AuthenticationException');
        } catch (AuthenticationException $e) {
            $this->assertSame(401, $e->getStatusCode());
            $this->assertSame(['message' => 'Unauthorized'], $e->getResponse());
        }
    }

    public function test422ResponseThrowsValidationException(): void
    {
        $body = [
            'message' => 'The given data was invalid',
            'errors' => ['amount' => ['The amount field is required.']],
        ];
        $mockHandler = new MockHandler([
            new GuzzleResponse(422, [], json_encode($body)),
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The given data was invalid');

        $this->makeRequest($mockHandler)->post('/invoices', []);
    }

    public function test422ValidationExceptionContainsFieldErrors(): void
    {
        $errors = [
            'amount' => ['The amount must be a positive number.'],
            'currency' => ['The currency is not supported.'],
        ];
        $body = ['message' => 'Validation failed', 'errors' => $errors];
        $mockHandler = new MockHandler([
            new GuzzleResponse(422, [], json_encode($body)),
        ]);

        try {
            $this->makeRequest($mockHandler)->post('/invoices', []);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame($errors, $e->getErrors());
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test422ValidationExceptionWithMissingErrorsKey(): void
    {
        $body = ['message' => 'Unprocessable'];
        $mockHandler = new MockHandler([
            new GuzzleResponse(422, [], json_encode($body)),
        ]);

        try {
            $this->makeRequest($mockHandler)->post('/invoices', []);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame([], $e->getErrors());
        }
    }

    public function test429ResponseThrowsRateLimitException(): void
    {
        $mockHandler = new MockHandler([
            new GuzzleResponse(
                429,
                ['Retry-After' => ['60']],
                json_encode(['message' => 'Too Many Requests'])
            ),
        ]);

        $this->expectException(RateLimitException::class);
        $this->expectExceptionMessage('Too Many Requests');

        $this->makeRequest($mockHandler)->get('/invoices');
    }

    public function test429ResponseExceptionContainsRetryAfter(): void
    {
        $mockHandler = new MockHandler([
            new GuzzleResponse(
                429,
                ['Retry-After' => ['120']],
                json_encode(['message' => 'Too Many Requests'])
            ),
        ]);

        try {
            $this->makeRequest($mockHandler)->get('/invoices');
            $this->fail('Expected RateLimitException');
        } catch (RateLimitException $e) {
            $this->assertSame(429, $e->getStatusCode());
            $this->assertSame(120, $e->getRetryAfter());
        }
    }

    public function test429ResponseWithNoRetryAfterHeader(): void
    {
        $mockHandler = new MockHandler([
            new GuzzleResponse(429, [], json_encode(['message' => 'Too Many Requests'])),
        ]);

        try {
            $this->makeRequest($mockHandler)->get('/invoices');
            $this->fail('Expected RateLimitException');
        } catch (RateLimitException $e) {
            $this->assertNull($e->getRetryAfter());
        }
    }

    public function test400ResponseThrowsGenericApiException(): void
    {
        $mockHandler = new MockHandler([
            new GuzzleResponse(400, [], json_encode(['message' => 'Bad Request'])),
        ]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Bad Request');

        $this->makeRequest($mockHandler)->get('/invoices');
    }

    public function test403ResponseThrowsApiExceptionWithCorrectCode(): void
    {
        $mockHandler = new MockHandler([
            new GuzzleResponse(403, [], json_encode(['message' => 'Forbidden'])),
        ]);

        try {
            $this->makeRequest($mockHandler)->get('/admin');
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame(403, $e->getStatusCode());
            $this->assertSame('Forbidden', $e->getMessage());
        }
    }

    public function test404ResponseThrowsApiExceptionWithNotFoundMessage(): void
    {
        $body = ['message' => 'Invoice not found', 'error' => 'not_found'];
        $mockHandler = new MockHandler([
            new GuzzleResponse(404, [], json_encode($body)),
        ]);

        try {
            $this->makeRequest($mockHandler)->get('/invoices/inv_nonexistent');
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame(404, $e->getStatusCode());
            $this->assertSame('Invoice not found', $e->getMessage());
            $this->assertSame($body, $e->getResponse());
        }
    }

    public function testClientErrorFallsBackToErrorKeyWhenNoMessageKey(): void
    {
        $body = ['error' => 'resource_not_found'];
        $mockHandler = new MockHandler([
            new GuzzleResponse(404, [], json_encode($body)),
        ]);

        try {
            $this->makeRequest($mockHandler)->get('/invoices/missing');
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame('resource_not_found', $e->getMessage());
        }
    }

    public function testClientErrorUsesDefaultMessageWhenBothMessageAndErrorAbsent(): void
    {
        $mockHandler = new MockHandler([
            new GuzzleResponse(400, [], json_encode(['code' => 'bad_req'])),
        ]);

        try {
            $this->makeRequest($mockHandler)->get('/invoices');
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame('Client error occurred', $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Server error handling (5xx)
    // -------------------------------------------------------------------------

    public function test500ResponseThrowsApiException(): void
    {
        $mockHandler = new MockHandler([
            new GuzzleResponse(500, [], json_encode(['message' => 'Internal Server Error'])),
        ]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Internal Server Error');

        $this->makeRequest($mockHandler)->get('/invoices');
    }

    public function test500ApiExceptionHasCorrectStatusCode(): void
    {
        $mockHandler = new MockHandler([
            new GuzzleResponse(500, [], json_encode(['message' => 'Server exploded'])),
        ]);

        try {
            $this->makeRequest($mockHandler)->get('/invoices');
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame(500, $e->getStatusCode());
        }
    }

    public function test503ResponseThrowsApiException(): void
    {
        $mockHandler = new MockHandler([
            new GuzzleResponse(503, [], json_encode(['message' => 'Service Unavailable'])),
        ]);

        $this->expectException(ApiException::class);

        $this->makeRequest($mockHandler)->get('/invoices');
    }

    public function testServerErrorUsesDefaultMessageWhenBodyHasNoMessageKey(): void
    {
        $mockHandler = new MockHandler([
            new GuzzleResponse(500, [], json_encode(['code' => 'internal_error'])),
        ]);

        try {
            $this->makeRequest($mockHandler)->get('/invoices');
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame('Server error occurred', $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Network / connection errors
    // -------------------------------------------------------------------------

    public function testConnectionErrorThrowsApiException(): void
    {
        $mockHandler = new MockHandler([
            new ConnectException(
                'Connection refused: Unable to connect to api.nodela.co',
                new GuzzleRequest('GET', 'https://api.nodela.co/v1/invoices')
            ),
        ]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessageMatches('/Request failed:/');

        $this->makeRequest($mockHandler)->get('/invoices');
    }

    public function testConnectionErrorWrapsOriginalException(): void
    {
        $networkError = new ConnectException(
            'cURL error 6: Could not resolve host',
            new GuzzleRequest('GET', 'https://api.nodela.co/v1/invoices')
        );
        $mockHandler = new MockHandler([$networkError]);

        try {
            $this->makeRequest($mockHandler)->get('/invoices');
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertNotNull($e->getPrevious());
            $this->assertInstanceOf(ConnectException::class, $e->getPrevious());
        }
    }

    // -------------------------------------------------------------------------
    // URL construction
    // -------------------------------------------------------------------------

    public function testGetRequestUsesFullUrlFromConfig(): void
    {
        $history = [];
        $historyMiddleware = \GuzzleHttp\Middleware::history($history);

        $mockHandler = new MockHandler([
            new GuzzleResponse(200, [], '[]'),
        ]);

        $stack = HandlerStack::create($mockHandler);
        $stack->push($historyMiddleware);

        $guzzleClient = new GuzzleClient(['handler' => $stack]);

        $reflection = new ReflectionClass(Request::class);
        $request = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('client')->setValue($request, $guzzleClient);
        $reflection->getProperty('config')->setValue($request, $this->config);

        $request->get('/invoices');

        $sentRequest = $history[0]['request'];
        $this->assertStringContainsString('https://api.nodela.co/v1/invoices', (string) $sentRequest->getUri());
    }
}
