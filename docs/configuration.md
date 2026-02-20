# Configuration

The `Config` class controls every aspect of how the SDK communicates with the Nodela API: the API key, request timeout, base URL, and custom HTTP headers.

---

## Default Behaviour

When you instantiate `Client` with only an API key, it creates a `Config` internally with these defaults:

| Setting     | Default value              |
|-------------|----------------------------|
| Base URL    | `https://api.nodela.co`    |
| API version | `v1`                       |
| Timeout     | `30` seconds               |
| Headers     | `Accept: application/json`, `Content-Type: application/json`, `User-Agent: NodelaPHP/1.0` |

---

## Creating a Custom Config

Pass a `Config` instance as the second argument to `Client`:

```php
use Nodelapay\Nodela\Client;
use Nodelapay\Nodela\Config;

$config = new Config(
    apiKey:  'your-api-key',
    timeout: 60,
    headers: [
        'X-Idempotency-Key' => 'unique-request-id',
        'X-Platform'        => 'MyApp/2.0',
    ],
);

$client = new Client('your-api-key', $config);
```

> The `apiKey` argument on `Client` is still required for the constructor signature, but when a `Config` is passed the key from `Config` is used for authentication.

---

## Constructor Reference

```php
public function __construct(
    string $apiKey,
    int    $timeout = 30,
    array  $headers = [],
)
```

| Parameter | Type     | Required | Description                                          |
|-----------|----------|----------|------------------------------------------------------|
| `apiKey`  | `string` | Yes      | Your Nodela API key                                  |
| `timeout` | `int`    | No       | Request timeout in seconds. Default: `30`            |
| `headers` | `array`  | No       | Additional HTTP headers merged with the defaults     |

---

## API Key

The API key is sent as a `Bearer` token on every request:

```
Authorization: Bearer your-api-key
```

Never hard-code API keys in source code. Load them from environment variables:

```php
$client = new Client($_ENV['NODELA_API_KEY']);

// Or with Config
$config = new Config(apiKey: getenv('NODELA_API_KEY'));
```

---

## Timeout

The timeout applies to the entire request lifecycle (connection + response). If the server does not respond within the configured limit a `ConnectException` is thrown and the SDK wraps it in an `ApiException`.

```php
// 10-second timeout for latency-sensitive paths
$config = new Config(apiKey: 'your-api-key', timeout: 10);

// No timeout (not recommended for production)
$config = new Config(apiKey: 'your-api-key', timeout: 0);
```

---

## Custom Headers

Custom headers are merged on top of the SDK defaults. If you supply a key that overlaps with a default header (e.g. `User-Agent`), your value wins:

```php
$config = new Config(
    apiKey:  'your-api-key',
    headers: [
        'User-Agent'        => 'MyApp/3.1',         // overrides NodelaPHP/1.0
        'X-Idempotency-Key' => bin2hex(random_bytes(16)),
    ],
);
```

**Effective headers sent on every request:**

```
Accept: application/json
Content-Type: application/json
User-Agent: MyApp/3.1
Authorization: Bearer your-api-key
X-Idempotency-Key: 4f3a...
```

---

## Available Methods

| Method                          | Returns   | Description                                                |
|---------------------------------|-----------|------------------------------------------------------------|
| `getApiKey()`                   | `string`  | The configured API key                                     |
| `getBaseUrl()`                  | `string`  | The base URL (`https://api.nodela.co`)                     |
| `getApiVersion()`               | `string`  | The API version string (`v1`)                              |
| `getTimeout()`                  | `int`     | The request timeout in seconds                             |
| `getHeaders()`                  | `array`   | Merged default + custom headers (without Authorization)    |
| `getFullUrl(string $endpoint)`  | `string`  | Builds the complete URL for a given endpoint path          |

### `getFullUrl()` example

```php
$config = new Config('key');
$config->getFullUrl('/invoices');
// => "https://api.nodela.co/v1/invoices"

$config->getFullUrl('invoices/inv_123/verify');
// => "https://api.nodela.co/v1/invoices/inv_123/verify"
```

Leading slashes on the endpoint are normalised automatically.
