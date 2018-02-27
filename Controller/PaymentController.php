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

namespace Payzen\Controller;

use Payzen\Model\PayzenConfigQuery;
use Payzen\Payzen\PayzenResponse;
use Payzen\Payzen;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Module\BasePaymentModuleController;

/**
 * Payzen payment module
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class PaymentController extends BasePaymentModuleController
{
    protected function getModuleCode()
    {
        return "Payzen";
    }

    /**
     * Process a Payzen platform request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function processPayzenRequest()
    {
        // The response code to the server
        $gateway_response_code = 'ko';

        $payzenResponse = new PayzenResponse(
            $_POST,
            PayzenConfigQuery::read('mode'),
            PayzenConfigQuery::read('test_certificate'),
            PayzenConfigQuery::read('production_certificate')
        );

        $request = $this->getRequest();
        $order_id = intval($request->get('vads_order_id'));

        $this->getLog()->addInfo($this->getTranslator()->trans("Payzen platform request received for order ID %id.", array('%id' => $order_id), Payzen::MODULE_DOMAIN));

        if (null !== $order = $this->getOrder($order_id)) {

            // Check the authenticity of the request
            if ($payzenResponse->isAuthentified()) {

                // Check payment status

                if ($payzenResponse->isAcceptedPayment()) {

                    // Payment was accepted.

                    if ($order->isPaid()) {
                        $this->getLog()->addInfo($this->getTranslator()->trans("Order ID %id is already paid.", array('%id' => $order_id), Payzen::MODULE_DOMAIN));

                        $gateway_response_code = 'payment_ok_already_done';
                    } else {
                        $this->getLog()->addInfo($this->getTranslator()->trans("Order ID %id payment was successful.", array('%id' => $order_id), Payzen::MODULE_DOMAIN));

                        // Payment OK !
                        $this->confirmPayment($order_id);

                        $gateway_response_code = 'payment_ok';
                    }
                } else {
                    if ($payzenResponse->isCancelledPayment()) {

                        // Payment was canceled.

                        $this->cancelPayment($order_id);
                    } else {

                        // Payment was not accepted.

                        $this->getLog()->addError($this->getTranslator()->trans("Order ID %id payment failed.", array('%id' => $order_id), Payzen::MODULE_DOMAIN));

                        if ($order->isPaid()) {
                            $gateway_response_code = 'payment_ko_already_done';
                        } else {
                            $gateway_response_code = 'payment_ko';
                        }
                    }
                }
            } else {
                $this->getLog()->addError($this->getTranslator()->trans("Response could not be authentified."));

                $gateway_response_code = 'auth_fail';
            }
        } else {
            $gateway_response_code = 'order_not_found';
        }

        $this->getLog()->info($this->getTranslator()->trans("Payzen platform request for order ID %id processing teminated.", array('%id' => $order_id), Payzen::MODULE_DOMAIN));

        return Response::create($payzenResponse->getOutputForGateway($gateway_response_code));
    }
}
