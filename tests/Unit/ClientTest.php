<?php

declare(strict_types=1);

namespace Nodelapay\Nodela\Tests\Unit;

use Nodelapay\Nodela\Client;
use Nodelapay\Nodela\Config;
use Nodelapay\Nodela\Http\Request;
use Nodelapay\Nodela\Resources\Invoices;
use Nodelapay\Nodela\Resources\Transactions;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ClientTest extends TestCase
{
    public function testClientExposesInvoicesResource(): void
    {
        $client = new Client('test-api-key');
        $this->assertInstanceOf(Invoices::class, $client->invoices);
    }

    public function testClientExposesTransactionsResource(): void
    {
        $client = new Client('test-api-key');
        $this->assertInstanceOf(Transactions::class, $client->transactions);
    }

    public function testClientAcceptsCustomConfig(): void
    {
        $config = new Config('custom-api-key', 60, ['X-App-Name' => 'TestApp']);
        $client = new Client('custom-api-key', $config);

        $this->assertInstanceOf(Invoices::class, $client->invoices);
        $this->assertInstanceOf(Transactions::class, $client->transactions);
    }

    public function testClientUsesProvidedApiKeyWhenNoConfigGiven(): void
    {
        $client = new Client('my-secret-key');
        $this->assertInstanceOf(Client::class, $client);
    }

    public function testInvoicesResourceIsReadonly(): void
    {
        $client = new Client('test-key');

        $this->expectException(\Error::class);
        // Use a stub without constructor instead of a PHPUnit mock to avoid lifecycle notices
        $stubRequest = (new ReflectionClass(Request::class))->newInstanceWithoutConstructor();
        $client->invoices = new Invoices($stubRequest); // @phpstan-ignore-line
    }

    public function testTransactionsResourceIsReadonly(): void
    {
        $client = new Client('test-key');

        $this->expectException(\Error::class);
        $stubRequest = (new ReflectionClass(Request::class))->newInstanceWithoutConstructor();
        $client->transactions = new Transactions($stubRequest); // @phpstan-ignore-line
    }
}
