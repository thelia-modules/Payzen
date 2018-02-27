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

namespace Payzen;

use Payzen\Model\Map\PayzenConfigTableMap;
use Payzen\Model\PayzenConfigQuery;
use Payzen\Payzen\PayzenCurrency;
use Payzen\Payzen\PayzenField;
use Payzen\Payzen\PayzenMultiApi;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Translation\Translator;
use Thelia\Install\Database;
use Thelia\Model\Base\CountryQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Model\Message;
use Thelia\Model\MessageQuery;
use Thelia\Model\ModuleImageQuery;
use Thelia\Model\Order;
use Thelia\Module\AbstractPaymentModule;

/**
 * Payzen payment module
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class Payzen extends AbstractPaymentModule
{
    /** The module domain for internationalisation */
    const MODULE_DOMAIN = "payzen";

    /**
     * The confirmation message identifier
     */
    const CONFIRMATION_MESSAGE_NAME = 'payzen_payment_confirmation';

    /** @var Translator $translator */
    protected $translator;

    protected function trans($id, $locale, $parameters = [])
    {
        if ($this->translator === null) {
            $this->translator = Translator::getInstance();
        }

        return $this->translator->trans($id, $parameters, self::MODULE_DOMAIN, $locale);
    }

    /**
     * @param ConnectionInterface|null $con
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function postActivation(ConnectionInterface $con = null)
    {
        // Once activated, create the module schema in the Thelia database.
        $database = new Database($con);

        try {
            PayzenConfigQuery::create()->findOne();
        } catch (\Exception $e) {
            $database->insertSql(null, array(
                __DIR__ . DS . 'Config'.DS.'thelia.sql' // The module schema
            ));
        }

        $languages = LangQuery::create()->find();


        if (null === MessageQuery::create()->findOneByName(self::CONFIRMATION_MESSAGE_NAME)) {
            $message = new Message();
            $message
                ->setName(self::CONFIRMATION_MESSAGE_NAME)
                ->setHtmlLayoutFileName('')
                ->setHtmlTemplateFileName(self::CONFIRMATION_MESSAGE_NAME.'.html')
                ->setTextLayoutFileName('')
                ->setTextTemplateFileName(self::CONFIRMATION_MESSAGE_NAME.'.txt')
            ;

            foreach ($languages as $language) {
                /** @var Lang $language */
                $locale = $language->getLocale();

                $message->setLocale($locale);

                $message->setTitle(
                    $this->trans('Order payment confirmation', $locale)
                );

                $message->setSubject(
                    $this->trans('Order {$order_ref} payment confirmation', $locale)
                );
            }

            $message->save();
        }

        /* Deploy the module's image */
        $module = $this->getModuleModel();

        if (ModuleImageQuery::create()->filterByModule($module)->count() == 0) {
            $this->deployImageFolder($module, sprintf('%s/images', __DIR__), $con);
        }
    }

    public function update($currentVersion, $newVersion, ConnectionInterface $con = null)
    {
        if (0 === version_compare($newVersion, '1.3.0')) {
            PayzenConfigQuery::set('send_confirmation_message_only_if_paid', false);
            PayzenConfigQuery::set('send_payment_confirmation_message', true);
        }

        parent::update($currentVersion, $newVersion, $con);
    }

    /**
     * @param ConnectionInterface|null $con
     * @param bool $deleteModuleData
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function destroy(ConnectionInterface $con = null, $deleteModuleData = false)
    {
        // Delete config table and messages if required
        if ($deleteModuleData) {
            $database = new Database($con);

            $database->execute("DROP TABLE ?", PayzenConfigTableMap::TABLE_NAME);

            MessageQuery::create()->findOneByName(self::CONFIRMATION_MESSAGE_NAME)->delete();
        }
    }

    /**
     *
     *  Method used by payment gateway.
     *
     *  If this method return a \Thelia\Core\HttpFoundation\Response instance, this response is sent to the
     *  browser.
     *
     *  In many cases, it's necessary to send a form to the payment gateway. On your response you can return this form already
     *  completed, ready to be sent
     *
     * @param  Order $order processed order
     * @return Response the HTTP response
     */
    public function pay(Order $order)
    {
        return $this->doPay($order, 'SINGLE');
    }

    /**
     * Payment gateway invocation
     *
     * @param Order $order processed order
     * @param string $payment_mode the payment mode, either 'SINGLE' ou 'MULTI'
     * @param string $payment_mean, either SDD (SEPA) or bank cards list
     * @return Response the HTTP response
     */
    protected function doPay(Order $order, $payment_mode, $payment_mean = '')
    {
        $payzen_params = $this->getPayzenParameters($order, $payment_mode, $payment_mean);

        // Convert files into standard var => value array
        $html_params = array();

        /** @var PayzenField $field */
        foreach ($payzen_params as $name => $field) {
            $html_params[$name] = $field->getValue();
        }

        // Be sure to have a valid platform URL, otherwise give up
        if (false === $platformUrl = PayzenConfigQuery::read('platform_url', false)) {
            throw new \InvalidArgumentException(Translator::getInstance()->trans("The platform URL is not defined, please check Payzen module configuration.", [], Payzen::MODULE_DOMAIN));
        }

        return $this->generateGatewayFormResponse($order, $platformUrl, $html_params);
    }

    /**
     * @return boolean true to allow usage of this payment module, false otherwise.
     */
    public function isValidPayment()
    {
        $valid = false;

        $mode = PayzenConfigQuery::read('mode', false);

        // If we're in test mode, do not display Payzen on the front office, except for allowed IP addresses.
        if ('TEST' == $mode) {
            $raw_ips = explode("\n", PayzenConfigQuery::read('allowed_ip_list', ''));

            $allowed_client_ips = array();

            foreach ($raw_ips as $ip) {
                $allowed_client_ips[] = trim($ip);
            }

            $client_ip = $this->getRequest()->getClientIp();

            $valid = in_array($client_ip, $allowed_client_ips);
        } elseif ('PRODUCTION' == $mode) {
            $valid = true;
        }

        if ($valid) {
            // Check if total order amount is in the module's limits
            $valid = $this->checkMinMaxAmount();
        }

        return $valid;
    }

    /**
     * Check if total order amount is in the module's limits
     *
     * @return bool true if the current order total is within the min and max limits
     */
    protected function checkMinMaxAmount()
    {

        // Check if total order amount is in the module's limits
        $order_total = $this->getCurrentOrderTotalAmount();

        $min_amount = PayzenConfigQuery::read('minimum_amount', 0);
        $max_amount = PayzenConfigQuery::read('maximum_amount', 0);

        return $order_total > 0 && ($min_amount <= 0 || $order_total >= $min_amount) && ($max_amount <= 0 || $order_total <= $max_amount);
    }

    /**
     * Returns the vads transaction id, that should be unique during the current day,
     * and should be 6 numeric characters between 000000 and 899999
     *
     * @return string the transaction ID
     * @throws \Exception an exception if something goes wrong.
     */
    protected function getTransactionId()
    {
        $con = Propel::getWriteConnection(PayzenConfigTableMap::DATABASE_NAME);

        $con->beginTransaction();

        try {
            $trans_id = intval(PayzenConfigQuery::read('next_transaction_id', 1));

            $next_trans_id = 1 + $trans_id;

            if ($next_trans_id > 899999) {
                $next_trans_id = 0;
            }

            PayzenConfigQuery::set('next_transaction_id', $next_trans_id);

            $con->commit();

            return sprintf("%06d", $trans_id);
        } catch (\Exception $ex) {
            $con->rollback();

            throw $ex;
        }
    }

    /**
     * Calculate the value of the vads_payment_config parameter.
     *
     * @param string $payment_config
     * @param float $orderAmount
     * @param PayzenCurrency $currency
     * @return string the value for vads_payment_config parameter
     */
    protected function getPaymentConfigValue($payment_config, $orderAmount, $currency)
    {
        if ('MULTI' == $payment_config) {
            $first    = $currency->convertAmountToInteger(($orderAmount*PayzenConfigQuery::read('multi_first_payment', 0))/100);
            $count    = PayzenConfigQuery::read('multi_number_of_payments', 4);
            $interval = PayzenConfigQuery::read('multi_payments_interval', 30);

            if ($first == 0) {
                $first = $currency->convertAmountToInteger($orderAmount / $count);
            }

            return sprintf("MULTI:first=%d;count=%d;period=%d", $first, $count, $interval);
        }

        return $payment_config;
    }

    /**
     * Create the form parameter list for the given order
     *
     * @param Order $order
     * @param string $payment_config single or multiple payment - see vads_payment_config parameter description
     * @param string $payment_mean SEPA or bank card - see vads_payment_cards parameter description
     *
     * @throws \InvalidArgumentException if an unsupported currency is used in order
     * @return array the payzen form parameters
     * @throws \Exception
     * @throws \Propel\Runtime\Exception\PropelException
     */
    protected function getPayzenParameters(Order $order, $payment_config, $payment_mean)
    {
        $payzenApi = new PayzenMultiApi();

        // Total order amount
        $amount = $order->getTotalAmount();

        /** @var  PayzenCurrency $currency */

        // Currency conversion to numeric ISO 1427 code
        if (null === $currency = $payzenApi->findCurrencyByAlphaCode($order->getCurrency()->getCode())) {
            throw new \InvalidArgumentException(Translator::getInstance()->trans(
                "Unsupported order currency: '%code'",
                array('%code' => $order->getCurrency()->getCode()),
                Payzen::MODULE_DOMAIN
            ));
        }

        // Check payment mean
        if ($payment_mean !== 'SDD') {
            $payment_mean = PayzenConfigQuery::read('allowed_cards');
        }


        $customer = $order->getCustomer();

        // Get customer lang code and locale
        if (null !== $langObj = LangQuery::create()->findPk($customer->getLang())) {
            $customer_lang = $langObj->getCode();
            $locale        = $langObj->getLocale();
        } else {
            $customer_lang = PayzenConfigQuery::read('default_language');
            $locale        = LangQuery::create()->findOneByByDefault(true)->getLocale();
        }

        $address = $customer->getDefaultAddress();

        // Customer phone (first non empty)
        $phone = $address->getPhone();
        if (empty($phone)) {
            $phone = $address->getCellphone();
        }

        // Transaction ID
        $transaction_id = $this->getTransactionId();

        $order->setTransactionRef($transaction_id)->save();

        $payzen_params = array(

            // Static configuration variables

            'vads_version'        => 'V2',
            'vads_contrib'        => 'Thelia version ' . ConfigQuery::read('thelia_version'),
            'vads_action_mode'    => 'INTERACTIVE',
            'vads_payment_config' => $this->getPaymentConfigValue($payment_config, $amount, $currency),
            'vads_page_action'    => 'PAYMENT',
            'vads_return_mode'    => 'POST',
            'vads_shop_name'      => ConfigQuery::read("store_name", ''),

            'vads_url_success'    => $this->getPaymentSuccessPageUrl($order->getId()),
            'vads_url_refused'    => $this->getPaymentFailurePageUrl($order->getId(), Translator::getInstance()->trans("Your payment has been refused", [], Payzen::MODULE_DOMAIN)),
            'vads_url_referral'   => $this->getPaymentFailurePageUrl($order->getId(), Translator::getInstance()->trans("Authorization request was rejected", [], Payzen::MODULE_DOMAIN)),
            'vads_url_cancel'     => $this->getPaymentFailurePageUrl($order->getId(), Translator::getInstance()->trans("You canceled the payment", [], Payzen::MODULE_DOMAIN)),
            'vads_url_error'      => $this->getPaymentFailurePageUrl($order->getId(), Translator::getInstance()->trans("An internal error occured", [], Payzen::MODULE_DOMAIN)),

            // User-defined configuration variables

            'vads_site_id'             => PayzenConfigQuery::read('site_id'),
            'vads_key_test'            => PayzenConfigQuery::read('test_certificate'),
            'vads_key_prod'            => PayzenConfigQuery::read('production_certificate'),
            'vads_ctx_mode'            => PayzenConfigQuery::read('mode'),
            'vads_platform_url'        => PayzenConfigQuery::read('platform_url'),
            'vads_default_language'    => PayzenConfigQuery::read('default_language'),
            'vads_available_languages' => PayzenConfigQuery::read('available_languages'),

            'vads_capture_delay'       => PayzenConfigQuery::read('banking_delay'),
            'vads_validation_mode'     => PayzenConfigQuery::read('validation_mode'),
            'vads_payment_cards'       => $payment_mean,

            'vads_redirect_enabled'          => PayzenConfigQuery::read('redirect_enabled'),
            'vads_redirect_success_timeout'  => PayzenConfigQuery::read('success_timeout'),
            'vads_redirect_success_message'  => PayzenConfigQuery::read('success_message'),
            'vads_redirect_error_timeout'    => PayzenConfigQuery::read('failure_timeout'),
            'vads_redirect_error_message'    => PayzenConfigQuery::read('failure_message'),

            // Order related configuration variables

            'vads_language'    => $customer_lang,
            'vads_order_id'    => $order->getId(), // Do not change this, as the callback use it to find the order
            'vads_currency'    => $currency->num,
            'vads_amount'      => $currency->convertAmountToInteger($amount),
            'vads_trans_id'    => $transaction_id,
            'vads_trans_date'  => gmdate("YmdHis"),

            // Activate 3D Secure ?
            'vads_threeds_mpi' => $amount >= PayzenConfigQuery::read('three_ds_minimum_order_amount', 0) ? 2 : 0,

            // Customer information

            'vads_cust_email'      => $customer->getEmail(),
            'vads_cust_id'         => $customer->getId(),
            'vads_cust_title'      => $customer->getCustomerTitle()->setLocale($locale)->getLong(),
            'vads_cust_last_name'  => $customer->getLastname(),
            'vads_cust_first_name' => $customer->getFirstname(),
            'vads_cust_address'    => trim($address->getAddress1() . ' ' . $address->getAddress2() . ' ' . $address->getAddress3()),
            'vads_cust_city'       => $address->getCity(),
            'vads_cust_zip'        => $address->getZipcode(),
            'vads_cust_country'    => CountryQuery::create()->findPk($address->getCountryId())->getIsoalpha2(),
            'vads_cust_phone'      => $phone,
        );

        foreach ($payzen_params as $payzen_parameter_name => $value) {
            $payzenApi->set($payzen_parameter_name, $value);
        }

        return $payzenApi->getRequestFields();
    }
}
