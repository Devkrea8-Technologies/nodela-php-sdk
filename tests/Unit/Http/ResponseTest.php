<?php

declare(strict_types=1);

namespace Nodelapay\Nodela\Tests\Unit\Http;

use Nodelapay\Nodela\Http\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
{
    public function testGetStatusCode(): void
    {
        $response = new Response(200, []);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testGetData(): void
    {
        $data = ['id' => 'inv_123', 'amount' => 1000, 'currency' => 'USD'];
        $response = new Response(200, $data);
        $this->assertSame($data, $response->getData());
    }

    public function testGetDataReturnsEmptyArrayWhenNoData(): void
    {
        $response = new Response(200, []);
        $this->assertSame([], $response->getData());
    }

    public function testGetHeaders(): void
    {
        $headers = ['Content-Type' => ['application/json'], 'X-Request-Id' => ['abc-123']];
        $response = new Response(200, [], $headers);
        $this->assertSame($headers, $response->getHeaders());
    }

    public function testGetHeadersDefaultsToEmptyArray(): void
    {
        $response = new Response(200, []);
        $this->assertSame([], $response->getHeaders());
    }

    #[DataProvider('successfulStatusCodesProvider')]
    public function testIsSuccessfulReturnsTrueForSuccessCodes(int $statusCode): void
    {
        $response = new Response($statusCode, []);
        $this->assertTrue($response->isSuccessful());
    }

    public static function successfulStatusCodesProvider(): array
    {
        return [
            '200 OK' => [200],
            '201 Created' => [201],
            '202 Accepted' => [202],
            '204 No Content' => [204],
            '299 Edge case' => [299],
        ];
    }

    #[DataProvider('unsuccessfulStatusCodesProvider')]
    public function testIsSuccessfulReturnsFalseForNonSuccessCodes(int $statusCode): void
    {
        $response = new Response($statusCode, []);
        $this->assertFalse($response->isSuccessful());
    }

    public static function unsuccessfulStatusCodesProvider(): array
    {
        return [
            '400 Bad Request' => [400],
            '401 Unauthorized' => [401],
            '403 Forbidden' => [403],
            '404 Not Found' => [404],
            '422 Unprocessable Entity' => [422],
            '429 Too Many Requests' => [429],
            '500 Internal Server Error' => [500],
            '502 Bad Gateway' => [502],
            '503 Service Unavailable' => [503],
        ];
    }

    public function testIsSuccessfulReturnsFalseForStatusBelow200(): void
    {
        $response = new Response(199, []);
        $this->assertFalse($response->isSuccessful());
    }

    public function testToArrayReturnsDataArray(): void
    {
        $data = ['id' => 'txn_001', 'amount' => 5000];
        $response = new Response(200, $data);
        $this->assertSame($data, $response->toArray());
    }

    public function testToArrayAndGetDataReturnSameValue(): void
    {
        $data = ['foo' => 'bar'];
        $response = new Response(200, $data);
        $this->assertSame($response->getData(), $response->toArray());
    }

    public function testToJsonEncodesDataAsJsonString(): void
    {
        $data = ['id' => 'inv_123', 'amount' => 5000];
        $response = new Response(200, $data);
        $this->assertSame(json_encode($data), $response->toJson());
    }

    public function testToJsonWithEmptyDataReturnsEmptyJsonObject(): void
    {
        $response = new Response(200, []);
        $this->assertSame('[]', $response->toJson());
    }

    public function testToJsonWithNestedData(): void
    {
        $data = [
            'customer' => ['name' => 'John Doe', 'email' => 'john@example.com'],
            'items' => [['sku' => 'ITEM-001', 'qty' => 2]],
        ];
        $response = new Response(200, $data);
        $this->assertJson($response->toJson());
        $this->assertSame(json_encode($data), $response->toJson());
    }

    public function testToJsonWithSpecialCharacters(): void
    {
        $data = ['title' => 'Payment for "order #123" & more'];
        $response = new Response(200, $data);
        $json = $response->toJson();
        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertSame($data['title'], $decoded['title']);
    }

    public function testStatusCode201IsSuccessful(): void
    {
        $response = new Response(201, ['id' => 'new_resource']);
        $this->assertTrue($response->isSuccessful());
        $this->assertSame(201, $response->getStatusCode());
    }

    public function testDataCanContainNestedArrays(): void
    {
        $data = [
            'invoices' => [
                ['id' => 'inv_001', 'status' => 'paid'],
                ['id' => 'inv_002', 'status' => 'pending'],
            ],
            'pagination' => ['page' => 1, 'total' => 2],
        ];
        $response = new Response(200, $data);
        $this->assertCount(2, $response->getData()['invoices']);
        $this->assertSame(1, $response->getData()['pagination']['page']);
    }
}
