<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Payzen\Tests;

use Payzen\Model\PayzenConfigQuery;
use Payzen\Payzen\PayzenApi;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Test\IntegrationTestCase;

/**
 * A payment PayZen accepts for an order that was cancelled in the meantime (the buyer came back from a refused
 * payment, the order was cancelled and its gift cards given back) leaves the order cancelled, and the shop is told to
 * refund it. A payment accepted for an order waiting for it still pays it.
 *
 * Boots the shop the module is installed in, on the test database `bin/test-prepare` creates:
 *   vendor/bin/phpunit --bootstrap vendor/autoload.php vendor/thelia/modules/Payzen/Tests
 */
final class CancelledOrderPaymentTest extends IntegrationTestCase
{
    private const CERTIFICATE = '1234567890123456';

    protected function setUp(): void
    {
        parent::setUp();

        PayzenConfigQuery::set('mode', 'TEST');
        PayzenConfigQuery::set('test_certificate', self::CERTIFICATE);
    }

    protected function tearDown(): void
    {
        $_POST = [];

        parent::tearDown();
    }

    public function testAnAcceptedPaymentOfACancelledOrderLeavesItCancelled(): void
    {
        $order = $this->createFixtureFactory()->order(overrides: ['statusCode' => OrderStatus::CODE_CANCELED, 'paymentModuleCode' => 'Payzen']);

        $answer = $this->notify((int) $order->getId(), '00');

        self::assertTrue(OrderQuery::create()->findPk($order->getId())?->isCancelled(), 'the order must stay cancelled');
        self::assertStringContainsString('KO-', $answer, 'PayZen is told the payment was not taken into account');
    }

    public function testAnAcceptedPaymentOfAnOrderWaitingForItPaysIt(): void
    {
        $order = $this->createFixtureFactory()->order(overrides: ['statusCode' => OrderStatus::CODE_NOT_PAID, 'paymentModuleCode' => 'Payzen']);

        $answer = $this->notify((int) $order->getId(), '00');

        self::assertTrue(OrderQuery::create()->findPk($order->getId())?->isPaid());
        self::assertStringContainsString('OK-', $answer);
    }

    /**
     * The notification PayZen posts to the shop, signed with the test certificate.
     */
    private function notify(int $orderId, string $result): string
    {
        $fields = [
            'vads_order_id' => (string) $orderId,
            'vads_result' => $result,
            'vads_trans_id' => '123456',
            'vads_amount' => '1000',
            'vads_currency' => '978',
            'vads_ctx_mode' => 'TEST',
        ];
        $fields['signature'] = (new PayzenApi())->sign($fields, self::CERTIFICATE);
        // The controller reads the notification from $_POST, as the PayZen library does.
        $_POST = $fields;

        $response = static::$kernel->handle(Request::create('/payzen/callback', 'POST', $fields), HttpKernelInterface::SUB_REQUEST, false);

        return (string) $response->getContent();
    }
}
