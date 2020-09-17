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

        // $address = new Address($cart->id_address_invoice);
        $customer = new Customer($cart->id_customer);
        // $currency = Currency::getCurrency($cart->id_currency);

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
    
        // $params['locale'] = $address->iso_code;
        // $params['currency'] = $currency["iso_code"];
        // $params['bill_address1'] = $address->address1;
        // $params['bill_city'] = $address->city;
        // $params['bill_to_address_country'] = $address->country;
        // $params['customer_email'] = $customer->email;
        // $params['customer_lastname'] = $customer->lastname;

        if (!Validate::isLoadedObject($customer))
            Tools::redirect('index.php?controller=order&step=1');

        // $id_address_delivery =  $cart->id_address_delivery;
        // $id_address_invoice = $cart->id_address_invoice;

        // Pour recupérer les valeurs et les renvoyer dans la page de confirmation
        $this->context->smarty->assign([
            'params' => $_REQUEST,
            'signature' => $this->sign($params),
            // 'currency_val' => $this->context->currency,
            // 'amount' => $params['amount'],
            // 'reference_number' => $params['reference_number'],
            // 'signed_date_time' => $params['signed_date_time'],
            // 'transaction_uuid' => $params['transaction_uuid'],
            // 'locale' => $params['locale'],
            // 'cust_currency' => $this->context->cart->id_currency,
            // 'currencies' => $this->module->getCurrency((int)$cart->id_currency),
            // 'bill_address1' => $address->address1,
            // 'bill_city' => $address->city,
            // 'bill_country' => $address->country,
            // 'customer_email' => $customer->email,
            // 'customer_lastname' => $customer->lastname,
            // 'id_customer' => $cart->id_customer, 
            // 'id_address_delivery' => $id_address_delivery, 
            // 'id_address_invoice' => $id_address_invoice, 
        ]);

        // $this->context->smarty->assign([
        //     'params' => $_REQUEST,
        //     'signature' => $this->sign($params),
        //     'bill_address1' => $customer->bill_address1
        // ]);

        //$this->setTemplate('payment_return.tpl');
        $this->setTemplate('module:cybersource/views/templates/front/payment_confirm.tpl');



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

    public function postSucces()
    {
        $cart = $this->context->cart;
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // $address = new Address($cart->id_address_invoice);
        $customer = new Customer($cart->id_customer);
        // $currency = Currency::getCurrency($cart->id_currency);

        

        $currency = $this->context->currency;
        $total = (float)$cart->getOrderTotal(true, Cart::BOTH);
        $mailVars = array(
            '{bankwire_owner}' => Configuration::get('BANK_WIRE_OWNER'),
            '{bankwire_details}' => nl2br(Configuration::get('BANK_WIRE_DETAILS')),
            '{bankwire_address}' => nl2br(Configuration::get('BANK_WIRE_ADDRESS'))
        );

        $this->module->validateOrder($cart->id, Configuration::get('PS_OS_BANKWIRE'), $total, $this->module->displayName, NULL, $mailVars, (int)$currency->id, false, $customer->secure_key);
        Tools::redirect('index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);
    }

    function sign ($params) {
        return $this->signData($this->buildDataToSign($params), Configuration::get('SECRET_KEY'));
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
