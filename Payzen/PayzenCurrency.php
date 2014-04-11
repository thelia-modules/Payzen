<?php
namespace Payzen\Payzen;

class PayzenCurrency {
    var $alpha3;
    var $num;
    var $decimals;

    function __construct($alpha3, $num, $decimals = 2) {
        $this->alpha3 = $alpha3;
        $this->num = $num;
        $this->decimals = $decimals;
    }

    function convertAmountToInteger($float) {
        $coef = pow(10, $this->decimals);

        return intval(strval($float * $coef));
    }

    function convertAmountToFloat($integer) {
        $coef = pow(10, $this->decimals);

        return floatval($integer) / $coef;
    }
}