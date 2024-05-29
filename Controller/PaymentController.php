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

use Exception;
use Payzen\Model\PayzenConfigQuery;
use Payzen\Payzen\PayzenResponse;
use Payzen\Payzen;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Translation\Translator;
use Thelia\Module\BasePaymentModuleController;

#[Route('/payzen', name: 'payzen_payment_')]
class PaymentController extends BasePaymentModuleController
{
    protected function getModuleCode(): string
    {
        return "Payzen";
    }

    /**
     * @throws PropelException
     * @throws Exception
     */
    #[Route('/callback', name: 'process', methods: ['POST'])]
    public function processPayzenRequest(Request $request, EventDispatcherInterface $eventDispatcher): Response
    {
        // The response code to the server
        $gateway_response_code = 'ko';

        $payzenResponse = new PayzenResponse(
            $_POST,
            PayzenConfigQuery::read('mode'),
            PayzenConfigQuery::read('test_certificate'),
            PayzenConfigQuery::read('production_certificate')
        );

        $order_id = (int)$request->get('vads_order_id');

        $this->getLog()->addInfo(Translator::getInstance()->trans("Payzen platform request received for order ID %id.", array('%id' => $order_id), Payzen::MODULE_DOMAIN));

        if (null !== $order = $this->getOrder($order_id)) {
            // Check the authenticity of the request
            if ($payzenResponse->isAuthentified()) {
                // Check payment status
                if ($payzenResponse->isAcceptedPayment()) {
                    // Payment was accepted.

                    if ($order->isPaid()) {
                        $this->getLog()->addInfo(Translator::getInstance()->trans("Order ID %id is already paid.", array('%id' => $order_id), Payzen::MODULE_DOMAIN));

                        $gateway_response_code = 'payment_ok_already_done';
                    } else {
                        $this->getLog()->addInfo(Translator::getInstance()->trans("Order ID %id payment was successful.", array('%id' => $order_id), Payzen::MODULE_DOMAIN));

                        // Payment OK !
                        $this->confirmPayment($eventDispatcher, $order_id);

                        $gateway_response_code = 'payment_ok';
                    }
                } else if ($payzenResponse->isCancelledPayment()) {
                    // Payment was canceled.
                    $this->cancelPayment($eventDispatcher, $order_id);
                } else {
                    // Payment was not accepted.
                    $this->getLog()->addError(Translator::getInstance()->trans("Order ID %id payment failed.", array('%id' => $order_id), Payzen::MODULE_DOMAIN));

                    if ($order->isPaid()) {
                        $gateway_response_code = 'payment_ko_already_done';
                    } else {
                        $gateway_response_code = 'payment_ko';
                    }
                }
            } else {
                $this->getLog()->addError(Translator::getInstance()->trans("Response could not be authenticated."));

                $gateway_response_code = 'auth_fail';
            }
        } else {
            $gateway_response_code = 'order_not_found';
        }

        $this->getLog()->info(Translator::getInstance()->trans("Payzen platform request for order ID %id processing terminated.", array('%id' => $order_id), Payzen::MODULE_DOMAIN));

        return new Response($payzenResponse->getOutputForGateway($gateway_response_code));
    }
}
