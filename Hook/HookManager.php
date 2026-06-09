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

namespace Payzen\Hook;

use Payzen\Form\ConfigurationForm;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Model\Base\ModuleQuery;
use Thelia\Model\CurrencyQuery;
use Thelia\Tools\URL;

class HookManager extends BaseHook
{
    public function __construct(
        private readonly TheliaFormFactory $formFactory,
        ?EventDispatcherInterface $dispatcher = null,
        ?ParserResolver $parserResolver = null,
    ) {
        parent::__construct($dispatcher, $parserResolver);
    }

    public function onModuleConfigure(HookRenderEvent $event): void
    {
        $multiEnabled = $this->isModuleActive('PayzenMulti');
        $sepaEnabled = $this->isModuleActive('PayzenOneOffSEPA');

        $form = $this->formFactory->createForm(ConfigurationForm::getName());

        $callbackUrl = URL::getInstance()->absoluteUrl('/payzen/callback');

        $defaultCurrency = CurrencyQuery::create()->findOneByByDefault(true);
        $currencySymbol = $defaultCurrency ? $defaultCurrency->getSymbol() : '€';

        $event->add($this->render('Payzen/module-configuration.html.twig', [
            'form' => $form->createView()->getView(),
            'multiEnabled' => $multiEnabled,
            'sepaEnabled' => $sepaEnabled,
            'callbackUrl' => $callbackUrl,
            'currencySymbol' => $currencySymbol,
        ]));
    }

    public static function getSubscribedHooks(): array
    {
        return [
            'module.configuration' => [
                [
                    'type' => 'back',
                    'method' => 'onModuleConfigure',
                ],
            ],
        ];
    }

    private function isModuleActive(string $code): bool
    {
        $module = ModuleQuery::create()->findOneByCode($code);

        return $module !== null && $module->getActivate() !== 0;
    }
}
