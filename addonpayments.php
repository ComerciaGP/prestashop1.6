<?php
/**
 * 2007-2018 PrestaShop
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
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2018 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once(dirname(__FILE__).'/classes/AddonPaymentsActions.php');

class Addonpayments extends PaymentModule
{

    protected $config_form = false;
    public $env;
    public $urltpv;
    public $merchant_id;
    public $shared_secret;
    public $settlement;
    public $realvault;
    public $offer_save_card;
    public $posible_cards;
    public $messages;

    public function __construct()
    {
        $this->name = 'addonpayments';
        $this->tab = 'payments_gateways';
        $this->version = '1.2.0';
        $this->author = 'eComm360 S.L.';
        $this->need_instance = 0;
        $this->controllers = array('payment', 'validation');

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        //realvault is the tokenization option

        $this->urltpv = AddonPaymentsActions::get_environment_url();

        $config = Configuration::getMultiple(array(
                    'ADDONPAYMENTS_URLTPV',
                    'ADDONPAYMENTS_MERCHANT_ID',
                    'ADDONPAYMENTS_TEST_MERCHANT_ID',
                    'ADDONPAYMENTS_SHARED_SECRET',
                    'ADDONPAYMENTS_TEST_SHARED_SECRET',
                    'ADDONPAYMENTS_REBATE_PASSWORD',
                    'ADDONPAYMENTS_REDIRECT_SETTLEMENT',
                    'ADDONPAYMENTS_REDIRECT_SUBACCOUNT',
                    'ADDONPAYMENTS_REDIRECT_REALVAULT',
                    'ADDONPAYMENTS_OFFER_SAVE_CARD',
                    'ADDONPAYMENTS_REDIRECT_CVN',
                    'ADDONPAYMENTS_FIRST_PAYMENT',
                    ));

        if (isset($config['ADDONPAYMENTS_MERCHANT_ID']) || isset($config['ADDONPAYMENTS_TEST_MERCHANT_ID']))
        {
          $this->merchant_id = Configuration::get('ADDONPAYMENTS_MERCHANT_ID') ? $config['ADDONPAYMENTS_MERCHANT_ID'] : $config['ADDONPAYMENTS_TEST_MERCHANT_ID'];
        }
        if (isset($config['ADDONPAYMENTS_SHARED_SECRET']) || isset($config['ADDONPAYMENTS_TEST_SHARED_SECRET']))
        {
          $this->shared_secret = Configuration::get('ADDONPAYMENTS_SHARED_SECRET') ? $config['ADDONPAYMENTS_SHARED_SECRET'] : $config['ADDONPAYMENTS_TEST_SHARED_SECRET'];
        }
        if (isset($config['ADDONPAYMENTS_REDIRECT_SETTLEMENT'])) {
          $this->settlement = $config['ADDONPAYMENTS_REDIRECT_SETTLEMENT'];
        }
        if (isset($config['ADDONPAYMENTS_REDIRECT_REALVAULT'])) {
          $this->realvault = $config['ADDONPAYMENTS_REDIRECT_REALVAULT'];
        }
        if (isset($config['ADDONPAYMENTS_OFFER_SAVE_CARD']))
        {
          $this->offer_save_card = $config['ADDONPAYMENTS_OFFER_SAVE_CARD'];
        }
        
        $this->messages['error'] = false;

        $this->messages['rebate_password_empty'] = $this->l('The rebate password can not be empty');

        $this->messages['rebate_connection_error'] = $this->l('There was an error durying the request of the rebate action, please contact to your provider');

        parent::__construct();

        $this->displayName = $this->l('AddonPayments Official');
        $this->description = $this->l('Este módulo le permite aceptar pagos a través de la forma de pago Addon Payments.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall my module?');

        if (!isset($this->merchant_id) || empty($this->shared_secret) || !isset($this->settlement) || !isset($this->realvault)) {
          $this->warning = $this->l('AddonPayments must be configured before using it.');
        }

        if (!$this->getTableAccount()) {
          $this->warning = $this->l('You have to configure at least one subaccount');
        }
        if (!count(Currency::checkPaymentCurrencies($this->id))) {
          $this->warning = $this->l('No currency has been set for this module.');
        }
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        if (extension_loaded('curl') == false) {
          $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
          return false;
        }

        if (!Configuration::get('PS_REWRITING_SETTINGS')) {
          $this->_errors[] = $this->l('URL Rewriting must be enabled before using this module.');
          return false;
        }

        include(dirname(__FILE__) . '/sql/install.php');

        foreach ($this->context->controller->_languages as $language) {
          Configuration::updateGlobalValue('ADDONPAYMENTS_CARD_PAYMENT_BUTTON_'.$language['id_lang'], '');
        }

        return parent::install() &&
                $this->registerHook('header') &&
                $this->registerHook('payment') &&
                $this->registerHook('paymentReturn') && 
                $this->registerHook('adminOrderLeft') &&
                Configuration::updateValue('ADDONPAYMENTS_REDIRECT_SETTLEMENT', true) &&
                Configuration::updateValue('ADDONPAYMENTS_REDIRECT_REALVAULT', '0') &&
                Configuration::updateGlobalValue('ADDONPAYMENTS_CARD_PAYMENT_BUTTON', '') &&
                Configuration::updateGlobalValue('ADDONPAYMENTS_FIRST_PAYMENT', 0);
    }

    public function uninstall()
    {
        include(dirname(__FILE__) . '/sql/uninstall.php');

        foreach ($this->context->controller->_languages as $language) {
          Configuration::deleteByName('ADDONPAYMENTS_CARD_PAYMENT_BUTTON_'.$language['id_lang']);
        }

        return parent::uninstall() &&
                Configuration::deleteByName('ADDONPAYMENTS_MERCHANT_ID') &&
                Configuration::deleteByName('ADDONPAYMENTS_TEST_MERCHANT_ID') &&
                Configuration::deleteByName('ADDONPAYMENTS_SHARED_SECRET') &&
                Configuration::deleteByName('ADDONPAYMENTS_TEST_SHARED_SECRET') &&
                Configuration::deleteByName('ADDONPAYMENTS_REBATE_PASSWORD') &&
                Configuration::deleteByName('ADDONPAYMENTS_REDIRECT_SETTLEMENT') &&
                Configuration::deleteByName('ADDONPAYMENTS_REDIRECT_SUBACCOUNT') &&
                Configuration::deleteByName('ADDONPAYMENTS_REDIRECT_REALVAULT') &&
                Configuration::deleteByName('ADDONPAYMENTS_FIRST_PAYMENT');
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        $submitted = false;
        if (((bool) Tools::isSubmit('submitAddonpaymentsModule')) == true)
        {
            $submitted = true;
          $this->postProcess();
        }
        if (((bool) Tools::isSubmit('submitListAddonpaymentsModule')) == true)
        {
            $submitted = true;
          $this->postProcessList();
        }
        if (((bool) Tools::isSubmit('submitUpdateSubaccount')) == true)
        {
            $submitted = true;
          $this->postProcessListUpdate();
        }

        if (((bool) Tools::isSubmit('deleteaddonpayments_subaccount')) == true)
        {
            $submitted = true;
          $this->postProcessListDelete();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        if (Shop::getContext() != Shop::CONTEXT_SHOP || Shop::getContextShopID() == null) {
            return $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure_all_shops.tpl');
        }

        $output = '';

        if ($submitted) {
            $output = $this->displayConfirmation($this->l('Settings updated successfully.'));
        }

        $output .= $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');

        $output .= $this->renderForm();


        if (count($this->getTableAccount()))
        {
          $output .= $this->renderList();
        }
        $output .= $this->renderFormList();

        return $output;
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitAddonpaymentsModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
                . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Environment'),
                        'name' => 'ADDONPAYMENTS_URLTPV',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'desc' => $this->l('Enter your test merchant ID'),
                        'name' => 'ADDONPAYMENTS_TEST_MERCHANT_ID',
                        'label' => $this->l('Merchant Test ID'),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'ADDONPAYMENTS_TEST_SHARED_SECRET',
                        'label' => $this->l('Test shared secret key'),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'desc' => $this->l('Enter your merchand ID'),
                        'name' => 'ADDONPAYMENTS_MERCHANT_ID',
                        'label' => $this->l('Merchand ID'),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'ADDONPAYMENTS_SHARED_SECRET',
                        'label' => $this->l('Shared secret key'),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'ADDONPAYMENTS_REBATE_PASSWORD',
                        'label' => $this->l('Rebate password'),
                    ),
                    /* TO DO */
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Settlement'),
                        'name' => 'ADDONPAYMENTS_REDIRECT_SETTLEMENT',
                        'is_bool' => true,
                        'desc' => $this->l('If you are using DCC the settlement type will be automatically set to Auto'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('RealVault'), //tokenization, this field is meant to choose if we want to use the tokenization
                        'name' => 'ADDONPAYMENTS_REDIRECT_REALVAULT',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Offer Save card?'),
                        'name' => 'ADDONPAYMENTS_OFFER_SAVE_CARD', //this will only work if we have the above field (tokenization) enabled and is meant to choose if the user will have the choice of Save card or not, if its set to no and tokenization set to Yes, it means users will always get their card sotred and remembered
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                  array(
                        'type' => 'text',
                        'label' => $this->l('Card payment button text'),
                        'name' => 'ADDONPAYMENTS_CARD_PAYMENT_BUTTON',
                        'lang' => true,
                        'desc' => $this->l('This is the text that will show up on the button of the transaction form'),
                        'required' => false,
                        'col' => 4
                    ),
                  array(
                        'type' => 'switch',
                        'label' => $this->l('Set the addonpayments module as the first in positions for all your payment methods'),
                        'name' => 'ADDONPAYMENTS_FIRST_PAYMENT',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        $config = Configuration::getMultiple(array(
                    'ADDONPAYMENTS_URLTPV',
                    'ADDONPAYMENTS_MERCHANT_ID',
                    'ADDONPAYMENTS_TEST_MERCHANT_ID',
                    'ADDONPAYMENTS_SHARED_SECRET',
                    'ADDONPAYMENTS_TEST_SHARED_SECRET',
                    'ADDONPAYMENTS_REBATE_PASSWORD',
                    'ADDONPAYMENTS_REDIRECT_SETTLEMENT',
                    'ADDONPAYMENTS_REDIRECT_SUBACCOUNT',
                    'ADDONPAYMENTS_REDIRECT_REALVAULT',
                    'ADDONPAYMENTS_OFFER_SAVE_CARD',
                    'ADDONPAYMENTS_REDIRECT_CVN',
                    'ADDONPAYMENTS_FIRST_PAYMENT',
                    )
        );
        foreach (Language::getLanguages() as $lang)
        {
          $config['ADDONPAYMENTS_CARD_PAYMENT_BUTTON'][$lang['id_lang']] = Configuration::get('ADDONPAYMENTS_CARD_PAYMENT_BUTTON_'.$lang['id_lang']);
        }
        return $config;
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();
        $html = false;
        $id_shop_group = Context::getContext()->shop->id_shop_group;
        $id_shop = Context::getContext()->shop->id;
        foreach (array_keys($form_values) as $key)
        {
            Configuration::updateValue($key, Tools::getValue($key), $html, $id_shop_group, $id_shop);
        }
        foreach (Language::getLanguages() as $lang) {
            Configuration::updateValue('ADDONPAYMENTS_CARD_PAYMENT_BUTTON_'.$lang['id_lang'], Tools::getValue('ADDONPAYMENTS_CARD_PAYMENT_BUTTON_'.$lang['id_lang']), $html, $id_shop_group, $id_shop);
        }
        $first_payment = Configuration::get('ADDONPAYMENTS_FIRST_PAYMENT');
        if ($first_payment) {
            AddonPaymentsActions::set_addonpayments_first();
        }
    }

    public function renderList()
    {
        $links = $this->getListTableAccount();

        $fields_list = array(
            'name_addonpayments_subaccount' => array(
                'title' => $this->l('SubAccount name'),
                'type' => 'text',
            ),
            'dcc_active' => array(
                'title' => $this->l('ACTIVE'),
                'type' => 'bool',
                'align' => 'center',
            ),
            '3d_secured' => array(
                'title' => $this->l('Secure'),
                'type' => 'bool',
                'align' => 'center',
            ),
        );

        $helper = new HelperList();
        $helper->shopLinkType = '';
        $helper->simple_header = true;
        $helper->identifier = 'id_addonpayments_subaccount';
        $helper->table = 'addonpayments_subaccount';
        $helper->actions = array('edit', 'delete');
        $helper->show_toolbar = false;
        $helper->module = $this;
        $helper->title = $this->l('Sub Accounts List');
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        return $helper->generateList($links, $fields_list);
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */

    protected function renderFormList()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        if (Tools::getIsset('updateaddonpayments_subaccount'))
        {
          $helper->submit_action = 'submitUpdateSubaccount';
        }
        else
        {
          $helper->submit_action = 'submitListAddonpaymentsModule';
        }
        
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
                . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormListValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );
        return $helper->generateForm(array($this->getConfigFormList()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigFormList()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => (Tools::getIsset('updateaddonpayments_subaccount') && !Tools::getValue('updateaddonpayments_subaccount')) ?
                    $this->l('Update a SubAccount') : $this->l('Add a new SubAccounts'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'desc' => $this->l('Name for Subaccount (default name Internet)'),
                        'name' => 'ADDONPAYMENTS_SUBACCOUNT_NAME',
                        'label' => $this->l('Subaccount'),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('ACTIVE'),
                        'name' => 'dcc_active',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('SECURE'),
                        'name' => '3d_secured',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => (Tools::getIsset('updateaddonpayments_subaccount') && !Tools::getValue('updateaddonpayments_subaccount')) ?
                    $this->l('Update') : $this->l('Save'),
                ),
            ),
        );

        if (Tools::isSubmit('updateaddonpayments_subaccount'))
        {
          $fields_form['form']['input'][] = array('type' => 'hidden', 'name' => 'updateSubaccount');
          $fields_form['form']['input'][] = array('type' => 'hidden', 'name' => 'id_addonpayments_subaccount');
        }

        return $fields_form;
    }

    /**
     * Set values for the inputs.
     */
    
    protected function getConfigFormListValues()
    {

        if (!Tools::isSubmit('updateaddonpayments_subaccount'))
        {
          $fields_form = array(
              'ADDONPAYMENTS_SUBACCOUNT_NAME' => Tools::getValue('ADDONPAYMENTS_SUBACCOUNT_NAME', ''),
              'ADDONPAYMENTS_SUBACCOUNT_3DSECURE' => Tools::getValue('ADDONPAYMENTS_SUBACCOUNT_3DSECURE', false),
              'ADDONPAYMENTS_SUBACCOUNT_DCC' => Tools::getValue('ADDONPAYMENTS_SUBACCOUNT_DCC', false),
              'ADDONPAYMENTS_SUBACCOUNT_DCC_CHOICE' => Tools::getValue('ADDONPAYMENTS_SUBACCOUNT_DCC_CHOICE', false), //we have to remove dcc later
              'dcc_active' => Tools::getValue('dcc_active', false),
              '3d_secured' => Tools::getValue('3d_secured', false),
          );
        }
        else
        {
          $fields_saved = $this->getTableAccount((int)Tools::getValue('id_addonpayments_subaccount'));
          $fields_form = array(
              'ADDONPAYMENTS_SUBACCOUNT_NAME' => isset($fields_saved['name_addonpayments_subaccount']) ? $fields_saved['name_addonpayments_subaccount'] : '',
              'ADDONPAYMENTS_SUBACCOUNT_3DSECURE' => isset($fields_saved['threeds_addonpayments_subaccount']) ? $fields_saved['threeds_addonpayments_subaccount'] : '',
              'ADDONPAYMENTS_SUBACCOUNT_DCC' => isset($fields_saved['dcc_addonpayments_subaccount']) ? $fields_saved['dcc_addonpayments_subaccount'] : '',
              'ADDONPAYMENTS_SUBACCOUNT_DCC_CHOICE' => isset($fields_saved['dcc_choice_addonpayments_subaccount']) ? $fields_saved['dcc_choice_addonpayments_subaccount'] : '',
              'id_addonpayments_subaccount' => Tools::getValue('id_addonpayments_subaccount'),
              'dcc_active' => isset($fields_saved['dcc_active']) ? $fields_saved['dcc_active'] : '',
              '3d_secured' => isset($fields_saved['3d_secured']) ? $fields_saved['3d_secured'] : '',
              'updateSubaccount' => '',
          );

        }
        return $fields_form;
    }

    /**
     * Save form data.
     */
    
    protected function postProcessList()
    {
        $form_values = $this->getConfigFormListValues();
        $id_shop = Context::getContext()->shop->id;
          if ((int) $form_values['dcc_active']) {
            Db::getInstance()->update('addonpayments_subaccount', array(
                    'dcc_active' => 0,
                        ), ' id_shop = ' . Context::getContext()->shop->id
                );
          }
          Db::getInstance()->insert('addonpayments_subaccount', array(
                      'name_addonpayments_subaccount' => pSQL($form_values['ADDONPAYMENTS_SUBACCOUNT_NAME']),
                      'threeds_addonpayments_subaccount' => (int) $form_values['ADDONPAYMENTS_SUBACCOUNT_3DSECURE'],
                      'dcc_addonpayments_subaccount' => (int) $form_values['ADDONPAYMENTS_SUBACCOUNT_DCC'],
                      'dcc_choice_addonpayments_subaccount' => (int) $form_values['ADDONPAYMENTS_SUBACCOUNT_DCC_CHOICE'] ? 1 : 2,
                      'dcc_active' => (int) $form_values['dcc_active'],
                      '3d_secured' => (int) $form_values['3d_secured'],
                      'id_shop' => $id_shop,
         ));
    }

    /**
     * Save Update SubAccount form data.
     */
    protected function postProcessListUpdate()
    {
        if ((int)Tools::getValue('dcc_active')) {
            Db::getInstance()->update('addonpayments_subaccount', array(
                    'dcc_active' => 0,
                        ), ' id_shop = ' . Context::getContext()->shop->id
                );
          }
        if (Db::getInstance()->update('addonpayments_subaccount', array(
                    'name_addonpayments_subaccount' => pSQL(Tools::getValue('ADDONPAYMENTS_SUBACCOUNT_NAME')),
                    'threeds_addonpayments_subaccount' => (int) Tools::getValue('ADDONPAYMENTS_SUBACCOUNT_3DSECURE'),
                    'dcc_addonpayments_subaccount' => (int) Tools::getValue('ADDONPAYMENTS_SUBACCOUNT_DCC'),
                    'dcc_choice_addonpayments_subaccount' => pSQL((int)Tools::getValue('ADDONPAYMENTS_SUBACCOUNT_DCC_CHOICE') ? 1 : 2),
                    '3d_secured' => (int) Tools::getValue('3d_secured'),
                    'dcc_active' => (int)Tools::getValue('dcc_active'),
                        ), 'id_addonpayments_subaccount = ' . (int) Tools::getValue('id_addonpayments_subaccount') . ' AND id_shop = ' . Context::getContext()->shop->id
                )
        ) {
          return true;
        } else {
          return false;
        }
    }

    /**
     * Save Update SubAccount form data.
     */
    protected function postProcessListDelete()
    {
        Db::getInstance()->delete('addonpayments_subaccount', 'id_addonpayments_subaccount = ' . (int) Tools::getValue('id_addonpayments_subaccount') . ' AND id_shop = ' . Context::getContext()->shop->id);
    }

    /**
     * Return all subaccounts set by the merchant
     * 
     * @return array
     */
    public function getTableAccount($id_addonpayments_subaccount = 0)
    {
        if ($this->active)
        {
          $sql = new DbQuery();
          $sql->from('addonpayments_subaccount', 'asb');
          $sql->where('asb.id_addonpayments_subaccount = ' . (int) $id_addonpayments_subaccount . ' AND asb.id_shop = ' . Context::getContext()->shop->id);
          $mergesqls = array();
          $mergesqls = Db::getInstance()->getRow($sql);
          return $mergesqls;
        }
        else
        {
          return false;
        }
    }

    /**
     * Return all subaccounts set by the merchant for listing
     * 
     * @return array
     */
    public function getListTableAccount()
    {
        $sql = 'SELECT id_addonpayments_subaccount, '
                . 'name_addonpayments_subaccount, '
                . 'threeds_addonpayments_subaccount, '
                . 'dcc_addonpayments_subaccount, '
                . 'dcc_choice_addonpayments_subaccount,'
                . 'dcc_active,'
                . '3d_secured'
                . ' FROM `'._DB_PREFIX_.'addonpayments_subaccount` '
                . 'WHERE id_shop = '.Context::getContext()->shop->id;//.' GROUP BY asb.id_shop';
        return Db::getInstance()->executeS($sql);
    }

    /**
     * Return formatted amount without '.'
     * 
     * @param string $total from RealexRedirectPaymentModuleFrontController::initContent()
     * @return string
     */
    public function getAmountFormat($total)
    {
        $tab = explode('.', $total);
        if (count($tab) == 1)
          return $tab[0] . '00';
        else
        {
          if (Tools::strlen(($tab[1])) == 1)
            $total = $tab[0] . $tab[1] . '0';
          else
            $total = $tab[0] . $tab[1];
        }
        return $total;
    }

    /**
     * Return list of all cards type set by the merchant for customer display
     * @return array
     */
    public function getSelectAccount()
    {
        $accounts = $this->getTableAccount();
        return $accounts;
    }

    /**
     * Return translate transaction result message
     * 
     * @param string $result from realexredirect::manageOrder();
     * @return string
     */
    public function getMsg($result = null)
    {
        switch ($result) {
          case '00':
            $retour = $this->l('Payment authorised successfully');
            break;
          case $result >= 300 && $result < 400:
            $retour = $this->l('Error with Addon Payments systems');
            break;
          case $result >= 500 && $result < 600:
            $retour = $this->l('Incorrect XML message formation or content');
            break;
          case '666':
            $retour = $this->l('Client deactivated.');
            break;
          case 'fail_liability': //(liability is the 3dsecure autentification)
            $retour = $this->l('3D Secure authentication failure');
            break;
          case '101':
          case '102':
          case '103':
          case $result >= 200 && $result < 300:
          case '999':
          default:
            $retour = $this->l('An error occured during payment.');
            break;
        }
        return $retour;
    }

    /**
     * Return translate AVS result message
     * @param string $response from realexredirect::manageOrder();
     * @return string
     */
    public function getAVSresponse($response = null)
    {
        switch ($response) {
          case 'M':
            $retour = $this->l('Matched');
            break;
          case 'N':
            $retour = $this->l('Not Matched');
            break;
          case 'I':
            $retour = $this->l('Problem with check');
            break;
          case 'U':
            $retour = $this->l('Unable to check (not certified etc)');
            break;
          case 'P':
            $retour = $this->l('Partial Match');
            break;
          case 'EE':
          default :
            $retour = $this->l('Error Occured');
            break;
        }
        return $retour;
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module))
          foreach ($currencies_module as $currency_module)
            if ($currency_order->id == $currency_module['id_currency'])
              return true;
        return false;
    }

    /**
     * Add the CSS & JavaScript files you want to be loaded in the BO.
     */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
          $this->context->controller->addJS($this->_path . 'views/js/back.js');
          $this->context->controller->addCSS($this->_path . 'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addCSS($this->_path . '/views/css/front.css');
    }

    /**
     * This method is used to render the payment button,
     * Take care if the button should be displayed or not.
     */
    public function hookPayment($params)
    {
        if (!$this->checkCurrency($params['cart']))
          return;

        $this->smarty->assign('module_dir', $this->_path);

        return $this->display(__FILE__, 'views/templates/hook/payment.tpl');
    }

    /**
     * This hook is used to display the order confirmation page.
     */
    public function hookPaymentReturn($params)
    {
        if ($this->active == false)
          return;

        $order = $params['objOrder'];

        if ($order->getCurrentOrderState()->id != Configuration::get('PS_OS_ERROR'))
          $this->smarty->assign('status', 'ok');

        $this->smarty->assign(array(
            'id_order' => $order->id,
            'reference' => $order->reference,
            'params' => $params,
            'total' => Tools::displayPrice($params['total_to_pay'], $params['currencyObj'], false),
        ));

        return $this->display(__FILE__, 'views/templates/hook/confirmation.tpl');
    }

    public function hookDisplayAdminOrderLeft($params)
    {
        $current_order = new Order((int)$params['id_order']);
        if (trim($current_order->payment) != 'AddonPayments Official') {
          return;
        }

        $output = '';
        $rebated_order = false;
        $max_total = (float)(Tools::ps_round($current_order->total_paid_real,2));
        if (Tools::isSubmit('rebate_order_addonpayments')) {
          $rebate_import = Tools::getValue('import_to_rebate');
          $rebate_import = floatval(str_replace(',', '.', $rebate_import));
          if($rebate_import > 0 && $rebate_import <= $max_total) {
              $rebate_result = AddonPaymentsActions::rebateOrder((int)$current_order->id, $rebate_import);
              if (!$rebate_result['error']) {
                  $output .= $this->displayConfirmation('<span id="addonpayments_show_message">' . $this->l('The rebate action has been successfully done.') . '</span>');
              } else {
                  $output .= $this->displayConfirmation('<span id="addonpayments_show_message">' . $this->l('An error has ocurred durying the process of rebate.') . ' ' . $rebate_result['message']);
              }
          }
        }

        $addonPaymentsOrderStatus = AddonPaymentsActions::existOrder((int)$current_order->id);
        
        if (!$addonPaymentsOrderStatus) {
          $output .= $this->displayConfirmation('<span id="addonpayments_show_message">' . $this->l('This order was not found in the addonPayments database register, please, contact with our support'));
              return $output;
        } else {
            if ($addonPaymentsOrderStatus == 'r') {
                $rebated_order = true;
                $this->context->smarty->assign('rebated_order', $rebated_order);
                $output .= $this->display(__FILE__, 'views/templates/admin/backoffice_order.tpl');
                return $output;
            }
        }
        $this->context->smarty->assign('max_total', $max_total);
        $this->context->smarty->assign('rebated_order', $rebated_order);
        $output .= $this->display(__FILE__, 'views/templates/admin/backoffice_order.tpl');
        return $output;
      }
}