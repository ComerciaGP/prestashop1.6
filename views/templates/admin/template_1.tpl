{*
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
*}

<div class="panel">
    <div class="row caixabankpayment-header">
        <img src="{$module_dir|escape:'html':'UTF-8'}views/img/addon-payments.png" class="col-xs-12 col-md-4 text-center" id="payment-logo" />
    </div>

    <hr />

    <div class="caixabankpayment-content">
        <div class="row">
            <div class="col-md-6">
                <h5>{l s='This payment module offers the following benefits' mod='addonpayments'}</h5>
                <dl>
                    <dt>&middot; {l s='Increase customer payment options' mod='addonpayments'}</dt>
                    <dd>{l s='Visa®, Mastercard® and more.' mod='addonpayments'}</dd>

                    <dt>&middot; {l s='Help to improve cash flow' mod='addonpayments'}</dt>
                    <dd>{l s='Receive funds quickly from the bank.' mod='addonpayments'}</dd>
                </dl>
            </div>

            <div class="col-md-6">
                <h5>{l s='La Caixa® Module' mod='addonpayments'}</h5>
                <ul>
                    <li>{l s='Simple, secure and reliable solution to process online payments' mod='addonpayments'}</li>
                    <li>{l s='Virtual terminal' mod='addonpayments'}</li>
                    <li>{l s='24/7/365 customer support' mod='addonpayments'}</li>
                </ul>
                <br />
            </div>
        </div>
    </div>
</div>
