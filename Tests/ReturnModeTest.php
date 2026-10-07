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
use Thelia\Model\OrderStatus;
use Thelia\Test\IntegrationTestCase;

/**
 * The buyer comes back from PayZen by a plain link, without the vads_* fields: a cross-site POST would arrive
 * without the session cookie (SameSite=Lax), and the confirmation and failure pages read the order of the session.
 * The payment itself is validated by the signed server notification only (PaymentController).
 *
 * Boots the shop the module is installed in, on the test database `bin/test-prepare` creates:
 *   vendor/bin/phpunit --bootstrap vendor/autoload.php vendor/thelia/modules/Payzen/Tests
 */
final class ReturnModeTest extends IntegrationTestCase
{
    public function testTheBuyerComesBackFromPayZenWithoutTheReturnFields(): void
    {
        $fixtures = $this->createFixtureFactory();
        $customer = $fixtures->customer($fixtures->customerTitle());
        $fixtures->address($customer, overrides: ['label' => 'Home'])->setIsDefault(1)->save();
        $order = $fixtures->order($customer, ['statusCode' => OrderStatus::CODE_NOT_PAID, 'paymentModuleCode' => 'Payzen']);
        $module = static::getContainer()->get('module.Payzen');
        self::assertInstanceOf(Payzen::class, $module);

        /** @var array<string, PayzenField> $fields */
        $fields = (new \ReflectionMethod($module, 'getPayzenParameters'))->invoke($module, $order, 'SINGLE', '');

        self::assertSame('NONE', $fields['vads_return_mode']->getValue());
        self::assertStringContainsString('/checkout/confirm', (string) $fields['vads_url_success']->getValue());
    }
}
