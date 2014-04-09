<?php
namespace Payzen\Payzen;

/**
 * Class representing the result of a transaction (sent by the check url or by the client return)
 */
class PayzenResponse {
    /**
     * Raw response parameters array
     * @var array
     * @access private
     */
    var $raw_response = array();
    /**
     * Certificate used to check the signature
     * @see PayzenApi::sign
     * @var boolean
     * @access private
     */
    var $certificate;
    /**
     * Value of vads_result
     * @var string
     * @access private
     */
    var $code;
    /**
     * Translation of $code (vads_result)
     * @var string
     * @access private
     */
    var $message;
    /**
     * Value of vads_extra_result
     * @var string
     * @access private
     */
    var $extraCode;
    /**
     * Translation of $extraCode (vads_extra_result)
     * @var string
     * @access private
     */
    var $extraMessage;
    /**
     * Value of vads_auth_result
     * @var string
     * @access private
     */
    var $authCode;
    /**
     * Translation of $authCode (vads_auth_result)
     * @var string
     * @access private
     */
    var $authMessage;
    /**
     * Value of vads_warranty_result
     * @var string
     * @access private
     */
    var $warrantyCode;
    /**
     * Translation of $warrantyCode (vads_warranty_result)
     * @var string
     * @access private
     */
    var $warrantyMessage;
    /**
     * Internal reference to PayzenApi for using util methods
     * @var PayzenApi
     * @access private
     */
    var $api;

    /**
     * Associative array containing human-readable translations of response codes. Initialized to french translations.
     * @var array
     * @access private
     */
    var $translation = array(
        'no_code' => '',
        'no_translation' => '',
        'results' => array(
            '00' => 'Paiement réalisé avec succès',
            '02' => 'Le commerçant doit contacter la banque du porteur',
            '05' => 'Paiement refusé',
            '17' => 'Annulation client',
            '30' => 'Erreur de format de la requête',
            '96' => 'Erreur technique lors du paiement'),
        'extra_results_default' => array(
            'empty' => 'Pas de contrôle effectué',
            '00' => 'Tous les contrôles se sont déroulés avec succès',
            '02' => 'La carte a dépassé l’encours autorisé',
            '03' => 'La carte appartient à la liste grise du commerçant',
            '04' => 'Le pays d’émission de la carte appartient à la liste grise du commerçant',
            '05' => 'L’adresse IP appartient à la liste grise du commerçant',
            '06' => 'Le code BIN appartient à la liste grise du commerçant',
            '07' => 'Détection d\'une e-carte bleue',
            '08' => 'Détection d\'une carte commerciale nationale',
            '09' => 'Détection d\'une carte commerciale étrangère',
            '14' => 'La carte est une carte à autorisation systématique',
            '20' => 'Aucun pays ne correspond (pays IP, pays carte, pays client)',
            '99' => 'Problème technique rencontré par le serveur lors du traitement d’un des contrôles locaux'),
        'extra_results_30' => array(
            '00' => 'signature',
            '01' => 'version',
            '02' => 'merchant_site_id',
            '03' => 'transaction_id',
            '04' => 'date',
            '05' => 'validation_mode',
            '06' => 'capture_delay',
            '07' => 'config',
            '08' => 'payment_cards',
            '09' => 'amount',
            '10' => 'currency',
            '11' => 'ctx_mode',
            '12' => 'language',
            '13' => 'order_id',
            '14' => 'order_info',
            '15' => 'cust_email',
            '16' => 'cust_id',
            '17' => 'cust_title',
            '18' => 'cust_name',
            '19' => 'cust_address',
            '20' => 'cust_zip',
            '21' => 'cust_city',
            '22' => 'cust_country',
            '23' => 'cust_phone',
            '24' => 'url_success',
            '25' => 'url_refused',
            '26' => 'url_referral',
            '27' => 'url_cancel',
            '28' => 'url_return',
            '29' => 'url_error',
            '30' => 'identifier',
            '31' => 'contrib',
            '32' => 'theme_config',
            '34' => 'redirect_success_timeout',
            '35' => 'redirect_success_message',
            '36' => 'redirect_error_timeout',
            '37' => 'redirect_error_message',
            '38' => 'return_post_params',
            '39' => 'return_get_params',
            '40' => 'card_number',
            '41' => 'expiry_month',
            '42' => 'expiry_year',
            '43' => 'card_cvv',
            '44' => 'card_info',
            '45' => 'card_options',
            '46' => 'page_action',
            '47' => 'action_mode',
            '48' => 'return_mode',
            '50' => 'secure_mpi',
            '51' => 'secure_enrolled',
            '52' => 'secure_cavv',
            '53' => 'secure_eci',
            '54' => 'secure_xid',
            '55' => 'secure_cavv_alg',
            '56' => 'secure_status',
            '60' => 'payment_src',
            '61' => 'user_info',
            '62' => 'contracts',
            '70' => 'empty_params',
            '99' => 'other'),
        'auth_results' => array(
            '00' => 'transaction approuvée ou traitée avec succès',
            '02' => 'contacter l’émetteur de carte',
            '03' => 'accepteur invalide',
            '04' => 'conserver la carte',
            '05' => 'ne pas honorer',
            '07' => 'conserver la carte, conditions spéciales',
            '08' => 'approuver après identification',
            '12' => 'transaction invalide',
            '13' => 'montant invalide',
            '14' => 'numéro de porteur invalide',
            '30' => 'erreur de format',
            '31' => 'identifiant de l’organisme acquéreur inconnu',
            '33' => 'date de validité de la carte dépassée',
            '34' => 'suspicion de fraude',
            '41' => 'carte perdue',
            '43' => 'carte volée',
            '51' => 'provision insuffisante ou crédit dépassé',
            '54' => 'date de validité de la carte dépassée',
            '56' => 'carte absente du fichier',
            '57' => 'transaction non permise à ce porteur',
            '58' => 'transaction interdite au terminal',
            '59' => 'suspicion de fraude',
            '60' => 'l’accepteur de carte doit contacter l’acquéreur',
            '61' => 'montant de retrait hors limite',
            '63' => 'règles de sécurité non respectées',
            '68' => 'réponse non parvenue ou reçue trop tard',
            '90' => 'arrêt momentané du système',
            '91' => 'émetteur de cartes inaccessible',
            '96' => 'mauvais fonctionnement du système',
            '94' => 'transaction dupliquée',
            '97' => 'échéance de la temporisation de surveillance globale',
            '98' => 'serveur indisponible routage réseau demandé à nouveau',
            '99' => 'incident domaine initiateur'),
        'warranty_results' => array(
            'YES' => 'Le paiement est garanti',
            'NO' => 'Le paiement n\'est pas garanti',
            'UNKNOWN' => 'Suite à une erreur technique, le paiment ne peut pas être garanti'));

    /**
     * Constructor for PayzenResponse class. Prepare to analyse check url or return url call.
     * @param array[string]string $parameters $_REQUEST by default
     * @param string $ctx_mode
     * @param string $key_test
     * @param string $key_prod
     * @param string $encoding
     */
    function PayzenResponse($parameters = null, $ctx_mode = null, $key_test = null, $key_prod = null) {

        $this->api = new PayzenApi(); // Use default API encoding (UTF-8) since the payment platform returns UTF-8 data

        if(is_null($parameters)) {
            $parameters = $_REQUEST;
        }
        $parameters = $this->api->uncharm($parameters);

        // Load site credentials if provided
        if (!is_null($ctx_mode)) {
            $this->api->set('vads_ctx_mode', $ctx_mode);
        }
        if (!is_null($key_test)) {
            $this->api->set('vads_key_test', $key_test);
        }
        if (!is_null($key_prod)) {
            $this->api->set('vads_key_prod', $key_prod);
        }

        $this->load($parameters, $this->api->getCertificate());
    }

    /**
     * Load response codes and translations from a parameter array.
     * @param array[string]string $raw
     * @param boolean $authentified
     */
    function load($raw, $certificate) {
        $this->raw_response = is_array($raw) ? $raw : array();
        $this->certificate = $certificate;

        // Get codes
        $code = $this->_findInArray('vads_result', $raw, null);
        $extraCode = $this->_findInArray('vads_extra_result', $raw, null);
        $authCode = $this->_findInArray('vads_auth_result', $raw, null);
        $warrantyCode = $this->_findInArray('vads_warranty_code', $raw, null);

        // Common translations
        $noCode = $this->translation['no_code'];
        $noTrans = $this->translation['no_translation'];

        // Result and extra result
        if ($code == null) {
            $message = $noCode;
            $extraMessage = ($extraCode == null) ? $noCode : $noTrans;
        } else {
            $message = $this->_findInArray($code, $this->translation['results'],
                $noTrans);

            if ($extraCode == null) {
                $extraMessage = $noCode;
            } elseif ($code == 30) {
                $extraMessage = $this->_findInArray($extraCode,
                    $this->translation['extra_results_30'], $noTrans);
            } else {
                $extraMessage = $this->_findInArray($extraCode,
                    $this->translation['extra_results_default'], $noTrans);
            }
        }

        // auth_result
        if ($authCode == null) {
            $authMessage = $noCode;
        } else {
            $authMessage = $this->_findInArray($authCode,
                $this->translation['auth_results'], $noTrans);
        }

        // warranty_result
        if ($warrantyCode == null) {
            $warrantyMessage = $noCode;
        } else {
            $warrantyMessage = $this->_findInArray($warrantyCode,
                $this->translations['warranty_results'], $noTrans);
        }

        $this->code = $code;
        $this->message = $message;
        $this->authCode = $authCode;
        $this->authMessage = $authMessage;
        $this->extraCode = $extraCode;
        $this->extraMessage = $extraMessage;
        $this->warrantyCode = $warrantyCode;
        $this->warrantyMessage = $warrantyMessage;
    }

    /**
     * Check response signature
     * @return boolean
     */
    function isAuthentified() {
        return $this->api->sign($this->raw_response, $this->certificate)
        == $this->getSignature();
    }

    /**
     * Return the signature computed from the received parameters, for log/debug purposes.
     * @param boolean $hashed apply sha1, false by default
     * @return string
     */
    function getComputedSignature($hashed = false) {
        return $this->api->sign($this->raw_response, $this->certificate, $hashed);
    }

    /**
     * Check if the payment was successful (waiting confirmation or captured)
     * @return boolean
     */
    function isAcceptedPayment() {
        return $this->code == '00';
    }

    /**
     * Check if the payment is waiting confirmation (successful but the amount has not been transfered and is not yet guaranteed)
     * @return boolean
     */
    function isPendingPayment() {
        return $this->get('auth_mode') == 'MARK';
    }

    /**
     * Check if the payment process was interrupted by the client
     * @return boolean
     */
    function isCancelledPayment() {
        return $this->code == '17';
    }

    /**
     * Return the value of a response parameter.
     * @param string $name
     * @return string
     */
    function get($name) {
        // Manage shortcut notations by adding 'vads_'
        $name = (substr($name, 0, 5) != 'vads_') ? 'vads_' . $name : $name;

        return @$this->raw_response[$name];
    }

    /**
     * Shortcut for getting ext_info_* fields.
     * @param string $key
     * @return string
     */
    function getExtInfo($key) {
        return $this->get("ext_info_$key");
    }

    /**
     * Returns the expected signature received from gateway.
     * @return string
     */
    function getSignature() {
        return @$this->raw_response['signature'];
    }

    /**
     * Return the paid amount converted from cents (or currency equivalent) to a decimal value
     * @return float
     */
    function getFloatAmount() {
        $currency = $this->api->findCurrencyByNumCode($this->get('currency'));
        return $currency->convertAmountToFloat($this->get('amount'));
    }

    /**
     * Return a short description of the payment result, useful for logging
     * @return string
     */
    function getLogString() {
        $log = $this->code . ' : ' . $this->message;
        if ($this->code == '30') {
            $log .= ' (' . $this->extraCode . ' : ' . $this->extraMessage . ')';
        }
        return $log;
    }

    /**
     * Return a formatted string to output as a response to the check url call
     * @param string $case shortcut code for current situations. Most useful : payment_ok, payment_ko, auth_fail
     * @param string $extraMessage some extra information to output to the payment gateway
     * @return string
     */
    function getOutputForGateway($case = '', $extraMessage = '', $originalEncoding="UTF-8") {
        $success = false;
        $message = '';

        // Messages prédéfinis selon le cas
        $cases = array(
            'payment_ok' => array(true, 'Paiement valide traite'),
            'payment_ko' => array(true, 'Paiement invalide traite'),
            'payment_ok_already_done' => array(true, 'Paiement valide traite, deja enregistre'),
            'payment_ko_already_done' => array(true, 'Paiement invalide traite, deja enregistre'),
            'order_not_found' => array(false, 'Impossible de retrouver la commande'),
            'payment_ko_on_order_ok' => array(false, 'Code paiement invalide recu pour une commande deja validee'),
            'auth_fail' => array(false, 'Echec authentification'),
            'ok' => array(true, ''),
            'ko' => array(false, ''));

        if (array_key_exists($case, $cases)) {
            $success = $cases[$case][0];
            $message = $cases[$case][1];
        }

        $message .= ' ' . $extraMessage;
        $message = str_replace("\n", '', $message);

        // Set original CMS encoding to convert if necessary response to send to platform
        $encoding = in_array(strtoupper($originalEncoding), $this->api->SUPPORTED_ENCODINGS) ? strtoupper($originalEncoding) : "UTF-8";

        if($encoding !== "UTF-8") {
            $message = iconv($encoding, "UTF-8", $message);
        }

        $response = '';
        $response .= '<span style="display:none">';
        $response .= $success ? "OK-" : "KO-";
        $response .= $this->get('trans_id');
        $response .= ($message === ' ') ? "\n" : "=$message\n";
        $response .= '</span>';
        return $response;
    }

    /**
     * Private shortcut function
     * @param string $value
     * @param array[string]string $translations
     * @param string $defaultTransation
     * @access private
     */
    function _findInArray($key, $array, $default) {
        if (is_array($array) && array_key_exists($key, $array)) {
            return $array[$key];
        }
        return $default;
    }
}
