<?php
namespace Payzen\Payzen;

class PayzenCurrency
{
    public $alpha3;
    public $num;
    public $decimals;

    public function __construct($alpha3, $num, $decimals = 2)
    {
        $this->alpha3 = $alpha3;
        $this->num = $num;
        $this->decimals = $decimals;
    }

    public function convertAmountToInteger($float)
    {
        $coef = pow(10, $this->decimals);

        return intval(strval($float * $coef));
    }

    public function convertAmountToFloat($integer)
    {
        $coef = pow(10, $this->decimals);

        return floatval($integer) / $coef;
    }
}
