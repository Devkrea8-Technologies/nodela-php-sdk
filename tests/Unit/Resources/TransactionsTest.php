<?php

declare(strict_types=1);

namespace Nodelapay\Nodela\Tests\Unit\Resources;

use Nodelapay\Nodela\Http\Request;
use Nodelapay\Nodela\Http\Response;
use Nodelapay\Nodela\Resources\Transactions;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TransactionsTest extends TestCase
{
    private MockObject&Request $mockRequest;
    private Transactions $transactions;

    protected function setUp(): void
    {
        $this->mockRequest = $this->createMock(Request::class);
        $this->transactions = new Transactions($this->mockRequest);
    }

    // -------------------------------------------------------------------------
    // list()
    // -------------------------------------------------------------------------

    public function testListCallsGetOnCorrectEndpoint(): void
    {
        $this->mockRequest
            ->expects($this->once())
            ->method('get')
            ->with('/transactions', $this->anything())
            ->willReturn(new Response(200, []));

        $this->transactions->list();
    }

    public function testListWithNoArgumentsPassesEmptyQueryParams(): void
    {
        $this->mockRequest
            ->expects($this->once())
            ->method('get')
            ->with('/transactions', [])
            ->willReturn(new Response(200, []));

        $this->transactions->list();
    }

    public function testListWithPageAndLimitPassesThemAsQueryParams(): void
    {
        $params = ['page' => 2, 'limit' => 20];

        $this->mockRequest
            ->expects($this->once())
            ->method('get')
            ->with('/transactions', $params)
            ->willReturn(new Response(200, []));

        $this->transactions->list($params);
    }

    public function testListWithOnlyPageParam(): void
    {
        $this->mockRequest
            ->expects($this->once())
            ->method('get')
            ->with('/transactions', ['page' => 3])
            ->willReturn(new Response(200, []));

        $this->transactions->list(['page' => 3]);
    }

    public function testListWithOnlyLimitParam(): void
    {
        $this->mockRequest
            ->expects($this->once())
            ->method('get')
            ->with('/transactions', ['limit' => 50])
            ->willReturn(new Response(200, []));

        $this->transactions->list(['limit' => 50]);
    }

    public function testListReturnsResponseObject(): void
    {
        $this->mockRequest
            ->expects($this->once())
            ->method('get')
            ->willReturn(new Response(200, []));

        $response = $this->transactions->list();

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testListReturnsResponseWithTransactionData(): void
    {
        $data = [
            ['id' => 'txn_001', 'amount' => 5000, 'currency' => 'USD', 'status' => 'completed'],
            ['id' => 'txn_002', 'amount' => 2500, 'currency' => 'NGN', 'status' => 'pending'],
        ];

        $this->mockRequest
            ->expects($this->once())
            ->method('get')
            ->willReturn(new Response(200, $data));

        $response = $this->transactions->list();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($data, $response->getData());
        $this->assertCount(2, $response->getData());
    }

    public function testListWithPageOneReturnsFirstPage(): void
    {
        $this->mockRequest
            ->expects($this->once())
            ->method('get')
            ->with('/transactions', ['page' => 1, 'limit' => 10])
            ->willReturn(new Response(200, []));

        $this->transactions->list(['page' => 1, 'limit' => 10]);
    }

    public function testListResponseIsSuccessful(): void
    {
        $this->mockRequest
            ->expects($this->once())
            ->method('get')
            ->willReturn(new Response(200, []));

        $response = $this->transactions->list();

        $this->assertTrue($response->isSuccessful());
    }

    public function testListCanPassArbitraryQueryParams(): void
    {
        // Transactions::list() accepts any array — future-proofing
        $params = ['page' => 1, 'limit' => 5, 'status' => 'completed'];

        $this->mockRequest
            ->expects($this->once())
            ->method('get')
            ->with('/transactions', $params)
            ->willReturn(new Response(200, []));

        $this->transactions->list($params);
    }

    public function testListResponseDataCanBeJsonEncoded(): void
    {
        $data = [['id' => 'txn_json', 'amount' => 100]];
        $this->mockRequest->expects($this->once())->method('get')->willReturn(new Response(200, $data));

        $response = $this->transactions->list();

        $this->assertJson($response->toJson());
        $decoded = json_decode($response->toJson(), true);
        $this->assertSame('txn_json', $decoded[0]['id']);
    }
}
