<?php

declare(strict_types=1);

namespace Nodelapay\Nodela\Tests\Unit\Resources;

use InvalidArgumentException;
use Nodelapay\Nodela\Http\Request;
use Nodelapay\Nodela\Http\Response;
use Nodelapay\Nodela\Resources\Invoices;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class InvoicesTest extends TestCase
{
    private MockObject&Request $mockRequest;
    private Invoices $invoices;

    protected function setUp(): void
    {
        // createMock skips the constructor, so the Config::getHedaers() bug is irrelevant here.
        $this->mockRequest = $this->createMock(Request::class);
        $this->invoices = new Invoices($this->mockRequest);
    }

    // -------------------------------------------------------------------------
    // create()
    // -------------------------------------------------------------------------

    public function testCreateCallsPostOnCorrectEndpoint(): void
    {
        $this->mockRequest
            ->expects($this->once())
            ->method('post')
            ->with('/invoices', $this->anything())
            ->willReturn(new Response(201, []));

        $this->invoices->create(['amount' => 5000, 'currency' => 'USD']);
    }

    public function testCreateReturnsResponseObject(): void
    {
        $expected = new Response(201, ['id' => 'inv_abc', 'status' => 'pending']);
        $this->mockRequest->expects($this->once())->method('post')->willReturn($expected);

        $response = $this->invoices->create(['amount' => 1000, 'currency' => 'USD']);

        $this->assertSame($expected, $response);
        $this->assertSame(201, $response->getStatusCode());
    }

    public function testCreateNormalizesLowercaseCurrencyToUppercase(): void
    {
        $this->mockRequest
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(fn ($params) => $params['currency'] === 'USD')
            )
            ->willReturn(new Response(201, []));

        $this->invoices->create(['amount' => 1000, 'currency' => 'usd']);
    }

    public function testCreateNormalizesMixedCaseCurrencyToUppercase(): void
    {
        $this->mockRequest
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(fn ($params) => $params['currency'] === 'NGN')
            )
            ->willReturn(new Response(201, []));

        $this->invoices->create(['amount' => 5000, 'currency' => 'Ngn']);
    }

    public function testCreateThrowsForUnsupportedCurrency(): void
    {
        $this->mockRequest->expects($this->never())->method('post');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported currency: "XYZ"');

        $this->invoices->create(['amount' => 1000, 'currency' => 'XYZ']);
    }

    public function testCreateThrowsForEmptyCurrency(): void
    {
        $this->mockRequest->expects($this->never())->method('post');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported currency: ""');

        $this->invoices->create(['amount' => 1000, 'currency' => '']);
    }

    public function testCreateThrowsForFakeCryptoCurrency(): void
    {
        $this->mockRequest->expects($this->never())->method('post');
        $this->expectException(InvalidArgumentException::class);

        $this->invoices->create(['amount' => 1000, 'currency' => 'FAKE']);
    }

    public function testCreateExceptionMessageListsSupportedCurrencies(): void
    {
        $this->mockRequest->expects($this->never())->method('post');

        try {
            $this->invoices->create(['amount' => 1000, 'currency' => 'BAD']);
            $this->fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('USD', $e->getMessage());
            $this->assertStringContainsString('EUR', $e->getMessage());
        }
    }

    public function testCreatePassesAmountToRequest(): void
    {
        $this->mockRequest
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(fn ($params) => $params['amount'] === 9999)
            )
            ->willReturn(new Response(201, []));

        $this->invoices->create(['amount' => 9999, 'currency' => 'GBP']);
    }

    public function testCreatePassesAllOptionalParamsToRequest(): void
    {
        $params = [
            'amount' => 10000,
            'currency' => 'EUR',
            'success_url' => 'https://example.com/success',
            'cancel_url' => 'https://example.com/cancel',
            'webhook_url' => 'https://example.com/webhook',
            'reference' => 'order_789',
            'customer' => ['name' => 'Jane Smith', 'email' => 'jane@example.com'],
            'title' => 'Order #789',
            'description' => 'Checkout payment',
        ];

        $this->mockRequest
            ->expects($this->once())
            ->method('post')
            ->with(
                '/invoices',
                $this->callback(function (array $sent) use ($params) {
                    return $sent['success_url'] === $params['success_url']
                        && $sent['cancel_url'] === $params['cancel_url']
                        && $sent['webhook_url'] === $params['webhook_url']
                        && $sent['reference'] === $params['reference']
                        && $sent['customer'] === $params['customer']
                        && $sent['title'] === $params['title']
                        && $sent['description'] === $params['description'];
                })
            )
            ->willReturn(new Response(201, []));

        $this->invoices->create($params);
    }

    #[DataProvider('supportedCurrenciesProvider')]
    public function testCreateAcceptsAllSupportedCurrencies(string $currency): void
    {
        $this->mockRequest
            ->expects($this->once())
            ->method('post')
            ->willReturn(new Response(201, []));

        $this->invoices->create(['amount' => 100, 'currency' => $currency]);
    }

    public static function supportedCurrenciesProvider(): array
    {
        return array_map(
            fn (string $c) => [$c],
            Invoices::SUPPORTED_CURRENCIES
        );
    }

    #[DataProvider('supportedCurrenciesProvider')]
    public function testCreateAcceptsLowercaseVersionsOfAllSupportedCurrencies(string $currency): void
    {
        $this->mockRequest
            ->expects($this->once())
            ->method('post')
            ->willReturn(new Response(201, []));

        $this->invoices->create(['amount' => 100, 'currency' => strtolower($currency)]);
    }

    // -------------------------------------------------------------------------
    // verify()
    // -------------------------------------------------------------------------

    public function testVerifyCallsGetOnCorrectEndpoint(): void
    {
        $invoiceId = 'inv_abc123';

        $this->mockRequest
            ->expects($this->once())
            ->method('get')
            ->with('/invoices/' . $invoiceId . '/verify')
            ->willReturn(new Response(200, []));

        $this->invoices->verify($invoiceId);
    }

    public function testVerifyReturnsResponse(): void
    {
        $expected = new Response(200, ['id' => 'inv_abc123', 'status' => 'paid']);
        $this->mockRequest->expects($this->once())->method('get')->willReturn($expected);

        $response = $this->invoices->verify('inv_abc123');

        $this->assertSame($expected, $response);
        $this->assertSame('paid', $response->getData()['status']);
    }

    public function testVerifyBuildsCorrectEndpointForDifferentIds(): void
    {
        $invoiceId = 'inv_xyz789';

        $this->mockRequest
            ->expects($this->once())
            ->method('get')
            ->with('/invoices/inv_xyz789/verify')
            ->willReturn(new Response(200, []));

        $this->invoices->verify($invoiceId);
    }

    // -------------------------------------------------------------------------
    // SUPPORTED_CURRENCIES constant
    // -------------------------------------------------------------------------

    public function testSupportedCurrenciesConstantIsNotEmpty(): void
    {
        $this->mockRequest->expects($this->never())->method('post');
        $this->assertNotEmpty(Invoices::SUPPORTED_CURRENCIES);
    }

    public function testSupportedCurrenciesContainsMajorWorldCurrencies(): void
    {
        $this->mockRequest->expects($this->never())->method('post');
        $expected = ['USD', 'EUR', 'GBP', 'JPY', 'CAD', 'AUD', 'NGN', 'ZAR', 'KES'];
        foreach ($expected as $currency) {
            $this->assertContains($currency, Invoices::SUPPORTED_CURRENCIES, "{$currency} should be supported");
        }
    }

    public function testSupportedCurrenciesAreAllUppercase(): void
    {
        $this->mockRequest->expects($this->never())->method('post');
        foreach (Invoices::SUPPORTED_CURRENCIES as $currency) {
            $this->assertSame(strtoupper($currency), $currency, "{$currency} should be uppercase");
        }
    }

    public function testSupportedCurrenciesHaveNoDuplicates(): void
    {
        $this->mockRequest->expects($this->never())->method('post');
        $unique = array_unique(Invoices::SUPPORTED_CURRENCIES);
        $this->assertCount(count($unique), Invoices::SUPPORTED_CURRENCIES);
    }

    public function testSupportedCurrenciesContainsAfricanCurrencies(): void
    {
        $this->mockRequest->expects($this->never())->method('post');
        $african = ['NGN', 'ZAR', 'KES', 'GHS', 'EGP', 'TZS', 'UGX', 'ETB'];
        foreach ($african as $currency) {
            $this->assertContains($currency, Invoices::SUPPORTED_CURRENCIES);
        }
    }
}
