<?php
#####################################################################################################
#
#					Module pour la plateforme de paiement PayZen
#						Version : 1.1 (révision 50362)
#									########################
#					Développé pour Prestashop
#						Version : 1.5.0.x
#						Compatibilité plateforme : V2
#									########################
#					Développé par Lyra Network
#						http://www.lyra-network.com/
#						19/08/2013
#						Contact : support@payzen.eu
#
#####################################################################################################

/*
* NOTICE OF LICENSE
*
* This source file is Licensed under the Open Software License version 3.0
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/osl-3.0.php
**/

namespace Payzen\Payzen;

/**
* @package payzen
* @author Alain Dubrulle <supportvad@lyra-network.com>
* @copyright www.lyra-network.com
* PHP classes to integrate an e-commerce solution with the payment platform supported by lyra-network.
*/
use Payzen\Payzen;
use Thelia\Core\Translation\Translator;
use Payzen\Model\PayzenConfigQuery;

/**
 * Class managing parameters checking, form and signature building, response analysis and more
 * @version 2.2
 */
class PayzenApi
{
    // **************************************
    // PROPERTIES
    // **************************************
    /**
     * The fields to send to the PayZen platform
     * @var array[string]PayzenField
     * @access private
     */
    var $requestParameters = array();
    /**
     * Certificate to send in TEST mode
     * @var string
     * @access private
     */
    var $keyTest;
    /**
     * Certificate to send in PRODUCTION mode
     * @var string
     * @access private
     */
    var $keyProd;
    /**
     * Url of the payment page
     * @var string
     * @access private
     */
    var $platformUrl;
    /**
     * Set to true to send the redirect_* parameters
     * @var boolean
     * @access private
     */
    var $redirectEnabled;
    /**
     * SHA-1 authentication signature
     * @var string
     * @access private
     */
    var $signature;
    /**
     * The original data encoding.
     * @var string
     * @access private
     */
    var $encoding;

    /**
     * The list of categories for payment with bank accord. To be sent with the products detail if you use this payment mean.
     * @static
     * @var array
     * @access public
     */
    var $ACCORD_CATEGORIES = array(
        "FOOD_AND_GROCERY",
        "AUTOMOTIVE",
        "ENTERTAINMENT",
        "HOME_AND_GARDEN",
        "HOME_APPLIANCE",
        "AUCTION_AND_GROUP_BUYING",
        "FLOWERS_AND_GIFTS",
        "COMPUTER_AND_SOFTWARE",
        "HEALTH_AND_BEAUTY",
        "SERVICE_FOR_INDIVIDUAL",
        "SERVICE_FOR_BUSINESS",
        "SPORTS",
        "CLOTHING_AND_ACCESSORIES",
        "TRAVEL",
        "HOME_AUDIO_PHOTO_VIDEO",
        "TELEPHONY"
    );

    /**
     * The list of encodings supported by this API.
     * @static
     * @var array
     * @access public
     */
    var $SUPPORTED_ENCODINGS = array(
        "UTF-8",
        "ASCII",
        "Windows-1252",
        "ISO-8859-15",
        "ISO-8859-1",
        "ISO-8859-6",
        "CP1256"
    );

    // **************************************
    // CONSTRUCTOR
    // **************************************
    /**
     * Constructor.
     * Initialize request fields definitions.
     */
    function __construct($encoding = "UTF-8")
    {
        // Initialize encoding
        $this->encoding = in_array(strtoupper($encoding), $this->SUPPORTED_ENCODINGS) ? strtoupper($encoding) : "UTF-8";

        /*
         * Définition des paramètres de la requête
         */
        // Common or long regexes
        $ans = "[^<>]"; // Any character (except the dreadful "<" and ">")
        $an63 = '#^[A-Za-z0-9]{0,63}$#';
        $an255 = '#^[A-Za-z0-9]{0,255}$#';
        $ans255 = '#^' . $ans . '{0,255}$#';
        $ans127 = '#^' . $ans . '{0,127}$#';
        $supzero = '[1-9]\d*';
        $regex_payment_cfg = '#^(SINGLE|MULTI:first=\d+;count=' . $supzero
            . ';period=' . $supzero . ')$#';
        $regex_trans_date = '#^\d{4}' . '(1[0-2]|0[1-9])'
            . '(3[01]|[1-2]\d|0[1-9])' . '(2[0-3]|[0-1]\d)' . '([0-5]\d){2}$#';
        //AAAAMMJJhhmmss
        $regex_mail = '#^[^@]+@[^@]+\.\w{2,4}$#'; //TODO plus restrictif
        $regex_params = '#^([^&=]+=[^&=]*)?(&[^&=]+=[^&=]*)*$#'; //name1=value1&name2=value2...

        // Déclaration des paramètres, de leur valeurs par défaut, de leur format...
        // 		$this->_addRequestField('raw_signature', 'DEBUG Signature', '#^.+$#', false);
        $this->_addRequestField('signature', 'Signature', "#^[0-9a-f]{40}$#", true);
        $this->_addRequestField(
            'vads_action_mode',
            'Action mode',
            "#^INTERACTIVE|SILENT$#",
            true,
            11
        );
        $this->_addRequestField(
            'vads_amount',
            'Amount',
            '#^' . $supzero . '$#',
            true
        );
        $this->_addRequestField(
            'vads_available_languages',
            'Available languages',
            "#^(|[A-Za-z]{2}(;[A-Za-z]{2})*)$#",
            false,
            2
        );
        $this->_addRequestField('vads_capture_delay', 'Capture delay', "#^\d*$#");
        $this->_addRequestField('vads_contracts', 'Contracts', $ans255);
        $this->_addRequestField('vads_contrib', 'Contribution', $ans255);
        $this->_addRequestField(
            'vads_ctx_mode',
            'Mode',
            "#^TEST|PRODUCTION$#",
            true
        );
        $this->_addRequestField('vads_currency', 'Currency', "#^\d{3}$#", true, 3);
        $this->_addRequestField(
            'vads_cust_antecedents',
            'Customer history',
            "#^NONE|NO_INCIDENT|INCIDENT$#"
        );
        $this->_addRequestField('vads_cust_address', 'Customer address', $ans255);
        $this->_addRequestField(
            'vads_cust_country',
            'Customer country',
            "#^[A-Za-z]{2}$#",
            false,
            2
        );
        $this->_addRequestField(
            'vads_cust_email',
            'Customer email',
            $regex_mail,
            false,
            127
        );
        $this->_addRequestField(
            'vads_cust_id',
            'Customer id',
            $an63,
            false,
            63
        );
        $this->_addRequestField(
            'vads_cust_name',
            'Customer name',
            $ans127,
            false,
            127
        );
        $this->_addRequestField(
            'vads_cust_cell_phone',
            'Customer cell phone',
            $an63,
            false,
            63
        );
        $this->_addRequestField(
            'vads_cust_phone',
            'Customer phone',
            $an63,
            false,
            63
        );
        $this->_addRequestField(
            'vads_cust_title',
            'Customer title',
            '#^' . $ans . '{0,63}$#',
            false,
            63
        );
        $this->_addRequestField(
            'vads_cust_city',
            'Customer city',
            '#^' . $ans . '{0,63}$#',
            false,
            63
        );
        $this->_addRequestField(
            'vads_cust_state',
            'Customer state/region',
            '#^' . $ans . '{0,63}$#',
            false,
            63
        );
        $this->_addRequestField(
            'vads_cust_zip',
            'Customer zip code',
            $an63,
            false,
            63
        );
        $this->_addRequestField(
            'vads_language',
            'Language',
            "#^[A-Za-z]{2}$#",
            false,
            2
        );
        $this->_addRequestField(
            'vads_order_id',
            'Order id',
            "#^[A-za-z0-9]{0,12}$#",
            false,
            12
        );
        $this->_addRequestField('vads_order_info', 'Order info', $ans255);
        $this->_addRequestField('vads_order_info2', 'Order info 2', $ans255);
        $this->_addRequestField('vads_order_info3', 'Order info 3', $ans255);
        $this->_addRequestField(
            'vads_page_action',
            'Page action',
            "#^PAYMENT$#",
            true,
            7
        );
        $this->_addRequestField(
            'vads_payment_cards',
            'Payment cards',
            "#^([A-Za-z0-9\-_]+;)*[A-Za-z0-9\-_]*$#",
            false,
            127
        );
        $this->_addRequestField(
            'vads_payment_config',
            'Payment config',
            $regex_payment_cfg,
            true
        );
        $this->_addRequestField(
            'vads_payment_src',
            'Payment source',
            "#^$#",
            false,
            0
        );
        $this->_addRequestField(
            'vads_redirect_error_message',
            'Redirection error message',
            $ans255,
            false
        );
        $this->_addRequestField(
            'vads_redirect_error_timeout',
            'Redirection error timeout',
            $ans255,
            false
        );
        $this->_addRequestField(
            'vads_redirect_success_message',
            'Redirection success message',
            $ans255,
            false
        );
        $this->_addRequestField(
            'vads_redirect_success_timeout',
            'Redirection success timeout',
            $ans255,
            false
        );
        $this->_addRequestField(
            'vads_return_mode',
            'Return mode',
            "#^NONE|GET|POST?$#",
            false,
            4
        );
        $this->_addRequestField(
            'vads_return_get_params',
            'GET return parameters',
            $regex_params,
            false
        );
        $this->_addRequestField(
            'vads_return_post_params',
            'POST return parameters',
            $regex_params,
            false
        );
        $this->_addRequestField(
            'vads_ship_to_name',
            'Shipping name',
            '#^' . $ans . '{0,127}$#',
            false,
            127
        );
        $this->_addRequestField(
            'vads_ship_to_phone_num',
            'Shipping phone',
            $ans255,
            false,
            63
        );
        $this->_addRequestField(
            'vads_ship_to_street',
            'Shipping street',
            $ans127,
            false,
            127
        );
        $this->_addRequestField(
            'vads_ship_to_street2',
            'Shipping street (2)',
            $ans127,
            false,
            127
        );
        $this->_addRequestField(
            'vads_ship_to_state',
            'Shipping state',
            $an63,
            false,
            63
        );
        $this->_addRequestField(
            'vads_ship_to_country',
            'Shipping country',
            "#^[A-Za-z]{2}$#",
            false,
            2
        );
        $this->_addRequestField(
            'vads_ship_to_city',
            'Shipping city',
            '#^' . $ans . '{0,63}$#',
            false,
            63
        );
        $this->_addRequestField(
            'vads_ship_to_zip',
            'Shipping zip code',
            $an63,
            false,
            63
        );
        $this->_addRequestField('vads_shop_name', 'Shop name', $ans127);
        $this->_addRequestField('vads_shop_url', 'Shop url', $ans127);
        $this->_addRequestField('vads_site_id', 'Site id', "#^\d{8}$#", true, 8);
        $this->_addRequestField('vads_theme_config', 'Theme', $ans255);
        $this->_addRequestField(
            'vads_trans_date',
            'Transaction date',
            $regex_trans_date,
            true,
            14
        );
        $this->_addRequestField(
            'vads_trans_id',
            'Transaction id',
            "#^[0-8]\d{5}$#",
            true,
            6
        );
        $this->_addRequestField(
            'vads_url_success',
            'Success url',
            $ans127,
            false,
            127
        );
        $this->_addRequestField(
            'vads_url_referral',
            'Referral url',
            $ans127,
            false,
            127
        );
        $this->_addRequestField(
            'vads_url_refused',
            'Refused url',
            $ans127,
            false,
            127
        );
        $this->_addRequestField(
            'vads_url_cancel',
            'Cancel url',
            $ans127,
            false,
            127
        );
        $this->_addRequestField('vads_url_error', 'Error url', $ans127, false, 127);
        $this->_addRequestField(
            'vads_url_return',
            'Return url',
            $ans127,
            false,
            127
        );
        $this->_addRequestField('vads_user_info', 'User info', $ans255);
        $this->_addRequestField(
            'vads_validation_mode',
            'Validation mode',
            "#^[01]?$#",
            false,
            1
        );
        $this->_addRequestField(
            'vads_version',
            'Gateway version',
            "#^V2$#",
            true,
            2
        );

        // Credit Card info
        $this->_addRequestField('vads_card_number', 'Card number', "#^\d{13,19}$#");
        $this->_addRequestField('vads_cvv', 'Card verification number', "#^\d{3,4}$#");
        $this->_addRequestField('vads_expiry_year', 'Year of card expiration', "#^20[0-9]{2}$#");
        $this->_addRequestField('vads_expiry_month', 'Month of card expiration', "#^\d[0-2]{1}$#");

        // Enable / disable 3D Secure
        $this->_addRequestField('vads_threeds_mpi', 'Enable / disable 3D Secure', '#^[0-2]$#', false);

        // Declaration of parameters for Oney payment
        $this->_addRequestField('vads_cust_first_name', 'Customer first name', $an63, false, 63);
        $this->_addRequestField('vads_cust_last_name', 'Customer last name', $an63, false, 63);
        $this->_addRequestField(
            'vads_cust_status',
            'Customer status (private or company)',
            "#^PRIVATE|COMPANY$#",
            false,
            7
        );

        $this->_addRequestField('vads_ship_to_first_name', 'Shipping first name', $an63, false, 63);
        $this->_addRequestField('vads_ship_to_last_name', 'Shipping last name', $an63, false, 63);
        $this->_addRequestField(
            'vads_ship_to_status',
            'Shipping status (private or company)',
            "#^PRIVATE|COMPANY$#",
            false,
            7
        );
        $this->_addRequestField(
            'vads_ship_to_delivery_company_name',
            'Name of the delivery company',
            $ans127,
            false,
            127
        );
        $this->_addRequestField('vads_ship_to_speed', 'Speed of the shipping method', "#^STANDARD|EXPRESS$#", false, 8);
        $this->_addRequestField(
            'vads_ship_to_type',
            'Type of the shipping method',
            "#^RECLAIM_IN_SHOP|RELAY_POINT|RECLAIM_IN_STATION|PACKAGE_DELIVERY_COMPANY|ETICKET$#",
            false,
            24
        );

        $this->_addRequestField('vads_insurance_amount', 'The amount of insurance', '#^' . $supzero . '$#', false, 12);
        $this->_addRequestField('vads_tax_amount', 'The amount of tax', '#^' . $supzero . '$#', false, 12);
        $this->_addRequestField('vads_shipping_amount', 'The amount of shipping', '#^' . $supzero . '$#', false, 12);
        $this->_addRequestField('vads_nb_products', 'Number of products', '#^' . $supzero . '$#', false);

        // Set some default parameters
        $this->set('vads_version', 'V2');
        $this->set('vads_page_action', 'PAYMENT');
        $this->set('vads_action_mode', 'INTERACTIVE');
        $this->set('vads_payment_config', 'SINGLE');
        $timestamp = time();
        $this->set('vads_trans_id', $this->_generateTransId($timestamp));
        $this->set('vads_trans_date', gmdate('YmdHis', $timestamp));
    }

    /**
     * Generate a trans_id.
     * To be independent from shared/persistent counters, we use the number of 1/10seconds since midnight,
     * which has the appropriate format (000000-899999) and has great chances to be unique.
     * @return string the generated trans_id
     * @access private
     */
    function _generateTransId($timestamp)
    {
        list($usec, $sec) = explode(" ", microtime()); // microseconds, php4 compatible
        $temp = ($timestamp + $usec - strtotime('today 00:00')) * 10;
        $temp = sprintf('%06d', $temp);

        return $temp;
    }

    /**
     * Shortcut function used in constructor to build requestParameters
     * @param string $name
     * @param string $label
     * @param string $regex
     * @param boolean $required
     * @param mixed $value
     * @return boolean true on success
     * @access private
     */
    function _addRequestField(
        $name,
        $label,
        $regex,
        $required = false,
        $length = 255,
        $value = null
    ) {
        $this->requestParameters[$name] = new PayzenField($name, $label, $regex,
            $required, $length);

        if ($value !== null) {
            return $this->set($name, $value);
        } else {
            return true;
        }
    }

    // **************************************
    // INTERNATIONAL FUNCTIONS
    // **************************************

    /**
     * Returns an array of languages accepted by the PayZen payment page
     * @static
     * @return array[string]string
     */
    function getSupportedLanguages()
    {
        return array(
            'fr' => Translator::getInstance()->trans('French', [], Payzen::MODULE_DOMAIN),
            'de' => Translator::getInstance()->trans('German', [], Payzen::MODULE_DOMAIN),
            'en' => Translator::getInstance()->trans('English', [], Payzen::MODULE_DOMAIN),
            'es' => Translator::getInstance()->trans('Spanish', [], Payzen::MODULE_DOMAIN),
            'zh' => Translator::getInstance()->trans('Chinese', [], Payzen::MODULE_DOMAIN),
            'it' => Translator::getInstance()->trans('Italian', [], Payzen::MODULE_DOMAIN),
            'ja' => Translator::getInstance()->trans('Japanese', [], Payzen::MODULE_DOMAIN),
            'pt' => Translator::getInstance()->trans('Portuguese', [], Payzen::MODULE_DOMAIN),
            'nl' => Translator::getInstance()->trans('Dutch', [], Payzen::MODULE_DOMAIN),
            'sv' => Translator::getInstance()->trans('Swedish', [], Payzen::MODULE_DOMAIN)
        );
    }

    /**
     * Returns true if the entered language is supported
     * @static
     * @param string $lang
     * @return boolean
     */
    function isSupportedLanguage($lang)
    {
        foreach ($this->getSupportedLanguages() as $code => $label) {
            if ($code == strtolower($lang)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return the list of currencies recognized by the PayZen platform
     * @static
     * @return array[int]PayzenCurrency
     */
    function getSupportedCurrencies()
    {
        $currencies = array(
            array('ARS', 32, 2),
            array('AUD', 36, 2),
            array('KHR', 116, 0),
            array('CAD', 124, 2),
            array('CNY', 156, 1),
            array('HRK', 191, 2),
            array('CZK', 203, 2),
            array('DKK', 208, 2),
            array('EKK', 233, 2),
            array('HKD', 344, 2),
            array('HUF', 348, 2),
            array('ISK', 352, 0),
            array('IDR', 360, 0),
            array('JPY', 392, 0),
            array('KRW', 410, 0),
            array('LVL', 428, 2),
            array('LTL', 440, 2),
            array('MYR', 458, 2),
            array('MXN', 484, 2),
            array('NZD', 554, 2),
            array('NOK', 578, 2),
            array('PHP', 608, 2),
            array('RUB', 643, 2),
            array('SGD', 702, 2),
            array('ZAR', 710, 2),
            array('SEK', 752, 2),
            array('CHF', 756, 2),
            array('THB', 764, 2),
            array('GBP', 826, 2),
            array('USD', 840, 2),
            array('TWD', 901, 1),
            array('RON', 946, 2),
            array('TRY', 949, 2),
            array('XOF', 952, 0),
            array('BGN', 975, 2),
            array('EUR', 978, 2),
            array('PLN', 985, 2),
            array('BRL', 986, 2)
        );

        $payzenCurrencies = array();

        foreach ($currencies as $currency) {
            $payzenCurrencies[] = new PayzenCurrency($currency[0], $currency[1], $currency[2]);
        }

        return $payzenCurrencies;
    }

    /**
     * Return a currency from its iso 3-letters code
     * @static
     * @param string $alpha3
     * @return PayzenCurrency
     */
    function findCurrencyByAlphaCode($alpha3)
    {
        $list = $this->getSupportedCurrencies();

        foreach ($list as $currency) {
            /** @var PayzenCurrency $currency */
            if ($currency->alpha3 == $alpha3) {
                return $currency;
            }
        }
        return null;
    }

    /**
     * Returns a currency form its iso numeric code
     * @static
     * @param int $num
     * @return PayzenCurrency
     */
    function findCurrencyByNumCode($numeric)
    {
        $list = $this->getSupportedCurrencies();
        foreach ($list as $currency) {
            /** @var PayzenCurrency $currency */
            if ($currency->num == $numeric) {
                return $currency;
            }
        }
        return null;
    }

    /**
     * Returns a currency numeric code from its 3-letters code
     * @static
     * @param string $alpha3
     * @return int
     */
    function getCurrencyNumCode($alpha3)
    {
        $currency = $this->findCurrencyByAlphaCode($alpha3);
        return is_a($currency, 'PayzenCurrency') ? $currency->num : null;
    }

    /**
     * Returns an array of card types accepted by the PayZen payment platform
     * @static
     * @return array[string]string
     */
    function getSupportedCardTypes()
    {
        return array(
            'CB' => 'CB',
            'VISA' => 'Visa',
            'VISA_ELECTRON' => 'Visa Electron',
            'MASTERCARD' => 'Mastercard',
            'MAESTRO' => 'Maestro',
            'AMEX' => 'American Express',
            'E-CARTEBLEUE' => 'E-Carte bleue'
        );
    }

    // **************************************
    // GETTERS/SETTERS
    // **************************************
    /**
     * Shortcut for setting multiple values with one array
     * @param array [string]mixed $parameters
     * @return boolean true on success
     */
    function setFromArray($parameters)
    {
        $ok = true;
        foreach ($parameters as $name => $value) {
            $ok &= $this->set($name, $value);
        }
        return $ok;
    }

    /**
     * General getter.
     * Retrieve an api variable from its name. Automatically add 'vads_' to the name if necessary.
     * Example : <code><?php $siteId = $api->get('site_id'); ?></code>
     * @param string $name
     * @return mixed null if $name was not recognised
     */
    function get($name)
    {
        if (!$name || !is_string($name)) {
            return null;
        }

        // V1/shortcut notation compatibility
        $name = (substr($name, 0, 5) != 'vads_') ? 'vads_' . $name : $name;

        if ($name == 'vads_key_test') {
            return $this->keyTest;
        } elseif ($name == 'vads_key_prod') {
            return $this->keyProd;
        } elseif ($name == 'vads_platform_url') {
            return $this->platformUrl;
        } elseif ($name == 'vads_redirect_enabled') {
            return $this->redirectEnabled;
        } elseif (array_key_exists($name, $this->requestParameters)) {
            return $this->requestParameters[$name]->getValue();
        } else {
            return null;
        }
    }

    /**
     * General setter.
     * Set an api variable with its name and the provided value. Automatically add 'vads_' to the name if necessary.
     * Example : <code><?php $api->set('site_id', '12345678'); ?></code>
     * @param string $name
     * @param mixed $value
     * @return boolean true on success
     */
    function set($name, $value)
    {
        if (!$name || !is_string($name)) {
            return false;
        }

        // V1/shortcut notation compatibility
        $name = (substr($name, 0, 5) != 'vads_') ? 'vads_' . $name : $name;

        // Convert the parameters if they are not encoded in utf8
        if ($this->encoding !== "UTF-8") {
            $value = iconv($this->encoding, "UTF-8", $value);
        }

        // Search appropriate setter
        if ($name == 'vads_key_test') {
            return $this->setCertificate($value, 'TEST');
        } elseif ($name == 'vads_key_prod') {
            return $this->setCertificate($value, 'PRODUCTION');
        } elseif ($name == 'vads_platform_url') {
            return $this->setPlatformUrl($value);
        } elseif ($name == 'vads_redirect_enabled') {
            return $this->setRedirectEnabled($value);
        } elseif (array_key_exists($name, $this->requestParameters)) {
            return $this->requestParameters[$name]->setValue($value);
        } else {
            return false;
        }
    }

    /**
     * Set target url of the payment form
     * @param string $url
     * @return boolean
     */
    function setPlatformUrl($url)
    {
        if (!preg_match('#https?://([^/]+/)+#', $url)) {
            return false;
        }
        $this->platformUrl = $url;
        return true;
    }

    /**
     * Enable/disable redirect_* parameters
     * @param mixed $enabled false, '0', a null or negative integer or 'false' to disable
     * @return boolean
     */
    function setRedirectEnabled($enabled)
    {
        $this->redirectEnabled = !(!$enabled || $enabled == '0'
            || strtolower($enabled) == 'false');
        return true;
    }

    /**
     * Set TEST or PRODUCTION certificate
     * @param string $key
     * @param string $mode
     * @return boolean true if the certificate could be set
     */
    function setCertificate($key, $mode)
    {
        // Check format
        if (!preg_match('#\d{16}#', $key)) {
            return false;
        }

        if ($mode == 'TEST') {
            $this->keyTest = $key;
        } elseif ($mode == 'PRODUCTION') {
            $this->keyProd = $key;
        } else {
            return false;
        }
        return true;
    }

    /**
     * Add product infos as request parameters.
     * @param string $label
     * @param int $amount
     * @param int $qty
     * @param string $ref
     * @param string $type
     * @return boolean true if product infos are set correctly
     */
    function addProductRequestField($label, $amount, $qty, $ref, $type)
    {
        $index = $this->get("nb_products") ? $this->get("nb_products") : 0;

        $ok = true;

        // Add product infos as request parameters
        $ok &= $this->_addRequestField(
            "vads_product_label" . $index,
            "Product label",
            '#^[^<>"+-]{0,255}$#',
            false,
            255,
            $label
        );
        $ok &= $this->_addRequestField(
            "vads_product_amount" . $index,
            "Product amount",
            '#^[1-9]\d*$#',
            false,
            12,
            $amount
        );
        $ok &= $this->_addRequestField(
            "vads_product_qty" . $index,
            "Product quantity",
            '#^[1-9]\d*$#',
            false,
            255,
            $qty
        );
        $ok &= $this->_addRequestField(
            "vads_product_ref" . $index,
            "Product reference",
            '#^[A-Za-z0-9]{0,64}$#',
            false,
            64,
            $ref
        );
        $ok &= $this->_addRequestField(
            "vads_product_type" . $index,
            "Product type",
            "#^" . implode("|", $this->ACCORD_CATEGORIES) . "$#",
            false,
            30,
            $type
        );

        // Increment the number of products
        $ok &= $this->set("nb_products", $index + 1);

        return $ok;
    }

    /**
     * Add extra info as a request parameter.
     * @param string $key
     * @param string $value
     * @return boolean true if extra info is set correctly
     */
    function addExtInfoRequestField($key, $value)
    {
        return $this->_addRequestField(
            "vads_ext_info_" . $key,
            "Extra info " . $key,
            '#^.{0,255}$#',
            false,
            255,
            $value
        );
    }


    /**
     * Return certificate according to current mode, false if mode was not set
     * @return string|boolean
     */
    function getCertificate()
    {
        switch ($this->requestParameters['vads_ctx_mode']
            ->getValue()) {
            case 'TEST':
                return $this->keyTest;
                break;

            case 'PRODUCTION':
                return $this->keyProd;
                break;

            default:
                return false;
                break;
        }
    }

    /**
     * Generate signature from a list of PayzenField
     * @param array [string]PayzenField $fields
     * @return string
     * @access private
     */
    function _generateSignatureFromFields($fields = null, $hashed = true)
    {
        $params = array();
        $fields = ($fields !== null) ? $fields : $this->requestParameters;
        foreach ($fields as $field) {
            if ($field->isRequired() || $field->isFilled()) {
                $params[$field->getName()] = $field->getValue();
            }
        }
        return $this->sign($params, $this->getCertificate(), $hashed);
    }

    /**
     * Public static method to compute a PayZen signature. Parameters must be in utf-8.
     * @param array [string]string $parameters payment gateway request/response parameters
     * @param string $key shop certificate
     * @param boolean $hashed set to false to get the raw, unhashed signature
     * @access public
     * @static
     */
    function sign($parameters, $key, $hashed = true)
    {
        $signContent = "";
        ksort($parameters);
        foreach ($parameters as $name => $value) {
            if (substr($name, 0, 5) == 'vads_') {
                $signContent .= $value . '+';
            }
        }
        $signContent .= $key;

        if(PayzenConfigQuery::read('signature_algorythm', 'HMAC') == 'HMAC') {
            $sign = $hashed ? base64_encode(hash_hmac('sha256', $signContent, $key, true)) : $signContent;
        } else {
            $sign = $hashed ? sha1($signContent) : $signContent;
        }
        return $sign;
    }

    // **************************************
    // REQUEST PREPARATION FUNCTIONS
    // **************************************
    /**
     * Unset the value of optionnal fields if they are unvalid
     */
    function clearInvalidOptionnalFields()
    {
        $fields = $this->getRequestFields();
        foreach ($fields as $field) {
            if (!$field->isValid() && !$field->isRequired()) {
                $field->setValue(null);
            }
        }
    }

    /**
     * Check all payment fields
     * @param array $errors will be filled with the name of invalid fields
     * @return boolean
     */
    function isRequestReady(&$errors = null)
    {
        $errors = is_array($errors) ? $errors : array();
        $fields = $this->getRequestFields();
        foreach ($fields as $field) {
            if (!$field->isValid()) {
                $errors[] = $field->getName();
            }
        }
        return sizeof($errors) == 0;
    }

    /**
     * Return the list of fields to send to the payment gateway
     * @return array[string]PayzenField a list of PayzenField or false if a parameter was invalid
     * @see PayzenField
     */
    function getRequestFields()
    {
        $fields = $this->requestParameters;

        // Filter redirect_parameters if redirect is disabled
        if (!$this->redirectEnabled) {
            $redirectFields = array(
                'vads_redirect_success_timeout',
                'vads_redirect_success_message',
                'vads_redirect_error_timeout',
                'vads_redirect_error_message'
            );
            foreach ($redirectFields as $fieldName) {
                unset($fields[$fieldName]);
            }
        }

        foreach ($fields as $fieldName => $field) {
            if (!$field->isFilled() && !$field->isRequired()) {
                unset($fields[$fieldName]);
            }
        }

        // Compute signature
        $fields['signature']->setValue($this->_generateSignatureFromFields($fields));

        // Return the list of fields
        return $fields;
    }

    /**
     * Return the url of the payment page with urlencoded parameters (GET-like url)
     * @return boolean|string
     */
    function getRequestUrl()
    {
        $fields = $this->getRequestFields();

        $url = $this->platformUrl . '?';
        foreach ($fields as $field) {
            if ($field->isFilled()) {
                $url .= $field->getName() . '=' . rawurlencode($field->getValue())
                    . '&';
            }
        }
        $url = substr($url, 0, -1); // remove last &
        return $url;
    }

    /**
     * Return the html form to send to the payment gateway
     * @param string $enteteAdd
     * @param string $inputType
     * @param string $buttonValue
     * @param string $buttonAdd
     * @param string $buttonType
     * @return string
     */
    function getRequestHtmlForm(
        $enteteAdd = '',
        $inputType = 'hidden',
        $buttonValue = 'Aller sur la plateforme de paiement',
        $buttonAdd = '',
        $buttonType = 'submit'
    ) {

        $html = "";
        $html .= '<form action="' . $this->platformUrl . '" method="POST" '
            . $enteteAdd . '>';
        $html .= "\n";
        $html .= $this->getRequestFieldsHtml('type="' . $inputType . '"');
        $html .= '<input type="' . $buttonType . '" value="' . $buttonValue . '" '
            . $buttonAdd . '/>';
        $html .= "\n";
        $html .= '</form>';
        return $html;
    }

    /**
     * Return the html code of the form fields to send to the payment page
     * @param string $inputAttributes
     * @return string
     */
    function getRequestFieldsHtml($inputAttributes = 'type="hidden"')
    {
        $fields = $this->getRequestFields();

        $html = '';
        $format = '<input name="%s" value="%s" ' . $inputAttributes . "/>\n";
        foreach ($fields as $field) {
            if ($field->isFilled()) {
                // Convert special chars to HTML entities to avoid data troncation
                $value = htmlspecialchars($field->getValue(), ENT_QUOTES, 'UTF-8');

                $html .= sprintf($format, $field->getName(), $value);
            }
        }
        return $html;
    }

    /**
     * Return the html fields to send to the payment page as a key/value array
     * @return array[string][string]
     */
    function getRequestFieldsArray()
    {
        $fields = $this->getRequestFields();

        $result = array();
        foreach ($fields as $field) {
            if ($field->isFilled()) {
                // Convert special chars to HTML entities to avoid data troncation
                $result[$field->getName()] = htmlspecialchars($field->getValue(), ENT_QUOTES, 'UTF-8');
            }
        }

        return $result;
    }

    /**
     * PHP is not yet a sufficiently advanced technology to be indistinguishable from magic...
     * so don't use magic_quotes, they mess up with the gateway response analysis.
     *
     * @param array $potentiallyMagicallyQuotedData
     */
    function uncharm($potentiallyMagicallyQuotedData)
    {
        if (get_magic_quotes_gpc()) {
            $sane = array();
            foreach ($potentiallyMagicallyQuotedData as $k => $v) {
                $saneKey = stripslashes($k);
                $saneValue = is_array($v) ? $this->uncharm($v) : stripslashes($v);
                $sane[$saneKey] = $saneValue;
            }
        } else {
            $sane = $potentiallyMagicallyQuotedData;
        }
        return $sane;
    }
}