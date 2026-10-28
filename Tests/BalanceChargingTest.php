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

use Payzen\Payzen;
use Payzen\Payzen\PayzenField;
use Thelia\Domain\Payment\Enum\OrderPaymentPartState;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderPaymentPart;
use Thelia\Model\OrderStatus;
use Thelia\Module\BalanceChargingPaymentModuleInterface;
use Thelia\Test\IntegrationTestCase;

/**
 * An order of 100 with a pending part of 30 (a gift card, for one) leaves a balance of 70 to PayZen: that is the amount
 * the payment form carries, never the total of the order. An order with no part is charged in full.
 *
 * Boots the shop the module is installed in, on the test database `bin/test-prepare` creates:
 *   vendor/bin/phpunit --bootstrap vendor/autoload.php vendor/thelia/modules/Payzen/Tests
 */
final class BalanceChargingTest extends IntegrationTestCase
{
    public function testThePaymentModuleDeclaresThatItChargesTheBalance(): void
    {
        self::assertInstanceOf(BalanceChargingPaymentModuleInterface::class, static::getContainer()->get('module.Payzen'));
    }

    public function testPayzenIsAskedForTheBalanceOfAnOrderWithAPart(): void
    {
        $order = $this->orderOf100WithAPartOf30();

        self::assertSame(100.0, (float) $order->getTotalAmount());
        self::assertSame('7000', (string) $this->parameters($order)['vads_amount']->getValue());
    }

    public function testPayzenIsAskedForTheWholeOrderWhenItHasNoPart(): void
    {
        self::assertSame('10000', (string) $this->parameters($this->orderOf100())['vads_amount']->getValue());
    }

    /**
     * @return array<string, PayzenField>
     */
    private function parameters(Order $order): array
    {
        $module = static::getContainer()->get('module.Payzen');
        self::assertInstanceOf(Payzen::class, $module);

        return (new \ReflectionMethod($module, 'getPayzenParameters'))->invoke($module, $order, 'SINGLE', '');
    }

    private function orderOf100(): Order
    {
        $fixtures = $this->createFixtureFactory();
        $customer = $fixtures->customer($fixtures->customerTitle());
        $fixtures->address($customer, overrides: ['label' => 'Home'])->setIsDefault(1)->save();
        $order = $fixtures->order($customer, ['statusCode' => OrderStatus::CODE_NOT_PAID, 'paymentModuleCode' => 'Payzen']);
        $fixtures->orderProduct($order, ['price' => '100.000000', 'promoPrice' => '100.000000']);

        return $order;
    }

    /**
     * The parts as the checkout writes them: the instant part first, then the primary part of the provider for the rest.
     */
    private function orderOf100WithAPartOf30(): Order
    {
        $order = $this->orderOf100();

        $this->part($order, 'Cheque', '30.000000', false, 0);
        $this->part($order, 'Payzen', '70.000000', true, 1);

        return $order;
    }

    private function part(Order $order, string $moduleCode, string $amount, bool $isPrimary, int $position): void
    {
        $module = ModuleQuery::create()->findOneByCode($moduleCode);
        self::assertNotNull($module, \sprintf('The test database has no %s module.', $moduleCode));

        (new OrderPaymentPart())
            ->setOrderId($order->getId())
            ->setPaymentModuleId($module->getId())
            ->setAmount($amount)
            ->setState(OrderPaymentPartState::PENDING->value)
            ->setIsPrimary($isPrimary)
            ->setPosition($position)
            ->save();
    }
}
