{**
 * 2019 Packlink
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
 * @author    Packlink <support@packlink.com>
 * @copyright 2019 Packlink Shipping S.L
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *}

<div class="pl-login-page" id="pl-main-page-holder">
  <div class="pl-login-page-side-img-wrapper pl-collapse">
    <img
            src="{html_entity_decode($loginIcon|escape:'html':'UTF-8')}"
            class="pl-login-icon"
            alt="{l s='Packlink PRO Shipping' mod='packlink'}"
    >
  </div>
  <div class="pl-login-page-content-wrapper">

    <div class="pl-register-form-wrapper">
      <div class="pl-register-btn-section-wrapper">
        {l s='Don\'t have an account?' mod='packlink'}
        <button type="button" id="pl-register-btn"
                class="btn btn-primary btn-lg"><i class="material-icons">account_circle</i>
          {l s='Register' mod='packlink'}
        </button>
      </div>
      <div class="pl-register-country-section-wrapper" id="pl-register-form">
        <div class="pl-register-form-close-btn">
          <i class="material-icons" id="pl-register-form-close-btn">close</i>
        </div>
        <div class="pl-register-country-title-wrapper">
          {l s='Select country to start' mod='packlink'}
        </div>
        <div class="pl-register-country-list-wrapper">
          <a href="https://pro.packlink.es/registro" target="_blank">
            <div class="pl-country">
              <img src="{html_entity_decode($iconPath|escape:'html':'UTF-8')}spain.svg">
              es
            </div>
          </a>
          <a href="https://pro.packlink.de/registrieren" target="_blank">
            <div class="pl-country">
              <img src="{html_entity_decode($iconPath|escape:'html':'UTF-8')}germany.svg">
              de
            </div>
          </a>
          <a href="https://pro.packlink.fr/inscription" target="_blank">
            <div class="pl-country">
              <img src="{html_entity_decode($iconPath|escape:'html':'UTF-8')}france.svg">
              fr
            </div>
          </a>
          <a href="https://pro.packlink.it/registro" target="_blank">
            <div class="pl-country">
              <img src="{html_entity_decode($iconPath|escape:'html':'UTF-8')}italy.svg">
              it
            </div>
          </a>
        </div>
      </div>
    </div>
    <div>
      <div class="pl-login-form-header">
        <div class="pl-login-form-title-wrapper">
          {l s='Allow PrestaShop to connect to PacklinkPRO' mod='packlink'}
        </div>
        <div class="pl-login-form-text-wrapper">
          {l s='Your API key can be found under' mod='packlink'}
          pro.packlink/<strong>Settings/PacklinkPROAPIkey</strong>
        </div>
      </div>
      <div class="pl-login-form-label-wrapper">
        {l s='Connect your account' mod='packlink'}
      </div>
      <form method="POST">
        <div class="pl-login-form-wrapper">
          <fieldset class="form-group pl-form-section-input pl-text-input">
            <input type="text" class="form-control" id="pl-login-api-key" name="api_key" required/>
            <span class="pl-text-input-label">{l s='Api key' mod='packlink'}</span>
          </fieldset>
        </div>
        <div>
          <button type="submit" name="login" class="btn btn-primary btn-lg">{l s='Log in' mod='packlink'}</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script type="application/javascript">
  Packlink.utilityService.configureInputElements();
  hidePrestaSpinner();
  initRegisterForm();
  calculateContentHeight(0);
</script>