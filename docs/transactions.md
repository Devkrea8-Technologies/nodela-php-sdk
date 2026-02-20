# Transactions

The `$client->transactions` resource provides access to your Nodela transaction history.

---

## Methods

| Method                    | HTTP   | Endpoint              |
|---------------------------|--------|-----------------------|
| `list(array $params = [])` | `GET` | `/v1/transactions`    |

---

## `list()`

Retrieves a paginated list of transactions for your account.

### Signature

```php
public function list(array $params = []): Response
```

### Parameters

All parameters are optional. Pass an empty array (or no argument) to retrieve the first page with the default limit.

| Field   | Type  | Description                                           |
|---------|-------|-------------------------------------------------------|
| `page`  | `int` | Page number (1-based). Default: `1`                   |
| `limit` | `int` | Number of results per page. Default determined by API |

Additional filter parameters accepted by the API can be passed in the same array and will be forwarded as query string values.

### Example: first page, default limit

```php
$response = $client->transactions->list();

$transactions = $response->getData();

foreach ($transactions as $tx) {
    echo $tx['id'] . ' — ' . $tx['amount'] . ' ' . $tx['currency'] . "\n";
}
```

### Example: paginating results

```php
$page    = 1;
$perPage = 50;

do {
    $response      = $client->transactions->list(['page' => $page, 'limit' => $perPage]);
    $transactions  = $response->getData();

    foreach ($transactions as $tx) {
        // process each transaction
    }

    $page++;
} while (count($transactions) === $perPage);
```

### Example: working with the Response object

```php
$response = $client->transactions->list(['page' => 1, 'limit' => 10]);

// Check HTTP status
if ($response->isSuccessful()) {
    $data = $response->getData();
}

// Convert to JSON string
$json = $response->toJson();

// Access as array
$array = $response->toArray();
```

---

## Response Shape

The API returns a collection of transaction objects. The exact schema depends on your Nodela plan and API version, but a typical item looks like:

```json
[
  {
    "id":         "txn_xyz789",
    "invoice_id": "inv_abc123",
    "amount":     25000,
    "currency":   "NGN",
    "status":     "successful",
    "reference":  "order_7891",
    "created_at": "2026-02-21T10:05:42Z"
  }
]
```

---

## Error Handling

```php
use Nodelapay\Nodela\Exceptions\AuthenticationException;
use Nodelapay\Nodela\Exceptions\RateLimitException;
use Nodelapay\Nodela\Exceptions\ApiException;

try {
    $response = $client->transactions->list(['page' => 1, 'limit' => 100]);
} catch (AuthenticationException $e) {
    // API key is invalid or missing
    echo 'Authentication failed: ' . $e->getMessage();
} catch (RateLimitException $e) {
    $wait = $e->getRetryAfter() ?? 60;
    sleep($wait);
    // retry
} catch (ApiException $e) {
    echo 'Error ' . $e->getStatusCode() . ': ' . $e->getMessage();
}
```

See [error-handling.md](error-handling.md) for the full exception reference.
