# HTTP Layer

The HTTP layer sits between the resource classes (`Invoices`, `Transactions`) and the Nodela API. It is composed of two classes: `Request` and `Response`.

This document is aimed at contributors and advanced users who need to understand or extend the internal plumbing. If you are building an application, the [Getting Started](getting-started.md) guide is a better starting point.

---

## Overview

```
Client
  ├── Invoices   ──┐
  └── Transactions ─┤──► Request ──► GuzzleHTTP ──► Nodela API
                    └──► Response ◄──────────────────────────┘
```

`Request` wraps a `GuzzleHttp\Client` instance, builds URLs via `Config::getFullUrl()`, and maps Guzzle exceptions to typed SDK exceptions. `Response` is a thin value object that carries the decoded JSON body, HTTP status code, and headers.

---

## `Request`

**Namespace:** `Nodelapay\Nodela\Http\Request`

### Constructor

```php
public function __construct(Config $config)
```

Internally creates a `GuzzleHttp\Client` pre-configured with:

- `timeout` from `Config::getTimeout()`
- `headers` from `Config::getHeaders()` + `Authorization: Bearer {apiKey}`

Because `GuzzleClient` is constructed internally, there is no public DI injection in the standard constructor. Tests bypass this via `ReflectionClass::newInstanceWithoutConstructor()` — see [testing.md](testing.md).

### Public Methods

| Method                                   | HTTP verb | Description                               |
|------------------------------------------|-----------|-------------------------------------------|
| `get(string $endpoint, array $query)`    | GET       | Appends `$query` as URL query parameters  |
| `post(string $endpoint, array $data)`    | POST      | Sends `$data` as a JSON body              |
| `put(string $endpoint, array $data)`     | PUT       | Sends `$data` as a JSON body              |
| `patch(string $endpoint, array $data)`   | PATCH     | Sends `$data` as a JSON body              |
| `delete(string $endpoint)`               | DELETE    | No body                                   |

All methods return a `Response` instance on success or throw a subclass of `ApiException` on failure.

### URL Construction

Every method calls `Config::getFullUrl($endpoint)` before dispatching, which builds:

```
https://api.nodela.co/v1/{endpoint}
```

Leading slashes on `$endpoint` are stripped automatically.

### Exception Mapping

| Guzzle exception         | HTTP status | SDK exception thrown                |
|--------------------------|-------------|-------------------------------------|
| `ClientException`        | 401         | `AuthenticationException`           |
| `ClientException`        | 422         | `ValidationException`               |
| `ClientException`        | 429         | `RateLimitException`                |
| `ClientException`        | other 4xx   | `ApiException`                      |
| `ServerException`        | 5xx         | `ApiException`                      |
| `ConnectException`       | —           | `ApiException` (statusCode = 0)     |
| `RequestException`       | —           | `ApiException`                      |

The `ConnectException` catch is placed _before_ `RequestException` because `ConnectException` extends `RequestException` in Guzzle 7 — catching the parent first would swallow it.

---

## `Response`

**Namespace:** `Nodelapay\Nodela\Http\Response`

A value object returned by every `Request` method. It carries three pieces of data from the HTTP response.

### Constructor

```php
public function __construct(int $statusCode, array $data, array $headers = [])
```

| Parameter     | Type    | Description                                  |
|---------------|---------|----------------------------------------------|
| `$statusCode` | `int`   | HTTP status code (e.g. `200`, `201`)         |
| `$data`       | `array` | Decoded JSON body (`json_decode(..., true)`) |
| `$headers`    | `array` | Raw response headers from Guzzle             |

### Methods

| Method              | Returns  | Description                                               |
|---------------------|----------|-----------------------------------------------------------|
| `getStatusCode()`   | `int`    | HTTP status code                                          |
| `getData()`         | `array`  | Decoded JSON body                                         |
| `getHeaders()`      | `array`  | Raw response headers                                      |
| `isSuccessful()`    | `bool`   | `true` when `statusCode >= 200 && statusCode < 300`       |
| `toArray()`         | `array`  | Alias for `getData()`                                     |
| `toJson()`          | `string` | JSON-encodes the data array                               |

### Example

```php
$response = $client->invoices->verify('inv_abc123');

$response->getStatusCode(); // 200
$response->isSuccessful();  // true
$response->getData();       // ['id' => 'inv_abc123', 'status' => 'paid', ...]
$response->toJson();        // '{"id":"inv_abc123","status":"paid",...}'
$response->getHeaders();    // ['Content-Type' => ['application/json'], ...]
```

---

## Request Flow (step by step)

1. A resource method (e.g. `Invoices::create()`) calls `$this->request->post('/invoices', $params)`.
2. `Request::post()` delegates to `Request::request('POST', '/invoices', ['json' => $params])`.
3. `Request::request()` calls `Config::getFullUrl('/invoices')` → `"https://api.nodela.co/v1/invoices"`.
4. Guzzle dispatches the HTTP request with the pre-built headers.
5. On success, the response body is decoded and wrapped in a `Response` object.
6. On failure, the appropriate Guzzle exception is caught and re-thrown as an SDK exception.

---

## Extending the HTTP Layer

If you need to customise HTTP behaviour (e.g. add retry logic, logging, or a custom transport), you can:

1. Extend `Request` and override `request()` or individual verb methods.
2. Subclass `Client` and pass your custom `Request` instance via protected property access (or via a custom constructor).

This is an advanced pattern. For most use cases, custom headers and timeouts via `Config` are sufficient.
