{*
* Lush View Hotel - Accountant Daily Flash, Tender Reconciliation, VIP & Hygiene View
*
* @author    Lush View Hotel
* @copyright 2026 Lush View Hotel
* @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
*}

<div class="panel">
    <div class="panel-heading">
        <i class="icon-bar-chart"></i> {l s='Hospitality Flash & Reconciliation Period' mod='lvhreports'}
    </div>
    <form method="post" action="{$lvh_controller_url|escape:'html':'UTF-8'}" class="form-inline">
        <div class="form-group" style="margin-right: 10px;">
            <label>{l s='From:' mod='lvhreports'}</label>
            <input type="date" name="date_from" value="{$lvh_date_from|escape:'html':'UTF-8'}" class="form-control" />
        </div>
        <div class="form-group" style="margin-right: 10px;">
            <label>{l s='To:' mod='lvhreports'}</label>
            <input type="date" name="date_to" value="{$lvh_date_to|escape:'html':'UTF-8'}" class="form-control" />
        </div>
        <button type="submit" name="submitFilterFlash" value="1" class="btn btn-primary">
            <i class="icon-refresh"></i> {l s='Update Flash Report' mod='lvhreports'}
        </button>
        <button type="submit" name="exportReconciliationCsv" value="1" class="btn btn-default">
            <i class="icon-download"></i> {l s='Export Tender CSV' mod='lvhreports'}
        </button>
    </form>
</div>

{* TRUE 34-ROOM HOSPITALITY KPI FLASH *}
<div class="row" style="margin-bottom: 12px;">
    <div class="col-md-2 col-sm-4">
        <div class="panel" style="padding: 12px;">
            <small class="text-muted">{l s='34-Room Occupancy' mod='lvhreports'}</small>
            <h3 style="margin: 4px 0 0; color:#1d4ed8;">{$lvh_flash.occupancy_pct}%</h3>
            <small class="text-muted">{$lvh_flash.sold_nights|intval} / {$lvh_flash.available_nights|intval} rm-nights</small>
        </div>
    </div>
    <div class="col-md-2 col-sm-4">
        <div class="panel" style="padding: 12px;">
            <small class="text-muted">{l s='ADR (Avg Daily Rate)' mod='lvhreports'}</small>
            <h3 style="margin: 4px 0 0;">GHS {$lvh_flash.adr|string_format:"%.2f"}</h3>
            <small class="text-muted">{l s='Excludes CON-* Hall' mod='lvhreports'}</small>
        </div>
    </div>
    <div class="col-md-2 col-sm-4">
        <div class="panel" style="padding: 12px;">
            <small class="text-muted">{l s='RevPAR (34 Rooms)' mod='lvhreports'}</small>
            <h3 style="margin: 4px 0 0;">GHS {$lvh_flash.revpar|string_format:"%.2f"}</h3>
            <small class="text-muted">{$lvh_flash.days|intval} {l s='days' mod='lvhreports'}</small>
        </div>
    </div>
    <div class="col-md-2 col-sm-4">
        <div class="panel" style="padding: 12px;">
            <small class="text-muted">{l s='Bedroom Revenue' mod='lvhreports'}</small>
            <h3 style="margin: 4px 0 0; color:#15803d;">GHS {$lvh_flash.room_revenue|string_format:"%.0f"}</h3>
            <small class="text-muted">{l s='Flat GHS Tariffs' mod='lvhreports'}</small>
        </div>
    </div>
    <div class="col-md-2 col-sm-4">
        <div class="panel" style="padding: 12px;">
            <small class="text-muted">{l s='Conference Hall' mod='lvhreports'}</small>
            <h3 style="margin: 4px 0 0; color:#7c3aed;">GHS {$lvh_flash.conference_revenue|string_format:"%.0f"}</h3>
            <small class="text-muted">{l s='Shared Hall' mod='lvhreports'}</small>
        </div>
    </div>
    <div class="col-md-2 col-sm-4">
        <div class="panel" style="padding: 12px;">
            <small class="text-muted">{l s='Folio Extras' mod='lvhreports'}</small>
            <h3 style="margin: 4px 0 0; color:#b45309;">GHS {$lvh_flash.extras_revenue|string_format:"%.0f"}</h3>
            <small class="text-muted">{l s='F&B / Laundry / Late' mod='lvhreports'}</small>
        </div>
    </div>
</div>

<div class="row">
    {* NORMALIZED TENDER RECONCILIATION *}
    <div class="col-md-6">
        <div class="panel">
            <div class="panel-heading">
                <i class="icon-money"></i> {l s='Canonical Tender Reconciliation (Normalized)' mod='lvhreports'}
                <span class="pull-right"><strong>GHS {$lvh_tenders.total_collected|string_format:"%.2f"}</strong></span>
            </div>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>{l s='Canonical Tender' mod='lvhreports'}</th>
                            <th class="text-right">{l s='Count' mod='lvhreports'}</th>
                            <th class="text-right">{l s='Missing Ref' mod='lvhreports'}</th>
                            <th class="text-right">{l s='Collected (GHS)' mod='lvhreports'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {if empty($lvh_tenders.rows)}
                            <tr>
                                <td colspan="4" class="text-center text-muted">{l s='No payments recorded in this date window.' mod='lvhreports'}</td>
                            </tr>
                        {else}
                            {foreach from=$lvh_tenders.rows item=tr}
                                <tr>
                                    <td><strong>{$tr.label|escape:'html':'UTF-8'}</strong></td>
                                    <td class="text-right">{$tr.tx_count|intval}</td>
                                    <td class="text-right">
                                        {if $tr.missing_ref_count > 0}
                                            <span class="label label-warning">{$tr.missing_ref_count|intval}</span>
                                        {else}
                                            0
                                        {/if}
                                    </td>
                                    <td class="text-right"><strong>{$tr.amount|string_format:"%.2f"}</strong></td>
                                </tr>
                            {/foreach}
                        {/if}
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {* UNPAID / PARTIALLY PAID AGING LIST *}
    <div class="col-md-6">
        <div class="panel">
            <div class="panel-heading">
                <i class="icon-warning-sign"></i> {l s='Open-Balance & Partial-Payment Orders (Aging)' mod='lvhreports'}
                <span class="badge">{$lvh_open_folios|@count}</span>
            </div>
            <div class="table-responsive" style="max-height: 340px; overflow-y: auto;">
                <table class="table table-condensed table-hover">
                    <thead>
                        <tr>
                            <th>{l s='Order' mod='lvhreports'}</th>
                            <th>{l s='Guest' mod='lvhreports'}</th>
                            <th class="text-right">{l s='Age' mod='lvhreports'}</th>
                            <th class="text-right">{l s='Balance Due' mod='lvhreports'}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {if empty($lvh_open_folios)}
                            <tr>
                                <td colspan="5" class="text-center text-success">{l s='All valid orders are settled in full!' mod='lvhreports'}</td>
                            </tr>
                        {else}
                            {foreach from=$lvh_open_folios item=of}
                                <tr>
                                    <td>
                                        <strong>{$of.reference|escape:'html':'UTF-8'}</strong><br>
                                        <small class="text-muted">{$of.date_add|date_format:"%d %b %Y"}</small>
                                    </td>
                                    <td>
                                        {$of.guest_name|escape:'html':'UTF-8'}
                                        {if $of.phone}<br><small>{$of.phone|escape:'html':'UTF-8'}</small>{/if}
                                    </td>
                                    <td class="text-right">{$of.age_days|intval}d</td>
                                    <td class="text-right"><strong style="color:#dc2626;">GHS {$of.balance_due|string_format:"%.2f"}</strong></td>
                                    <td class="text-right">
                                        <a href="{$lvh_order_base_url|escape:'html':'UTF-8'}&id_order={$of.id_order|intval}&vieworder" class="btn btn-default btn-xs">
                                            {l s='Settle' mod='lvhreports'}
                                        </a>
                                    </td>
                                </tr>
                            {/foreach}
                        {/if}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{* VIP REPEAT GUEST LEADERBOARD *}
<div class="panel">
    <div class="panel-heading">
        <i class="icon-star"></i> {l s='Lush View Loyal & VIP Repeat Guests (3+ Stays)' mod='lvhreports'}
    </div>
    <div class="table-responsive">
        <table class="table table-striped table-condensed">
            <thead>
                <tr>
                    <th>{l s='Guest Name' mod='lvhreports'}</th>
                    <th>{l s='Tier' mod='lvhreports'}</th>
                    <th>{l s='Phone' mod='lvhreports'}</th>
                    <th class="text-right">{l s='Completed Stays' mod='lvhreports'}</th>
                    <th class="text-right">{l s='Lifetime Spend (GHS)' mod='lvhreports'}</th>
                    <th>{l s='Last Visit' mod='lvhreports'}</th>
                </tr>
            </thead>
            <tbody>
                {if empty($lvh_vip_guests)}
                    <tr>
                        <td colspan="6" class="text-center text-muted">{l s='No repeat guests found yet.' mod='lvhreports'}</td>
                    </tr>
                {else}
                    {foreach from=$lvh_vip_guests item=vg}
                        <tr>
                            <td><strong>{$vg.guest_name|escape:'html':'UTF-8'}</strong></td>
                            <td>
                                {if $vg.stays_count >= 15}
                                    <span class="label label-warning">Platinum VIP</span>
                                {elseif $vg.stays_count >= 5}
                                    <span class="label label-success">VIP Regular</span>
                                {else}
                                    <span class="label label-info">Returning</span>
                                {/if}
                            </td>
                            <td>{$vg.phone|escape:'html':'UTF-8'}</td>
                            <td class="text-right"><strong>{$vg.stays_count|intval}</strong></td>
                            <td class="text-right">GHS {$vg.lifetime_spend|string_format:"%.2f"}</td>
                            <td>{$vg.last_stay|date_format:"%d %b %Y"}</td>
                        </tr>
                    {/foreach}
                {/if}
            </tbody>
        </table>
    </div>
</div>

{* SUPERADMIN / IT 1-CLICK LIVE SYSTEM HYGIENE REMEDIATION *}
{if $lvh_can_hygiene}
    <div class="panel" style="border-left: 4px solid #7c3aed;">
        <div class="panel-heading">
            <i class="icon-wrench"></i> {l s='SuperAdmin / IT — 1-Click Production Hygiene Remediations' mod='lvhreports'}
        </div>
        <div class="row">
            <div class="col-md-3">
                <div class="well well-sm">
                    <strong>1. {l s='Foreign/US Tax Cleanup' mod='lvhreports'}</strong>
                    <p class="text-muted" style="font-size:12px; margin:6px 0;">
                        {l s='Active tax rows:' mod='lvhreports'} <strong>{$lvh_hygiene.active_taxes|intval}</strong>
                        ({l s='Flat GHS rates use tax group 0' mod='lvhreports'})
                    </p>
                    <form method="post" action="{$lvh_controller_url|escape:'html':'UTF-8'}">
                        <button type="submit" name="hygieneDisableForeignTaxes" value="1" class="btn btn-xs {if $lvh_hygiene.active_taxes > 0}btn-warning{else}btn-default{/if} btn-block">
                            {l s='Deactivate Unused Taxes' mod='lvhreports'}
                        </button>
                    </form>
                </div>
            </div>
            <div class="col-md-3">
                <div class="well well-sm">
                    <strong>2. {l s='Stop Admin Email Spam' mod='lvhreports'}</strong>
                    <p class="text-muted" style="font-size:12px; margin:6px 0;">
                        {if $lvh_hygiene.admin_spam_enabled}
                            <span class="text-danger">{l s='Active (causes 550 Mailbox Full)' mod='lvhreports'}</span>
                        {else}
                            <span class="text-success">{l s='Clean (Disabled)' mod='lvhreports'}</span>
                        {/if}
                    </p>
                    <form method="post" action="{$lvh_controller_url|escape:'html':'UTF-8'}">
                        <button type="submit" name="hygieneStopAdminMailSpam" value="1" class="btn btn-xs {if $lvh_hygiene.admin_spam_enabled}btn-danger{else}btn-default{/if} btn-block">
                            {l s='Disable Internal Order Mail Spam' mod='lvhreports'}
                        </button>
                    </form>
                </div>
            </div>
            <div class="col-md-3">
                <div class="well well-sm">
                    <strong>3. {l s='Orphan & CVE Module Cleanup' mod='lvhreports'}</strong>
                    <p class="text-muted" style="font-size:12px; margin:6px 0;">
                        {l s='Orphan cashpayment / active qlohotelreview:' mod='lvhreports'} <strong>{$lvh_hygiene.orphan_or_vuln_modules|intval}</strong>
                    </p>
                    <form method="post" action="{$lvh_controller_url|escape:'html':'UTF-8'}">
                        <button type="submit" name="hygieneCleanOrphanModules" value="1" class="btn btn-xs {if $lvh_hygiene.orphan_or_vuln_modules > 0}btn-warning{else}btn-default{/if} btn-block">
                            {l s='Purge Orphan & Disable CVE' mod='lvhreports'}
                        </button>
                    </form>
                </div>
            </div>
            <div class="col-md-3">
                <div class="well well-sm">
                    <strong>4. {l s='Lush View Brand Defaults' mod='lvhreports'}</strong>
                    <p class="text-muted" style="font-size:12px; margin:6px 0;">
                        <code>{$lvh_hygiene.shop_name|escape:'html':'UTF-8'}</code> / <code>{$lvh_hygiene.header_title|escape:'html':'UTF-8'}</code>
                    </p>
                    <form method="post" action="{$lvh_controller_url|escape:'html':'UTF-8'}">
                        <button type="submit" name="hygieneFixBrandingDefaults" value="1" class="btn btn-xs {if $lvh_hygiene.branding_needs_fix}btn-primary{else}btn-default{/if} btn-block">
                            {l s='Apply Lush View Hotel Titles' mod='lvhreports'}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
{/if}
