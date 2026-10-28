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
use Thelia\Domain\Payment\Enum\OrderPaymentPartState;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderPaymentPart;
use Thelia\Model\OrderPaymentPartQuery;
use Thelia\Model\OrderStatus;
use Thelia\Test\IntegrationTestCase;

/**
 * A payment PayZen accepts for an order whose parts a cancellation released (a gift card given back) is a charge the core must not lose from sight: it settles the released part of the provider, provided the confirmation carries the code of that module.
 *
 * Boots the shop the module is installed in, on the test database `bin/test-prepare` creates:
 *   vendor/bin/phpunit --bootstrap vendor/autoload.php vendor/thelia/modules/Payzen/Tests
 */
final class ConfirmationSourceTest extends IntegrationTestCase
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

    public function testTheConfirmationOfAReleasedPaymentPartIsAcceptedFromTheModuleThatHoldsIt(): void
    {
        $order = $this->createFixtureFactory()->order(overrides: ['statusCode' => OrderStatus::CODE_NOT_PAID, 'paymentModuleCode' => 'Payzen']);
        $this->part((int) $order->getId(), 'Cheque', '30.000000', false, 0);
        $this->part((int) $order->getId(), 'Payzen', '70.000000', true, 1);

        $this->notify((int) $order->getId());

        $primary = OrderPaymentPartQuery::create()->filterByOrderId($order->getId())->filterByIsPrimary(true)->findOne();
        self::assertSame(OrderPaymentPartState::SETTLED->value, $primary?->getState(), 'the provider charged the buyer: its released part is settled again');
    }

    private function part(int $orderId, string $moduleCode, string $amount, bool $isPrimary, int $position): void
    {
        $module = ModuleQuery::create()->findOneByCode($moduleCode);
        self::assertNotNull($module, \sprintf('The test database has no %s module.', $moduleCode));

        (new OrderPaymentPart())
            ->setOrderId($orderId)
            ->setPaymentModuleId($module->getId())
            ->setAmount($amount)
            ->setState(OrderPaymentPartState::RELEASED->value)
            ->setIsPrimary($isPrimary)
            ->setPosition($position)
            ->save();
    }

    /**
     * The notification PayZen posts to the shop, signed with the test certificate.
     */
    private function notify(int $orderId): string
    {
        $fields = [
            'vads_order_id' => (string) $orderId,
            'vads_result' => '00',
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
