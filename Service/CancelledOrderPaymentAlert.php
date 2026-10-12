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

namespace Payzen\Service;

use Payzen\Payzen;
use Psr\Log\LoggerInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Order;

/**
 * Tells the shop that PayZen accepted the payment of an order that had been cancelled.
 *
 * The buyer came back from a refused or abandoned payment, the order was cancelled (its gift cards given back, its
 * stock released), then PayZen confirmed a payment for it after all: a late notification, a second attempt in the
 * same payment page. The order is not paid back to life behind the merchant's back — whatever it gave back is gone —
 * and the merchant is asked to refund the buyer.
 */
final readonly class CancelledOrderPaymentAlert
{
    public function __construct(
        private MailerFactory $mailer,
        private LoggerInterface $logger,
    ) {
    }

    public function send(Order $order, string $transactionId): void
    {
        $translator = Translator::getInstance();
        $parameters = ['%ref' => (string) $order->getRef(), '%id' => (string) $order->getId(), '%transaction' => $transactionId];

        $subject = $translator->trans('PayZen accepted a payment for the cancelled order %ref', $parameters, Payzen::MODULE_DOMAIN);
        $body = $translator->trans(
            'PayZen accepted the payment %transaction of the order %ref (ID %id), which had been cancelled. The order was left cancelled: please refund the customer from the PayZen back office.',
            $parameters,
            Payzen::MODULE_DOMAIN,
        );

        $this->logger->error($body);

        $storeEmail = (string) ConfigQuery::getStoreEmail();
        $recipients = array_fill_keys(ConfigQuery::getNotificationEmailsList(), (string) ConfigQuery::getStoreName());

        if ('' === $storeEmail || [] === $recipients) {
            return;
        }

        try {
            $this->mailer->sendSimpleEmailMessage([$storeEmail => (string) ConfigQuery::getStoreName()], $recipients, $subject, nl2br(htmlspecialchars($body)), $body);
        } catch (\Throwable $exception) {
            // The answer to PayZen must not depend on the mail server: the log line above says it all.
            $this->logger->error(\sprintf('The alert about the cancelled order %s could not be sent: %s', $order->getRef(), $exception->getMessage()));
        }
    }
}
