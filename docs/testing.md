# Testing

The SDK ships with a comprehensive test suite built on PHPUnit 12.5. This document explains how to run the tests and how to write new ones following the project's established patterns.

---

## Running the Test Suite

```bash
# Run all tests (no coverage — fastest)
./vendor/bin/phpunit --no-coverage

# Run all tests with HTML coverage report (output to coverage/)
./vendor/bin/phpunit

# Run only unit tests
./vendor/bin/phpunit --testsuite Unit --no-coverage

# Run only integration tests
./vendor/bin/phpunit --testsuite Integration --no-coverage

# Run a specific test file
./vendor/bin/phpunit tests/Unit/Resources/InvoicesTest.php --no-coverage

# Run a specific test method
./vendor/bin/phpunit --filter testCreateCallsPostEndpoint --no-coverage
```

---

## Test Suite Structure

```text
tests/
+-- Unit/
|   +-- ConfigTest.php                     - Config class
|   +-- ClientTest.php                     - Client class
|   +-- Http/
|   |   +-- RequestTest.php                - HTTP verbs, exception mapping
|   +-- Resources/
|   |   +-- InvoicesTest.php               - create(), verify(), currency validation
|   |   +-- TransactionsTest.php           - list(), pagination
|   +-- Exceptions/
|       +-- ApiExceptionTest.php
|       +-- AuthenticationExceptionTest.php
|       +-- RateLimitExceptionTest.php
|       +-- ValidationExceptionTest.php
+-- Integration/
    +-- InvoicesIntegrationTest.php        - full request/response cycle
    +-- TransactionsIntegrationTest.php
```

### PHPUnit configuration

See [phpunit.xml](https://github.com/Devkrea8-Technologies/nodela-php-sdk/blob/main/phpunit.xml) for the full configuration. The `APP_ENV=testing` env var is set automatically.

---

## Known Test State

The test suite reports **296 tests** total. Of these:

- **12 errors** — intentional, surfacing bugs in the source code (documented in the project memory):
  - `ConfigTest` and `ClientTest` call `getHeaders()` on a version of `Config` that had a `getHedaers()` typo. These tests are the bug's canaries.
- **12 skips** — intentional, for classes/scenarios that cannot be loaded in the current state:
  - `RateLimitException` tests (PSR-4 class name mismatch)
  - `ConnectException`-specific tests

All remaining tests pass. When you fix the source bugs these numbers will change.

---

## Testing Patterns

The SDK uses two distinct patterns depending on what is being tested.

---

### Pattern 1 — Resource Unit Tests

Resource classes (`Invoices`, `Transactions`) depend on `Request`. The simplest approach is PHPUnit's `createMock()`:

```php
use PHPUnit\Framework\TestCase;
use Nodelapay\Nodela\Http\Request;
use Nodelapay\Nodela\Http\Response;
use Nodelapay\Nodela\Resources\Invoices;

class InvoicesTest extends TestCase
{
    private Request $request;
    private Invoices $invoices;

    protected function setUp(): void
    {
        // createMock() skips the Request constructor entirely
        $this->request = $this->createMock(Request::class);
        $this->invoices = new Invoices($this->request);
    }

    public function testCreateCallsPostEndpoint(): void
    {
        $responseData = ['id' => 'inv_abc', 'status' => 'pending'];
        $response = new Response(201, $responseData);

        $this->request
            ->expects($this->once())
            ->method('post')
            ->with('/invoices', $this->arrayHasKey('amount'))
            ->willReturn($response);

        $result = $this->invoices->create(['amount' => 1000, 'currency' => 'NGN']);

        $this->assertSame(201, $result->getStatusCode());
        $this->assertSame($responseData, $result->getData());
    }
}
```

**Why `createMock()` instead of `new Request()`?**

`Request::__construct()` creates a real `GuzzleClient` internally. `createMock()` bypasses the constructor entirely and returns a test double that can be configured with `expects()`.

---

### Pattern 2 — Request / Integration Tests

Testing `Request` itself requires injecting a fake HTTP transport. Guzzle's `MockHandler` is perfect for this, but you cannot simply call `new Request($config)` because the constructor wires up a real client.

The solution is `ReflectionClass::newInstanceWithoutConstructor()`:

```php
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Nodelapay\Nodela\Config;
use Nodelapay\Nodela\Http\Request;

class RequestTest extends TestCase
{
    private function makeRequest(array $mockResponses): Request
    {
        $config  = new Config('test-api-key');
        $handler = new MockHandler($mockResponses);
        $stack   = HandlerStack::create($handler);
        $guzzle  = new GuzzleClient(['handler' => $stack]);

        // Bypass the constructor
        $ref = new ReflectionClass(Request::class);
        $req = $ref->newInstanceWithoutConstructor();

        // Inject dependencies via reflection
        $configProp = $ref->getProperty('config');
        $configProp->setAccessible(true);
        $configProp->setValue($req, $config);

        $clientProp = $ref->getProperty('client');
        $clientProp->setAccessible(true);
        $clientProp->setValue($req, $guzzle);

        return $req;
    }

    public function testGetReturnsDecodedJson(): void
    {
        $mockBody = json_encode(['data' => [['id' => 'txn_1']]]);
        $request  = $this->makeRequest([
            new GuzzleResponse(200, ['Content-Type' => 'application/json'], $mockBody),
        ]);

        $response = $request->get('/transactions');

        $this->assertTrue($response->isSuccessful());
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testPostThrowsAuthenticationExceptionOn401(): void
    {
        $this->expectException(\Nodelapay\Nodela\Exceptions\AuthenticationException::class);

        $request = $this->makeRequest([
            new GuzzleResponse(401, [], json_encode(['message' => 'Unauthorized'])),
        ]);

        $request->post('/invoices', []);
    }
}
```

---

## Testing Exception Behaviour

Each exception class should be tested in isolation to verify constructor defaults and accessor methods:

```php
use PHPUnit\Framework\TestCase;
use Nodelapay\Nodela\Exceptions\ValidationException;

class ValidationExceptionTest extends TestCase
{
    public function testDefaultStatusCodeIs422(): void
    {
        $e = new ValidationException('Validation failed');
        $this->assertSame(422, $e->getStatusCode());
    }

    public function testGetErrorsReturnsFieldErrors(): void
    {
        $errors = ['email' => ['The email field is required.']];
        $e      = new ValidationException('Invalid', $errors);

        $this->assertSame($errors, $e->getErrors());
    }
}
```

---

## Testing Currency Validation

The `Invoices::create()` method validates currency client-side. Test both the happy path and the guard:

```php
public function testCreateThrowsForUnsupportedCurrency(): void
{
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessageMatches('/Unsupported currency/');

    $this->invoices->create(['amount' => 100, 'currency' => 'XYZ']);
}

public function testCreateNormalisesCurrencyToUppercase(): void
{
    $this->request
        ->expects($this->once())
        ->method('post')
        ->with('/invoices', $this->callback(fn($p) => $p['currency'] === 'NGN'))
        ->willReturn(new Response(201, []));

    $this->invoices->create(['amount' => 100, 'currency' => 'ngn']);
}
```

---

## Code Quality Checks

Run these before opening a pull request:

```bash
# Static analysis (must pass at max level)
./vendor/bin/phpstan analyse src --level=max

# Code style check
./vendor/bin/php-cs-fixer fix --dry-run --diff

# Apply code style fixes automatically
./vendor/bin/php-cs-fixer fix
```

Or via Composer scripts:

```bash
composer test        # phpunit
composer analyse     # phpstan
composer format      # php-cs-fixer fix
composer format:check # php-cs-fixer dry-run
```

---

## Checklist for New Tests

- [ ] Test file mirrors the `src/` directory structure under `tests/Unit/`
- [ ] Class name ends in `Test`, method names start with `test`
- [ ] No real network calls — use `createMock()` or `MockHandler`
- [ ] Every public method has at least one happy-path test
- [ ] Every exception path has at least one test
- [ ] Tests are deterministic (no sleeps, no random data, no `date()` without mocking)
- [ ] PHPStan passes after adding new test code
