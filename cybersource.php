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

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class CyberSource extends PaymentModule
{
    protected $_html = '';
    protected $_postErrors = array();

    public $details;
    public $owner;
    public $address;
    public $extra_mail_vars;

    public function __construct()
    {
        $this->name = 'cybersource';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->author = 'Obertys SARL';
        $this->controllers = array('validation');
        $this->is_eu_compatible = 1;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('CyberSource Payment');
        $this->description = $this->l('Description of Payment CyberSource');

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }
    }

    public function install()
    {
        if (!parent::install() || !$this->registerHook('paymentOptions') || !$this->registerHook('paymentReturn')) {
            return false;
        }
        return true;
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $payment_options = [
           // $this->getOfflinePaymentOption(),
            $this->getExternalPaymentOption(),
           // $this->getEmbeddedPaymentOption(),
            // $this->getIframePaymentOption(),
        ];

        return $payment_options;
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    public function getOfflinePaymentOption()
    {
        $offlineOption = new PaymentOption();
        $offlineOption->setCallToActionText($this->l('Pay offline'))
                      ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))
                      ->setAdditionalInformation($this->context->smarty->fetch('module:cybersource/views/templates/front/payment_infos.tpl'))
                      ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/payment.png'));

        return $offlineOption;
    }

    public function getExternalPaymentOption()
    {
        $date = date_create();
        $externalOption = new PaymentOption();
        $externalOption->setCallToActionText($this->l('Pay via CyberSource'))
                       ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))
                       ->setInputs([
                            'access_key' => [
                                'name' =>'access_key',
                                'type' =>'hidden',
                                'value' => Configuration::get('ACCES_KEY'),
                            ],
                            'amount' => [
                                'name' =>'amount',
                                'type' =>'hidden',
                                'value' => $this->context->cart->getOrderTotal(true, Cart::BOTH),
                            ],
                            'currency' => [
                                'name' =>'currency',
                                'type' =>'hidden',
                                'value' => 'USD',
                            ],
                            'locale' => [
                                'name' =>'locale',
                                'type' =>'hidden',
                                'value' => 'en',
                            ],
                            'profile_id' => [
                                'name' =>'profile_id',
                                'type' =>'hidden',
                                'value' => Configuration::get('PROFIL_ID'),
                            ],
                            'reference_number' => [
                                'name' =>'reference_number',
                                'type' =>'hidden',
                                'value' => time()*1000,
                            ],
                            'signed_date_time' => [
                                'name' =>'signed_date_time',
                                'type' =>'hidden',
                                'value' => gmdate("Y-m-d\TH:i:s\Z"),
                            ],
                            'signed_field_names' => [
                                'name' =>'signed_field_names',
                                'type' =>'hidden',
                                'value' => 'access_key,profile_id,transaction_uuid,signed_field_names,unsigned_field_names,signed_date_time,locale,transaction_type,reference_number,amount,currency',
                            ],
                            'transaction_type' => [
                                'name' =>'transaction_type',
                                'type' =>'hidden',
                                'value' => 'authorization',
                            ],
                            'transaction_uuid' => [
                                'name' =>'transaction_uuid',
                                'type' =>'hidden',
                                'value' => uniqid(),
                            ],
                            'unsigned_field_names' => [
                                'name' =>'unsigned_field_names',
                                'type' =>'hidden',
                                'value' => '',
                            ],
                        ])
                       //->setAdditionalInformation($this->context->smarty->fetch('module:cybersource/views/templates/front/payment_infos.tpl'))
                       ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/payment.png'));

        return $externalOption;
    }

    /**
     * Configuration admin du module
     */
    public function getContent()
    {
        $this->_html .=$this->postProcess();
        $this->_html .= $this->renderForm();

        return $this->_html;

    }

    /**
     * Traitement de la configuration BO
     * @return type
     */
    public function postProcess()
    {
        if ( Tools::isSubmit('SubmitPaymentConfiguration'))
        {
            Configuration::updateValue('PAYMENT_API_URL', Tools::getValue('PAYMENT_API_URL'));
            Configuration::updateValue('PAYMENT_API_URL_SUCESS', Tools::getValue('PAYMENT_API_URL_SUCESS'));
            Configuration::updateValue('PAYMENT_API_URL_ERROR', Tools::getValue('PAYMENT_API_URL_ERROR'));
            
            Configuration::updateValue('SECRET_KEY', Tools::getValue('SECRET_KEY'));
            Configuration::updateValue('ACCES_KEY', Tools::getValue('ACCES_KEY'));
            Configuration::updateValue('PROFIL_ID', Tools::getValue('PROFIL_ID'));
        }
        return $this->displayConfirmation($this->l('Configuration updated with success'));
    }

    /**
    * Formulaire de configuration admin
    */
   public function renderForm()
   {
       $fields_form = [
           'form' => [
               'legend' => [
                   'title' => $this->l('Payment Configuration'),
                   'icon' => 'icon-cogs'
               ],
               'description' => $this->l('Configuration form'),
               'input' => [
                  [
                       'type' => 'text',
                       'label' => $this->l('Payment api url'),
                       'name' => 'PAYMENT_API_URL',
                       'required' => true,
                       'empty_message' => $this->l('Please fill the payment api url'),
                  ],
                  [
                       'type' => 'text',
                       'label' => $this->l('Payment api success url'),
                       'name' => 'PAYMENT_API_URL_SUCESS',
                       'required' => true,
                       'empty_message' => $this->l('Please fill the payment api success url'),
                   ],
                   [
                       'type' => 'text',
                       'label' => $this->l('Payment api error url'),
                       'name' => 'PAYMENT_API_URL_ERROR',
                       'required' => true,
                       'empty_message' => $this->l('Please fill the payment api error url'),
                   ],
                   [
                    'type' => 'text',
                    'label' => $this->l('Payment Secret Key'),
                    'name' => 'SECRET_KEY',
                    'required' => true,
                    'empty_message' => $this->l('Please fill the Payment Secret Key'),
                    ],
                    [
                     'type' => 'text',
                     'label' => $this->l('Payment Acces Key'),
                     'name' => 'ACCES_KEY',
                     'required' => true,
                     'empty_message' => $this->l('Please fill the Payment Acces Key'),
                    ],
                    [
                     'type' => 'text',
                     'label' => $this->l('Payment Profile Id'),
                     'name' => 'PROFIL_ID',
                     'required' => true,
                     'empty_message' => $this->l('Please fill the Payment Profile Id'),
                    ],
               ],
               'submit' => [
                   'title' => $this->l('Save'),
                   'class' => 'button btn btn-default pull-right',
               ],
           ],
           ];

       $helper = new HelperForm();
       $helper->show_toolbar = false;
       $helper->default_form_language = (int)Configuration::get('PS_LANG_DEFAULT');
       $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
       $helper->id = 'cybersource';
       $helper->identifier = 'cybersource';
       $helper->submit_action = 'SubmitPaymentConfiguration';
       $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
       $helper->token = Tools::getAdminTokenLite('AdminModules');
       $helper->tpl_vars = [
           'fields_value' => $this->getConfigFieldsValues(),
           'languages' => $this->context->controller->getLanguages(),
           'id_language' => $this->context->language->id
       ];

       return $helper->generateForm(array($fields_form));
   }

   /**
    * Récupération des variables de configuration du formulaire admin
    */
   public function getConfigFieldsValues()
   {
       return [
           'PAYMENT_API_URL' => Tools::getValue('PAYMENT_API_URL', Configuration::get('PAYMENT_API_URL')),
           'PAYMENT_API_URL_SUCESS' => Tools::getValue('PAYMENT_API_URL_SUCESS', Configuration::get('PAYMENT_API_URL_SUCESS')),
           'PAYMENT_API_URL_ERROR' => Tools::getValue('PAYMENT_API_URL_ERROR', Configuration::get('PAYMENT_API_URL_ERROR')),
           
           'SECRET_KEY' => Tools::getValue('SECRET_KEY', Configuration::get('SECRET_KEY')),
           'ACCES_KEY' => Tools::getValue('ACCES_KEY', Configuration::get('ACCES_KEY')),
           'PROFIL_ID' => Tools::getValue('PROFIL_ID', Configuration::get('PROFIL_ID')),
       ];
   }

   /**
    * Récupération des informations du template
    * @return array
    */
   public function getTemplateVars()
   {
       return [
           'shop_name' => $this->context->shop->name,
           'custom_var' => $this->l('My custom var value'),
           'payment_details' => $this->l('custom details'),
       ];
   }
}
