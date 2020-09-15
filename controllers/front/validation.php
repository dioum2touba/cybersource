<?php
/*
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2015 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

/**
 * @since 1.5.0
 */
class CyberSourceValidationModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        $cart = $this->context->cart;
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'cybersource') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            die($this->module->l('This payment method is not available.', 'validation'));
        }

        foreach($_REQUEST as $name => $value) {
            $params[$name] = $value;
        }
            

        // Pour recupérer les valeurs et les renvoyer dans la page de confirmation
        // $this->context->smarty->assign([
        //     'params' => $_REQUEST,
        //     'signature' => $this->sign($params),
        //     'currency_val' => $this->context->currency,
        //     'amount' => $params['amount'],
        //     'reference_number' => $params['reference_number'],
        //     'signed_date_time' => $params['signed_date_time'],
        //     'transaction_uuid' => $params['transaction_uuid'],
        //     'locale' => $params['locale'],
        //     'cust_currency' => $this->context->cart->id_currency,
        //     'currencies' => $this->module->getCurrency((int)$cart->id_currency),
        // ]);

        $this->context->smarty->assign([
            'params' => $_REQUEST,
            'signature' => $this->sign($params)
        ]);

        //$this->setTemplate('payment_return.tpl');
        $this->setTemplate('module:cybersource/views/templates/front/payment_confirm.tpl');


        // $customer = new Customer($cart->id_customer);
        // if (!Validate::isLoadedObject($customer))
        //     Tools::redirect('index.php?controller=order&step=1');

        // $currency = $this->context->currency;
        // $total = (float)$cart->getOrderTotal(true, Cart::BOTH);
        // $mailVars = array(
        //     '{bankwire_owner}' => Configuration::get('BANK_WIRE_OWNER'),
        //     '{bankwire_details}' => nl2br(Configuration::get('BANK_WIRE_DETAILS')),
        //     '{bankwire_address}' => nl2br(Configuration::get('BANK_WIRE_ADDRESS'))
        // );

        // $this->module->validateOrder($cart->id, Configuration::get('PS_OS_BANKWIRE'), $total, $this->module->displayName, NULL, $mailVars, (int)$currency->id, false, $customer->secure_key);
        // Tools::redirect('index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);
    }

    function sign ($params) {
        return $this->signData($this->buildDataToSign($params), '072b4e72216d45a1b8daaa441e5acfc295cdde6a93cd441cb248f3ed602a5d7fda4e0f7025ac4dd5bf1b1ae7cc884cfb1a464524635e4e4cb87c0c681fdd16efa63340015cd04fd79f2e7e76a52c39d4a691279f2f124ad9b69c8feb7c4a595d8d49a4245cb84e0f859a5aebb77e2a7ba7243921054e4b36baf9601c1ec90c81');
    }
    
    function signData($data, $secretKey) {
        return base64_encode(hash_hmac('sha256', $data, $secretKey, true));
    }
    
    function buildDataToSign($params) {
        $signedFieldNames = explode(',', $params["signed_field_names"]);
        foreach ($signedFieldNames as $field) {
            $dataToSign[] = $field.'='.$params[$field];
        }
        return $this->commaSeparate($dataToSign);
    }
    
    function commaSeparate ($dataToSign) {
        return implode(',', $dataToSign);
    }
}
