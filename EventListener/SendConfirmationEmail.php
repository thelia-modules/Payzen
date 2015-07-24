<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Payzen\EventListener;

use Payzen\Payzen;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Action\BaseAction;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\ParserInterface;
use Thelia\Log\Tlog;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Model\MessageQuery;

/**
 * Payzen payment module
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class SendConfirmationEmail extends BaseAction implements EventSubscriberInterface
{
    /**
     * @var MailerFactory
     */
    protected $mailer;
    /**
     * @var ParserInterface
     */
    protected $parser;

    public function __construct(ParserInterface $parser, MailerFactory $mailer)
    {
        $this->parser = $parser;
        $this->mailer = $mailer;
    }

    /**
     * @return \Thelia\Mailer\MailerFactory
     */
    public function getMailer()
    {
        return $this->mailer;
    }

    /**
     * Checks if we are the payment module for the order, and if the order is paid,
     * then send a confirmation email to the customer.
     *
     * @params OrderEvent $order
     */
    public function update_status(OrderEvent $event)
    {
        $payzen = new Payzen();

        if ($event->getOrder()->isPaid() && $payzen->isPaymentModuleFor($event->getOrder())) {
            $contact_email = ConfigQuery::read('store_email', false);
            $lang = Lang::getDefaultLanguage();
            $locale = $lang->getLocale();

            Tlog::getInstance()->debug("Sending confirmation email from store contact e-mail $contact_email");

            if ($contact_email) {
                $message = MessageQuery::create()
                    ->filterByName(Payzen::CONFIRMATION_MESSAGE_NAME)
                    ->findOne();

                if (false === $message) {
                    throw new \Exception(sprintf("Failed to load message '%s'.", Payzen::CONFIRMATION_MESSAGE_NAME));
                }

                $order = $event->getOrder();
                $customer = $order->getCustomer();

                $this->parser->assign('order_id', $order->getId());
                $this->parser->assign('order_ref', $order->getRef());
                $this->parser->assign('locale', $locale);

                $message
                    ->setLocale($order->getLang()->getLocale());

                $instance = \Swift_Message::newInstance()
                    ->addTo($customer->getEmail(), $customer->getFirstname()." ".$customer->getLastname())
                    ->addFrom($contact_email, ConfigQuery::read('store_name'))
                ;

                // Build subject and body
                $message->buildMessage($this->parser, $instance);

                $this->getMailer()->send($instance);

                Tlog::getInstance()->debug("Confirmation email sent to customer ".$customer->getEmail());
            }
        } else {
            Tlog::getInstance()->debug("No confirmation email sent (order not paid, or not the proper payment module).");
        }
    }

    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::ORDER_UPDATE_STATUS => array("update_status", 128)
        );
    }
}
