{*
* 2007-2017 PrestaShop
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
*  @copyright 2007-2017 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

{capture name=path}{l s='Order Payment' d='Modules.Addonpayments.Shop'}{/capture}
<div>
    <h3>{l s='Order summary' d='Modules.Addonpayments.Shop'}:</h3>

    {assign var='current_step' value='payment'}
    {include file="$tpl_dir./order-steps.tpl"}

    {if $nbProducts <= 0}
        <ul class="alert alert-info">
            <li>{l s='Your shopping cart is empty.' d='Modules.Addonpayments.Shop'}.</li>
        </ul>
    {else}
        {if isset($error)}
            <div class="alert alert-danger">
                {$error|escape:'htmlall':'UTF-8'}
            </div>
        {/if}
        <p>
            <strong>{l s='You have chosen to pay by Credit or Debit card.' d='Modules.Addonpayments.Shop'}</strong>
        </p>
        <p>
            {l s='The total amount of your order is' d='Modules.Addonpayments.Shop'}
            <span id="amount" class="price">{displayPrice price=$total}</span>
            {if $use_taxes == 1}
                {l s='(tax incl.)' d='Modules.Addonpayments.Shop'}
            {/if}
        </p>
        {if $realvault=="1" && $payer_exists=="1"}
            <div class="bloc_registered_card">
                <h4>{l s='Registered card' d='Modules.Addonpayments.Shop'}</h4>
                {if !empty($error)} <br/><span class="error">{$error|escape:'htmlall':'UTF-8'}</span><br/><br/>{/if}
                {if !empty($input_registered)}
                    {$input_registered|escape:'':'UTF-8'}
                {else}
                    {l s='No card registered' d='Modules.Addonpayments.Shop'}
                {/if}
            </div>
        {/if}
        <div class="bloc_new_card">
            <form action="{$submit_new|escape:'htmlall':'UTF-8'}" method="post">
                <h4>{l s='New card' d='Modules.Addonpayments.Shop'}</h4>
                {l s='Please select your card type' d='Modules.Addonpayments.Shop'}<br/> 
                {*<select name='ACCOUNT'>
                    {foreach from=$selectAccount item=account}
                        <option value='{$account['account']|escape:'htmlall':'UTF-8'}'>
                            {if $account['card']=="MC"}
                                {l s='MASTERCARD' d='Modules.Addonpayments.Shop'}
                            {elseif $account['card']=="AMEX"}
                                {l s='AMERICAN EXPRESS' d='Modules.Addonpayments.Shop'}
                            {else}
                                {$account['card']|escape:'htmlall':'UTF-8'}
                            {/if}
                        </option>
                    {/foreach}
                </select>*}
                {$input_new|escape:'':'UTF-8'}
            </form>
        </div>
        <div style="padding-top:10px; padding-bottom:10px">
            <a href="{$link->getPageLink('order', true, NULL, "step=3")|escape:'htmlall':'UTF-8'}" class="button_large">{l s='Other payment methods' d='Modules.Addonpayments.Shop'}</a>
        </div>
    {/if}
</div>