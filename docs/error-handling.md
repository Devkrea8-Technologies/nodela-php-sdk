# Error Handling

The SDK maps every API and network failure to a typed PHP exception. All exceptions extend `ApiException`, so you can catch them at whatever granularity you need.

---

## Exception Hierarchy

```
\Exception
  └── ApiException                    (any API or network error)
        ├── AuthenticationException   (HTTP 401 — bad API key)
        ├── ValidationException       (HTTP 422 — invalid request data)
        └── RateLimitException        (HTTP 429 — too many requests)
```

There is also a special case for client-side validation:

```
\InvalidArgumentException             (unsupported currency, thrown before any request)
```

---

## Exception Reference

### `ApiException`

**Namespace:** `Nodelapay\Nodela\Exceptions\ApiException`

The base class for all SDK exceptions. Thrown for:

- `4xx` errors not covered by a more specific subclass (e.g. 400, 403, 404)
- `5xx` server errors
- Network failures (connection refused, DNS failure, timeout)

| Method              | Returns    | Description                                   |
|---------------------|------------|-----------------------------------------------|
| `getMessage()`      | `string`   | Human-readable error description              |
| `getStatusCode()`   | `int`      | HTTP status code (0 for network-level errors) |
| `getResponse()`     | `array`    | Decoded response body from the API            |
| `getPrevious()`     | `?Throwable` | The underlying Guzzle exception, if any     |

```php
use Nodelapay\Nodela\Exceptions\ApiException;

try {
    $client->invoices->create([...]);
} catch (ApiException $e) {
    echo $e->getMessage();    // "Not found"
    echo $e->getStatusCode(); // 404
    print_r($e->getResponse()); // ['error' => 'Invoice not found', ...]
}
```

---

### `AuthenticationException`

**Namespace:** `Nodelapay\Nodela\Exceptions\AuthenticationException`

Thrown when the API returns HTTP `401`. This means your API key is missing, expired, or invalid.

Inherits all methods from `ApiException`. No additional methods.

```php
use Nodelapay\Nodela\Exceptions\AuthenticationException;

try {
    $client->invoices->create([...]);
} catch (AuthenticationException $e) {
    // Log and alert — this should not happen in production
    logger()->critical('Nodela auth failure', ['message' => $e->getMessage()]);
}
```

**Common causes:**

- Wrong API key passed to `Client`
- API key revoked or rotated
- Using a test key against the production environment (or vice versa)

---

### `ValidationException`

**Namespace:** `Nodelapay\Nodela\Exceptions\ValidationException`

Thrown when the API returns HTTP `422`. The request was well-formed but the data failed server-side validation.

| Method        | Returns  | Description                                             |
|---------------|----------|---------------------------------------------------------|
| `getErrors()` | `array`  | Field-level validation errors keyed by field name       |

```php
use Nodelapay\Nodela\Exceptions\ValidationException;

try {
    $client->invoices->create([
        'amount'   => -100,  // invalid
        'currency' => 'NGN',
    ]);
} catch (ValidationException $e) {
    echo $e->getMessage(); // "The given data was invalid."

    foreach ($e->getErrors() as $field => $messages) {
        // $field    => "amount"
        // $messages => ["The amount must be a positive number."]
        echo "$field: " . implode(', ', (array) $messages) . "\n";
    }
}
```

**Note:** Currency errors are caught _client-side_ before the request is sent and throw `\InvalidArgumentException` instead.

---

### `RateLimitException`

**Namespace:** `Nodelapay\Nodela\Exceptions\RateLimitException`

Thrown when the API returns HTTP `429 Too Many Requests`.

| Method            | Returns  | Description                                                  |
|-------------------|----------|--------------------------------------------------------------|
| `getRetryAfter()` | `?int`   | Seconds to wait before retrying, parsed from `Retry-After` header. `null` if the header was not present. |

```php
use Nodelapay\Nodela\Exceptions\RateLimitException;

try {
    $response = $client->transactions->list();
} catch (RateLimitException $e) {
    $wait = $e->getRetryAfter() ?? 60; // fall back to 60s if header missing
    echo "Rate limited. Retrying in {$wait} seconds.";
    sleep($wait);
    // retry the request
}
```

---

## Client-Side Validation

The `InvalidArgumentException` is thrown by `Invoices::create()` before any HTTP request is made when the currency code is not in the supported list:

```php
try {
    $client->invoices->create([
        'amount'   => 500,
        'currency' => 'XYZ',
    ]);
} catch (\InvalidArgumentException $e) {
    echo $e->getMessage();
    // Unsupported currency: "XYZ". Supported currencies: USD, EUR, NGN, ...
}
```

---

## Recommended Catch Order

Always catch the most specific exception first:

```php
use Nodelapay\Nodela\Exceptions\AuthenticationException;
use Nodelapay\Nodela\Exceptions\ValidationException;
use Nodelapay\Nodela\Exceptions\RateLimitException;
use Nodelapay\Nodela\Exceptions\ApiException;

try {
    $response = $client->invoices->create($params);
} catch (\InvalidArgumentException $e) {
    // Client-side currency error — fix the input
} catch (AuthenticationException $e) {
    // Config error — alert ops, do not retry
} catch (ValidationException $e) {
    // Bad data — return errors to the caller
    $errors = $e->getErrors();
} catch (RateLimitException $e) {
    // Throttled — back off and retry
    sleep($e->getRetryAfter() ?? 60);
} catch (ApiException $e) {
    // Unexpected error — log and surface a generic message
    logger()->error('Nodela error', [
        'status'  => $e->getStatusCode(),
        'message' => $e->getMessage(),
        'body'    => $e->getResponse(),
    ]);
}
```

---

## Network Errors

If the HTTP request never reaches the server (DNS failure, connection refused, timeout), the underlying `GuzzleHttp\Exception\ConnectException` is caught and re-thrown as an `ApiException` with:

- `getMessage()` — Guzzle's original message prefixed with `"Request failed: "`
- `getStatusCode()` — `0` (no HTTP response was received)
- `getResponse()` — empty array `[]`

```php
catch (ApiException $e) {
    if ($e->getStatusCode() === 0) {
        // Network-level failure — check connectivity
    }
}
```
