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

use Payzen\Form\ConfigurationForm;
use Payzen\Model\PayzenConfigQuery;
use Payzen\Payzen;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Tools\URL;

/**
 * Payzen payment module
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class ConfigurationController extends BaseAdminController
{

    /**
     * @return mixed an HTTP response, or
     */
    public function configure()
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, 'Payzen', AccessManager::UPDATE)) {
            return $response;
        }

        // Initialize the potential error message, and the potential exception
        $error_msg = $ex = null;

        // Create the Form from the request
        $configurationForm = new ConfigurationForm($this->getRequest());

        try {

            // Check the form against constraints violations
            $form = $this->validateForm($configurationForm, "POST");

            // Get the form field values
            $data = $form->getData();

            foreach($data as $name => $value) {
                if (is_array($value)) {
                    $value = implode(';', $value);
                }

                PayzenConfigQuery::set($name, $value);
            }

            // Log configuration modification
            $this->adminLogAppend(
                "payzen.configuration.message",
                AccessManager::UPDATE,
                sprintf("Payzen configuration updated")
            );

            // Redirect to the success URL,
            if ($this->getRequest()->get('save_mode') == 'stay') {
                // If we have to stay on the same page, redisplay the configuration page/
                $route = '/admin/module/Payzen';
            }
            else {
                // If we have to close the page, go back to the module back-office page.
                $route = '/admin/modules';
            }

            $this->redirect(URL::getInstance()->absoluteUrl($route));

            // An exit is performed after redirect.+

        } catch (FormValidationException $ex) {
            // Form cannot be validated. Create the error message using
            // the BaseAdminController helper method.
            $error_msg = $this->createStandardFormValidationErrorMessage($ex);
        }
        catch (\Exception $ex) {
              // Any other error
             $error_msg = $ex->getMessage();
        }

        // At this point, the form has errors, and should be redisplayed. We don not redirect,
        // just redisplay the same template.
        // Setup the Form error context, to make error information available in the template.
        $this->setupFormErrorContext(
            $this->getTranslator()->trans("Payzen configuration", [], Payzen::MODULE_DOMAIN),
            $error_msg,
            $configurationForm,
            $ex
        );

        // Do not redirect at this point, or the error context will be lost.
        // Just redisplay the current template.
        return $this->render('module-configure', array('module_code' => 'Payzen'));
    }
}