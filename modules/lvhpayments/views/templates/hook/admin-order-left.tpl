{*
* Lush View Hotel - Order View Payment & Printable Receipt Panel
*
* @author    Lush View Hotel
* @copyright 2026 Lush View Hotel
* @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
*}

<div class="panel">
    <div class="panel-heading">
        <i class="icon-money"></i> {l s='Lush View Folio Balance & Receipt' mod='lvhpayments'}
        <span class="pull-right">
            <a href="{$lvh_thermal_url|escape:'html':'UTF-8'}" target="_blank" class="btn btn-default btn-xs">
                <i class="icon-print"></i> {l s='80mm Thermal Receipt' mod='lvhpayments'}
            </a>
            <a href="{$lvh_a4_url|escape:'html':'UTF-8'}" target="_blank" class="btn btn-primary btn-xs">
                <i class="icon-file-text"></i> {l s='A4 Guest Receipt' mod='lvhpayments'}
            </a>
        </span>
    </div>

    <div class="row" style="margin-bottom: 12px;">
        <div class="col-sm-4">
            <div class="well well-sm" style="margin-bottom: 6px;">
                <small class="text-muted">{l s='Room + Extras Total' mod='lvhpayments'}</small>
                <div style="font-size: 16px; font-weight: bold;">GHS {$lvh_grand_total|string_format:"%.2f"}</div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="well well-sm" style="margin-bottom: 6px;">
                <small class="text-muted">{l s='Total Paid' mod='lvhpayments'}</small>
                <div style="font-size: 16px; font-weight: bold; color: #15803d;">GHS {$lvh_paid_total|string_format:"%.2f"}</div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="well well-sm" style="margin-bottom: 6px; border-color: {if $lvh_balance_due > 0.01}#dc2626{else}#16a34a{/if};">
                <small class="text-muted">{l s='Balance Due' mod='lvhpayments'}</small>
                <div style="font-size: 16px; font-weight: bold; color: {if $lvh_balance_due > 0.01}#dc2626{else}#15803d{/if};">
                    {if $lvh_balance_due > 0.01}
                        GHS {$lvh_balance_due|string_format:"%.2f"}
                    {else}
                        {l s='PAID IN FULL' mod='lvhpayments'}
                    {/if}
                </div>
            </div>
        </div>
    </div>

    {if !empty($lvh_payment_rows)}
        <div class="table-responsive">
            <table class="table table-condensed table-bordered" style="margin-bottom: 0;">
                <thead>
                    <tr>
                        <th>{l s='Date' mod='lvhpayments'}</th>
                        <th>{l s='Canonical Tender' mod='lvhpayments'}</th>
                        <th>{l s='Reference / Tx ID' mod='lvhpayments'}</th>
                        <th class="text-right">{l s='Amount (GHS)' mod='lvhpayments'}</th>
                        <th>{l s='Recorded By' mod='lvhpayments'}</th>
                        <th>{l s='Audit' mod='lvhpayments'}</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach from=$lvh_payment_rows item=pr}
                        <tr>
                            <td>{$pr.date_add|escape:'html':'UTF-8'}</td>
                            <td><strong>{$pr.canonical_label|escape:'html':'UTF-8'}</strong></td>
                            <td>
                                {if $pr.transaction_id}
                                    <code>{$pr.transaction_id|escape:'html':'UTF-8'}</code>
                                {else}
                                    <span class="text-muted">&mdash;</span>
                                {/if}
                            </td>
                            <td class="text-right"><strong>{$pr.amount|string_format:"%.2f"}</strong></td>
                            <td>{$pr.recorded_by_name|escape:'html':'UTF-8'}</td>
                            <td>
                                {if $pr.verified_status == 'verified'}
                                    <span class="label label-success">{l s='Verified' mod='lvhpayments'}</span>
                                {elseif $pr.verified_status == 'flagged'}
                                    <span class="label label-danger">{l s='Flagged' mod='lvhpayments'}</span>
                                {else}
                                    <span class="label label-default">{l s='Recorded' mod='lvhpayments'}</span>
                                {/if}
                            </td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        </div>
    {/if}
</div>
