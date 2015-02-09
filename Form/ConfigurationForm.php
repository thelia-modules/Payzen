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

namespace Payzen\Form;

use Payzen\Model\PayzenConfigQuery;
use Payzen\Payzen\PayzenApi;
use Payzen\Payzen;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;
use Thelia\Model\Base\ModuleQuery;
use Thelia\Model\Module;

/**
 * Payzen payment module
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class ConfigurationForm extends BaseForm
{
    protected function trans($str, $params = []) {
        return Translator::getInstance()->trans($str, $params, Payzen::MODULE_DOMAIN);
    }

    protected function buildForm()
    {
        $api = new PayzenApi();

        // Available languages, translated.
        $available_languages = array();

        foreach ($api->getSupportedLanguages() as $code => $label) {
            $available_languages[$code] = $this->trans($label);
        }

        $available_languages_combo = array_merge(
            array("" => $this->trans("Please select...")),
            $available_languages
        );

        asort($available_languages);

        foreach ($api->getSupportedCardTypes() as $code => $label) {
            $available_cards[$code] = $this->trans($label);
        }

        asort($available_cards);

        // If the Multi plugin is not enabled, all multi_fields are hidden
        /** @var Module $multiModule */
        $multiEnabled = (null !== $multiModule = ModuleQuery::create()->findOneByCode('PayzenMulti')) && $multiModule->getActivate() != 0;

        $this->formBuilder
            ->add(
                'site_id',
                'text',
                array(
                    'constraints' => array(new NotBlank()),
                    'required' => true,
                    'label' => $this->trans('Site ID'),
                    'data' => PayzenConfigQuery::read('site_id', '12345678'),
                    'label_attr' => array(
                        'for' => 'site_id',
                        'help' => $this->trans('Site ID provided by the payment gateway')
                    )
                )
            )
            ->add(
                'test_certificate',
                'text',
                array(
                    'constraints' => array(new NotBlank()),
                    'required' => true,
                    'label' => $this->trans('Test certificate'),
                    'data' => PayzenConfigQuery::read('test_certificate', '1111111111111111'),
                    'label_attr' => array(
                        'for' => 'test_certificate',
                        'help' => $this->trans('The test certificate provided by the payment gateway')
                    )
                )
            )
            ->add(
                'production_certificate',
                'text',
                array(
                    'constraints' => array(new NotBlank()),
                    'required' => true,
                    'label' => $this->trans('Production certificate'),
                    'data' => PayzenConfigQuery::read('production_certificate', '1111111111111111'),
                    'label_attr' => array(
                        'for' => 'production_certificate',
                        'help' => $this->trans('The production certificate provided by the payment gateway')
                    )
                )
            )
            ->add(
                'platform_url',
                'text',
                array(
                    'constraints' => array(new NotBlank()),
                    'required' => true,
                    'label' => $this->trans('Payment page URL'),
                    'data' => PayzenConfigQuery::read('platform_url', 'https://secure.payzen.eu/vads-payment/'),
                    'label_attr' => array(
                        'for' => 'platform_url',
                        'help' => $this->trans('URL the client will be redirected to')
                    )
                )
            )
            ->add(
                'mode',
                'choice',
                array(
                    'constraints' => array(new NotBlank()),
                    'required' => true,
                    'choices' => array(
                        'TEST' => $this->trans('Test'),
                        'PRODUCTION' => $this->trans('Production'),
                    ),
                    'label' => $this->trans('Operation Mode'),
                    'data' => PayzenConfigQuery::read('mode', 'TEST'),
                    'label_attr' => array(
                        'for' => 'mode',
                        'help' => $this->trans('Test or production mode')
                    )
                )
            )
            ->add(
                'allowed_ip_list',
                'textarea',
                array(
                    'required' => false,
                    'label' => $this->trans('Allowed IPs in test mode'),
                    'data' => PayzenConfigQuery::read('allowed_ip_list', ''),
                    'label_attr' => array(
                        'for' => 'platform_url',
                        'help' => $this->trans(
                                'List of IP addresses allowed to use this payment on the front-office when in test mode (your current IP is %ip). One address per line',
                                array('%ip' => $this->getRequest()->getClientIp())
                        ),
                        'rows' => 3
                    )
                )
            )
            ->add(
                'default_language',
                'choice',
                array(
                    'constraints' => array(new NotBlank()),
                    'required' => true,
                    'choices' => $available_languages_combo,
                    'label' => $this->trans('Default language'),
                    'data' => PayzenConfigQuery::read('default_language', ''),
                    'label_attr' => array(
                        'for' => 'default_language',
                        'help' => $this->trans('The default language of the payment page')
                    )
                )
            )
            ->add(
                'available_languages',
                'choice',
                array(
                    'required' => false,
                    'choices' => $available_languages,
                    'multiple' => true,
                    'label' => $this->trans('Available languages'),
                    'data' => explode(';', PayzenConfigQuery::read('available_languages', '')),
                    'label_attr' => array(
                        'for' => 'available_languages',
                        'help' => $this->trans(
                                'Languages available on the payment page. Select nothing to use gateway config.'
                            ),
                        'size' => 10
                    )
                )
            )
            ->add(
                'banking_delay',
                'number',
                array(
                    'constraints' => array(
                        new NotBlank(),
                        new GreaterThanOrEqual(array('value' => 0))
                    ),
                    'required' => true,
                    'label' => $this->trans('Banking delay'),
                    'data' => PayzenConfigQuery::read('banking_delay', '0'),
                    'label_attr' => array(
                        'for' => 'banking_delay',
                        'help' => $this->trans('Delay before banking (in days)')
                    )
                )
            )
            ->add(
                'validation_mode',
                'choice',
                array(
                    'required' => false,
                    'choices' => array(
                        '' => $this->trans('Default'),
                        '0' => $this->trans('Automatic'),
                        '1' => $this->trans('Manual'),
                    ),
                    'label' => $this->trans('Payment validation'),
                    'data' => PayzenConfigQuery::read('validation_mode', ''),
                    'label_attr' => array(
                        'for' => 'validation_mode',
                        'help' => $this->trans(
                                'If manual is selected, you will have to confirm payments manually in your bank back-office'
                            )
                    )
                )
            )
            ->add(
                'allowed_cards',
                'choice',
                array(
                    'required' => false,
                    'choices' => $available_cards,
                    'multiple' => true,
                    'label' => $this->trans('Available payment cards'),
                    'data' => explode(';', PayzenConfigQuery::read('allowed_cards', '')),
                    'label_attr' => array(
                        'for' => 'allowed_cards',
                        'help' => $this->trans('Select nothing to use gateway configuration.'),
                        'size' => 7
                    )
                )
            )
            ->add(
                'redirect_enabled',
                'choice',
                array(
                    'required' => true,
                    'choices' => array(
                        'False' => $this->trans('Disabled'),
                        'True' => $this->trans('Enabled'),
                    ),
                    'label' => $this->trans('Automatic redirection after payment'),
                    'data' => PayzenConfigQuery::read('redirect_enabled', 'True'),
                    'label_attr' => array(
                        'for' => 'redirect_enabled',
                        'help' => $this->trans('Redirect the customer to the shop at the end of the payment process')
                    )
                )
            )
            ->add(
                'success_timeout',
                'number',
                array(
                    'constraints' => array(
                        new NotBlank(),
                        new GreaterThanOrEqual(array('value' => 0))
                    ),
                    'required' => true,
                    'label' => $this->trans('Success timeout'),
                    'data' => PayzenConfigQuery::read('success_timeout', '5'),
                    'label_attr' => array(
                        'for' => 'success_timeout',
                        'help' => $this->trans(
                                'Time in seconds before the client is redirected after a successful payment'
                            )
                    )
                )
            )
            ->add(
                'success_message',
                'text',
                array(
                    'required' => false,
                    'label' => $this->trans('Success message'),
                    'data' => PayzenConfigQuery::read('success_message', ''),
                    'label_attr' => array(
                        'for' => 'success_timeout',
                        'help' => $this->trans('Message displayed after a successful payment before redirecting')
                    )
                )
            )
            ->add(
                'failure_timeout',
                'number',
                array(
                    'constraints' => array(
                        new NotBlank(),
                        new GreaterThanOrEqual(array('value' => 0))
                    ),
                    'required' => true,
                    'label' => $this->trans('Failure timeout'),
                    'data' => PayzenConfigQuery::read('failure_timeout', '5'),
                    'label_attr' => array(
                        'for' => 'failure_timeout',
                        'help' => $this->trans('Time in seconds before the client is redirected after a failed payment')
                    )
                )
            )
            ->add(
                'failure_message',
                'text',
                array(
                    'required' => false,
                    'label' => $this->trans('Failure message'),
                    'data' => PayzenConfigQuery::read('failure_message', ''),
                    'label_attr' => array(
                        'for' => 'failure_message',
                        'help' => $this->trans('Message displayed after a failed payment before redirecting')
                    )
                )
            )
            ->add(
                'minimum_amount',
                'money',
                array(
                    'constraints' => array(
                        new NotBlank(),
                        new GreaterThanOrEqual(array('value' => 0))
                    ),
                    'required' => true,
                    'label' => $this->trans('Minimum order total'),
                    'data' => PayzenConfigQuery::read('minimum_amount', '0'),
                    'label_attr' => array(
                        'for' => 'minimum_amount',
                        'help' => $this->trans('Minimum order total in the default currency for which this payment method is available. Enter 0 for no minimum')
                    )
                )
            )
            ->add(
                'maximum_amount',
                'money',
                array(
                    'constraints' => array(
                        new NotBlank(),
                        new GreaterThanOrEqual(array('value' => 0))
                    ),
                    'required' => true,
                    'label' => $this->trans('Maximum order total'),
                    'data' => PayzenConfigQuery::read('maximum_amount', '0'),
                    'label_attr' => array(
                        'for' => 'maximum_amount',
                        'help' => $this->trans('Maximum order total in the default currency for which this payment method is available. Enter 0 for no maximum')
                    )
                )
            )
            ->add(
                'three_ds_minimum_order_amount',
                'money',
                array(
                    'constraints' => array(
                        new NotBlank(),
                        new GreaterThanOrEqual(array('value' => 0))
                    ),
                    'required' => true,
                    'label' => $this->trans('3D Secure minimum order amount'),
                    'data' => PayzenConfigQuery::read('three_ds_minimum_order_amount', '0'),
                    'label_attr' => array(
                        'for' => 'three_ds_minimum_order_amount',
                        'help' => $this->trans('Minimum order total in the default currency to request a 3D Secure authentication')
                    )
                )
            )

            // -- Multiple payments configuration

            ->add(
                'multi_minimum_amount',
                $multiEnabled ? 'money' : 'hidden',
                array(
                    'constraints' => array(
                        new NotBlank(),
                        new GreaterThanOrEqual(array('value' => 0))
                    ),
                    'required' => true,
                    'label' => $this->trans('Minimum order total for multiple times'),
                    'data' => PayzenConfigQuery::read('multi_minimum_amount', '0'),
                    'label_attr' => array(
                        'for' => 'minimum_amount',
                        'help' => $this->trans('Minimum order total in the default currency for which multiple times payment method is available. Enter 0 for no minimum')
                    )
                )
            )
            ->add(
                'multi_maximum_amount',
                $multiEnabled ? 'money' : 'hidden',
                array(
                    'constraints' => array(
                        new NotBlank(),
                        new GreaterThanOrEqual(array('value' => 0))
                    ),
                    'required' => true,
                    'label' => $this->trans('Maximum order total for multiple times'),
                    'data' => PayzenConfigQuery::read('multi_maximum_amount', '0'),
                    'label_attr' => array(
                        'for' => 'maximum_amount',
                        'help' => $this->trans('Maximum order total in the default currency for which multiple times payment method is available. Enter 0 for no maximum')
                    )
                )
            )
            ->add(
                'multi_first_payment',
                $multiEnabled ? 'number' : 'hidden',
                array(
                    'constraints' => array(
                        new NotBlank(),
                        new GreaterThanOrEqual(array('value' => 0)),
                        new LessThanOrEqual(array('value' => 100))
                    ),
                    'required' => false,
                    'label' => $this->trans('Amount of first payment '),
                    'data' => PayzenConfigQuery::read('multi_first_payment', 25),
                    'label_attr' => array(
                        'for' => 'multi_first_payment',
                        'help' => $this->trans('Amount of the first payment, as a percent of the order total. If zero or empty, all payments will be equals.')
                    )
                )
            )
            ->add(
                'multi_number_of_payments',
                $multiEnabled ? 'number' : 'hidden',
                array(
                    'constraints' => array(
                        new NotBlank(),
                        new GreaterThanOrEqual(array('value' => 1))
                    ),
                    'required' => true,
                    'label' => $this->trans('Number of payments'),
                    'data' => PayzenConfigQuery::read('multi_number_of_payments', 4),
                    'label_attr' => array(
                        'for' => 'multi_number_of_payments',
                        'help' => $this->trans('The total number of payments')
                    )
                )
            )
            ->add(
                'multi_payments_interval',
                $multiEnabled ? 'number' : 'hidden',
                array(
                    'constraints' => array(new NotBlank()),
                    'required' => true,
                    'label' => $this->trans('Days between two payments'),
                    'data' => PayzenConfigQuery::read('multi_payments_interval', 30),
                    'label_attr' => array(
                        'for' => 'multi_payments_interval',
                        'help' => $this->trans('The interval in days between payments')
                    )
                )
            )
        ;
    }

    public function getName()
    {
        return 'payzen_configuration_form';
    }
}
