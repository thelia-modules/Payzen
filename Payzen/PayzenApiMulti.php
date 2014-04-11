<?php
namespace Payzen\Payzen;

/**
 * Class managing multi payment specific actions
 * @version 2.2
 */
class PayzenMultiApi extends PayzenApi
{

    /**
     * Constructor.
     */
    function __construct($encoding = "UTF-8")
    {
        // call parent class constructor
        parent::__construct($encoding);
    }

    /**
     * Set multi payment configuration
     * @param $total_in_cents total order amount in cents
     * @param $first_in_cents amount of the first payment in cents
     * @param $count total number of payments
     * @param $period number of days between 2 payments
     * @return boolean true on success
     */
    function setMultiPayment($total_in_cents = null, $first_in_cents = null, $count = 3, $period = 30)
    {
        $result = false;

        if (is_numeric($count) && $count > 1 && is_numeric($period) && $period > 0) {
            // Valeurs par défaut pour first et total
            $total_in_cents = ($total_in_cents === null) ? $this->get('amount') : $total_in_cents;
            $first_in_cents = ($first_in_cents === null) ? round($total_in_cents / $count) : $first_in_cents;

            // Vérification des paramètres
            if (is_numeric($total_in_cents) && $total_in_cents > $first_in_cents
                && $total_in_cents > 0 && is_numeric($first_in_cents)
                && $first_in_cents > 0
            ) {
                // Enregistrement du paramètres payment_config
                $payment_config = 'MULTI:first=' . $first_in_cents . ';count=' . $count . ';period=' . $period;
                $result = $this->set('amount', $total_in_cents);
                if ($result === true) {
                    // premier set ok, on continue
                    $result = $this->set('payment_config', $payment_config);
                }
            }
        }

        return $result;
    }
}