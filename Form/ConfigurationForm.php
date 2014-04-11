<?php
/*************************************************************************************/
/* */
/* Thelia */
/* */
/* Copyright (c) OpenStudio */
/* email : info@thelia.net */
/* web : http://www.thelia.net */
/* */
/* This program is free software; you can redistribute it and/or modify */
/* it under the terms of the GNU General Public License as published by */
/* the Free Software Foundation; either version 3 of the License */
/* */
/* This program is distributed in the hope that it will be useful, */
/* but WITHOUT ANY WARRANTY; without even the implied warranty of */
/* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the */
/* GNU General Public License for more details. */
/* */
/* You should have received a copy of the GNU General Public License */
/* along with this program. If not, see <http://www.gnu.org/licenses/>. */
/* */
/*************************************************************************************/

namespace Payzen\Form;

use Payzen\Model\PayzenConfigQuery;
use Payzen\Payzen\PayzenApi;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

/**
 * Payzen configuration data
 *
 * @package Payzen\Form
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class ConfigurationForm extends BaseForm
{
    protected function buildForm()
    {
        $T = Translator::getInstance();

        $api = new PayzenApi();

        // Available languages, translated.
        $available_languages = array();

        foreach ($api->getSupportedLanguages() as $code => $label) {
            $available_languages[$code] = $T->trans($label);
        }

        asort($available_languages);

        foreach ($api->getSupportedCardTypes() as $code => $label) {
            $available_cards[$code] = $T->trans($label);
        }

        asort($available_cards);

        $this->formBuilder
            ->add(
                'site_id',
                'text',
                array(
                    'constraints' => array(new NotBlank()),
                    'required' => true,
                    'label' => $T->trans('Site ID'),
                    'data' => PayzenConfigQuery::read('site_id', '12345678'),
                    'label_attr' => array(
                        'for' => 'site_id',
                        'help' => $T->trans('Site ID provided by the payment gateway')
                    )
                )
            )
            ->add(
                'test_certificate',
                'text',
                array(
                    'constraints' => array(new NotBlank()),
                    'required' => true,
                    'label' => $T->trans('Test certificate'),
                    'data' => PayzenConfigQuery::read('test_certificate', '1111111111111111'),
                    'label_attr' => array(
                        'for' => 'test_certificate',
                        'help' => $T->trans('The test certificate provided by the payment gateway')
                    )
                )
            )
            ->add(
                'production_certificate',
                'text',
                array(
                    'constraints' => array(new NotBlank()),
                    'required' => true,
                    'label' => $T->trans('Production certificate'),
                    'data' => PayzenConfigQuery::read('production_certificate', '1111111111111111'),
                    'label_attr' => array(
                        'for' => 'production_certificate',
                        'help' => $T->trans('The production certificate provided by the payment gateway')
                    )
                )
            )
            ->add(
                'platform_url',
                'text',
                array(
                    'constraints' => array(new NotBlank()),
                    'required' => true,
                    'label' => $T->trans('Payment page URL'),
                    'data' => PayzenConfigQuery::read('platform_url', 'https://secure.payzen.eu/vads-payment/'),
                    'label_attr' => array(
                        'for' => 'platform_url',
                        'help' => $T->trans('URL the client will be redirected to')
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
                        'TEST' => $T->trans('Test'),
                        'PROD' => $T->trans('Production'),
                    ),
                    'label' => $T->trans('Operation Mode'),
                    'data' => PayzenConfigQuery::read('mode', 'TEST'),
                    'label_attr' => array(
                        'for' => 'mode',
                        'help' => $T->trans('Test or production mode')
                    )
                )
            )
            ->add(
                'allowed_ip_list',
                'textarea',
                array(
                    'required' => false,
                    'label' => $T->trans('Allowed IPs in test mode'),
                    'data' => PayzenConfigQuery::read('allowed_ip_list', ''),
                    'label_attr' => array(
                        'for' => 'platform_url',
                        'help' => $T->trans(
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
                    'choices' => $available_languages,
                    'label' => $T->trans('Default language'),
                    'data' => PayzenConfigQuery::read('default_language', ''),
                    'label_attr' => array(
                        'for' => 'default_language',
                        'help' => $T->trans('The default language of the payment page')
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
                    'label' => $T->trans('Available languages'),
                    'data' => explode(';', PayzenConfigQuery::read('available_languages', '')),
                    'label_attr' => array(
                        'for' => 'available_languages',
                        'help' => $T->trans(
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
                    'label' => $T->trans('Banking delay'),
                    'data' => PayzenConfigQuery::read('banking_delay', '0'),
                    'label_attr' => array(
                        'for' => 'banking_delay',
                        'help' => $T->trans('Delay before banking (in days)')
                    )
                )
            )
            ->add(
                'validation_mode',
                'choice',
                array(
                    'required' => false,
                    'choices' => array(
                        '' => $T->trans('Default'),
                        '0' => $T->trans('Automatic'),
                        '1' => $T->trans('Manual'),
                    ),
                    'label' => $T->trans('Payment validation'),
                    'data' => PayzenConfigQuery::read('validation_mode', ''),
                    'label_attr' => array(
                        'for' => 'validation_mode',
                        'help' => $T->trans(
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
                    'label' => $T->trans('Available payment cards'),
                    'data' => explode(';', PayzenConfigQuery::read('allowed_cards', '')),
                    'label_attr' => array(
                        'for' => 'allowed_cards',
                        'help' => $T->trans('Select nothing to use gateway configuration.'),
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
                        'False' => $T->trans('Disabled'),
                        'True' => $T->trans('Enabled'),
                    ),
                    'label' => $T->trans('Automatic redirection after payment'),
                    'data' => PayzenConfigQuery::read('redirect_enabled', 'True'),
                    'label_attr' => array(
                        'for' => 'redirect_enabled',
                        'help' => $T->trans('Redirect the customer to the shop at the end of the payment process')
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
                    'label' => $T->trans('Success timeout'),
                    'data' => PayzenConfigQuery::read('success_timeout', '5'),
                    'label_attr' => array(
                        'for' => 'success_timeout',
                        'help' => $T->trans(
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
                    'label' => $T->trans('Success message'),
                    'data' => PayzenConfigQuery::read('success_message', '5'),
                    'label_attr' => array(
                        'for' => 'success_timeout',
                        'help' => $T->trans('Message displayed after a successful payment before redirecting')
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
                    'label' => $T->trans('Failure timeout'),
                    'data' => PayzenConfigQuery::read('failure_timeout', '5'),
                    'label_attr' => array(
                        'for' => 'failure_timeout',
                        'help' => $T->trans('Time in seconds before the client is redirected after a failed payment')
                    )
                )
            )
            ->add(
                'failure_message',
                'text',
                array(
                    'required' => false,
                    'label' => $T->trans('Failure message'),
                    'data' => PayzenConfigQuery::read('failure_message', '5'),
                    'label_attr' => array(
                        'for' => 'failure_message',
                        'help' => $T->trans('Message displayed after a failed payment before redirecting')
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
                    'label' => $T->trans('Minimum order total'),
                    'data' => PayzenConfigQuery::read('minimum_amount', '0'),
                    'label_attr' => array(
                        'for' => 'minimum_amount',
                        'help' => $T->trans('Minimum order total in the default currency for which this payment method is available. Enter 0 for no minimum')
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
                    'label' => $T->trans('Maximum order total'),
                    'data' => PayzenConfigQuery::read('maximum_amount', '0'),
                    'label_attr' => array(
                        'for' => 'maximum_amount',
                        'help' => $T->trans('Maximum order total in the default currency for which this payment method is available. Enter 0 for no maximum')
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
                    'label' => $T->trans('3D Secure minimum order amount'),
                    'data' => PayzenConfigQuery::read('three_ds_minimum_order_amount', '0'),
                    'label_attr' => array(
                        'for' => 'three_ds_minimum_order_amount',
                        'help' => $T->trans('Minimum order total in the default currency to request a 3D Secure authentication')
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