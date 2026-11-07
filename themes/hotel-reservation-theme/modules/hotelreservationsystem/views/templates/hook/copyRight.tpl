{*
* Lush View Hotel - theme-level override of
* modules/hotelreservationsystem/views/templates/hook/copyRight.tpl
*
* QloApps resolves this file BEFORE the module's own copy
* (classes/module/Module.php:2433-2438, _isTemplateOverloadedStatic), so the
* module and the core stay untouched and the change survives module updates.
*
* The only difference from the upstream template is that the trailing
* "| Powered by QloApps" text and its link to qloapps.com are removed, at the
* owner's explicit instruction - decision D6 in
* docs/project/06-branding-plan.md. Everything else (the establishment year,
* the chain name, which 1.8.0 feeds from PS_SHOP_NAME, and the escaping) is
* identical to upstream.
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
*
*  @author    Lush View Hotel
*  @copyright 2026 Lush View Hotel
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  Upstream template: Webkul / PrestaShop SA, Academic Free License (AFL 3.0)
*}
<div class="copyRightWrapper">
	<p class="copyRight">
		&copy;{if isset($WK_HTL_ESTABLISHMENT_YEAR) && $WK_HTL_ESTABLISHMENT_YEAR} {$WK_HTL_ESTABLISHMENT_YEAR}-{'Y'|date}&nbsp;{/if}<a href="{$base_dir}">&nbsp;{$WK_HTL_CHAIN_NAME|escape:'html':'UTF-8'}</a>.&nbsp;{l s=' All rights reserved.' mod='hotelreservationsystem'}
	</p>
</div>
