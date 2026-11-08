{*
* Lush View Hotel - Shift Cash-Up & Electronic Payment Verification Console
*
* @author    Lush View Hotel
* @copyright 2026 Lush View Hotel
* @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
*}

<div class="panel">
    <div class="panel-heading">
        <i class="icon-calendar"></i> {l s='Select Shift / Reconciliation Date' mod='lvhpayments'}
    </div>
    <form method="get" action="{$lvh_controller_url|escape:'html':'UTF-8'}" class="form-inline">
        <input type="hidden" name="controller" value="AdminLvhPayments" />
        <input type="hidden" name="token" value="{$lvh_token|escape:'html':'UTF-8'}" />
        <div class="form-group" style="margin-right: 10px;">
            <label>{l s='Business Date:' mod='lvhpayments'}</label>
            <input type="date" name="shift_date" value="{$lvh_shift_date|escape:'html':'UTF-8'}" class="form-control" />
        </div>
        <button type="submit" class="btn btn-primary">
            <i class="icon-refresh"></i> {l s='Load Shift Totals' mod='lvhpayments'}
        </button>
    </form>
</div>

<div class="row">
    <div class="col-md-3 col-sm-6">
        <div class="panel">
            <div class="text-muted">{l s='Expected Cash Drawer (GHS)' mod='lvhpayments'}</div>
            <h3 style="margin: 4px 0 0; color: #15803d;">GHS {$lvh_summary.cash|string_format:"%.2f"}</h3>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="panel">
            <div class="text-muted">{l s='Mobile Money (MTN / Telecel / AT)' mod='lvhpayments'}</div>
            <h3 style="margin: 4px 0 0; color: #b45309;">GHS {$lvh_summary.momo|string_format:"%.2f"}</h3>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="panel">
            <div class="text-muted">{l s='POS / Card Terminal' mod='lvhpayments'}</div>
            <h3 style="margin: 4px 0 0; color: #1d4ed8;">GHS {$lvh_summary.pos|string_format:"%.2f"}</h3>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="panel">
            <div class="text-muted">{l s='Bank Transfer' mod='lvhpayments'}</div>
            <h3 style="margin: 4px 0 0;">GHS {$lvh_summary.bank|string_format:"%.2f"}</h3>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-5">
        <div class="panel">
            <div class="panel-heading">
                <i class="icon-briefcase"></i> {l s='Submit Receptionist Shift Cash-Up' mod='lvhpayments'}
            </div>
            <form method="post" action="{$lvh_controller_url|escape:'html':'UTF-8'}&shift_date={$lvh_shift_date|escape:'url'}">
                <div class="form-group">
                    <label>{l s='Shift Window' mod='lvhpayments'}</label>
                    <select name="shift_label" class="form-control">
                        <option value="Morning Shift (07:00-15:00)">{l s='Morning Shift (07:00-15:00)' mod='lvhpayments'}</option>
                        <option value="Afternoon / Evening (15:00-23:00)">{l s='Afternoon / Evening (15:00-23:00)' mod='lvhpayments'}</option>
                        <option value="Night Audit Shift (23:00-07:00)">{l s='Night Audit Shift (23:00-07:00)' mod='lvhpayments'}</option>
                        <option value="Full Day Handover">{l s='Full Day Handover' mod='lvhpayments'}</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>{l s='System Expected Cash for Selected Date (GHS)' mod='lvhpayments'}</label>
                    <input type="text" class="form-control" readonly="readonly" value="{$lvh_summary.cash|string_format:'%.2f'}" />
                </div>
                <div class="form-group">
                    <label>{l s='Physical Cash Counted in Drawer (GHS)' mod='lvhpayments'} *</label>
                    <input type="number" step="0.01" min="0" name="counted_cash" required="required" class="form-control" placeholder="0.00" />
                </div>
                <div class="form-group">
                    <label>{l s='Handover Notes / Petty Cash / Float Remarks' mod='lvhpayments'}</label>
                    <textarea name="handover_notes" rows="3" class="form-control" placeholder="{l s='Note any change float, MoMo delays, or pending checkouts...' mod='lvhpayments'}"></textarea>
                </div>
                <button type="submit" name="submitShiftCashup" value="1" class="btn btn-success">
                    <i class="icon-check"></i> {l s='Record Shift Handover' mod='lvhpayments'}
                </button>
            </form>
        </div>
    </div>

    <div class="col-md-7">
        <div class="panel">
            <div class="panel-heading">
                <i class="icon-exchange"></i> {l s='Recent Shift Handovers' mod='lvhpayments'}
            </div>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>{l s='Date / Shift' mod='lvhpayments'}</th>
                            <th>{l s='Staff' mod='lvhpayments'}</th>
                            <th class="text-right">{l s='Expected' mod='lvhpayments'}</th>
                            <th class="text-right">{l s='Counted' mod='lvhpayments'}</th>
                            <th class="text-right">{l s='Variance' mod='lvhpayments'}</th>
                            <th>{l s='Status' mod='lvhpayments'}</th>
                            {if $lvh_is_supervisor}<th>{l s='Action' mod='lvhpayments'}</th>{/if}
                        </tr>
                    </thead>
                    <tbody>
                        {if empty($lvh_cashups)}
                            <tr>
                                <td colspan="7" class="text-center text-muted">{l s='No shift handovers recorded yet.' mod='lvhpayments'}</td>
                            </tr>
                        {else}
                            {foreach from=$lvh_cashups item=cu}
                                <tr>
                                    <td>
                                        <strong>{$cu.shift_date|escape:'html':'UTF-8'}</strong><br>
                                        <small class="text-muted">{$cu.shift_label|escape:'html':'UTF-8'}</small>
                                    </td>
                                    <td>
                                        {$cu.employee_name|escape:'html':'UTF-8'}
                                        {if $cu.handover_notes}<br><small class="text-muted">{$cu.handover_notes|escape:'html':'UTF-8'}</small>{/if}
                                    </td>
                                    <td class="text-right">{$cu.expected_cash|string_format:"%.2f"}</td>
                                    <td class="text-right"><strong>{$cu.counted_cash|string_format:"%.2f"}</strong></td>
                                    <td class="text-right" style="color: {if $cu.variance_cash < -0.01}#dc2626{elseif $cu.variance_cash > 0.01}#d97706{else}#15803d{/if}; font-weight: bold;">
                                        {$cu.variance_cash|string_format:"%+.2f"}
                                    </td>
                                    <td>
                                        {if $cu.supervisor_status == 'approved'}
                                            <span class="label label-success">{l s='Approved' mod='lvhpayments'}</span>
                                        {elseif $cu.supervisor_status == 'discrepancy'}
                                            <span class="label label-danger">{l s='Discrepancy' mod='lvhpayments'}</span>
                                        {else}
                                            <span class="label label-warning">{l s='Submitted' mod='lvhpayments'}</span>
                                        {/if}
                                    </td>
                                    {if $lvh_is_supervisor}
                                        <td style="white-space: nowrap;">
                                            <form method="post" action="{$lvh_controller_url|escape:'html':'UTF-8'}&shift_date={$lvh_shift_date|escape:'url'}" style="display:inline;">
                                                <input type="hidden" name="id_cashup" value="{$cu.id_cashup|intval}" />
                                                <button type="submit" name="approveCashup" value="approved" class="btn btn-xs btn-success" title="Approve">
                                                    <i class="icon-ok"></i>
                                                </button>
                                                <button type="submit" name="approveCashup" value="discrepancy" class="btn btn-xs btn-danger" title="Flag Variance">
                                                    <i class="icon-warning-sign"></i>
                                                </button>
                                            </form>
                                        </td>
                                    {/if}
                                </tr>
                            {/foreach}
                        {/if}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-heading">
        <i class="icon-credit-card"></i> {l s='Transactions & Reference Verification for' mod='lvhpayments'} {$lvh_shift_date|escape:'html':'UTF-8'}
        <span class="badge">{$lvh_day_payments|@count}</span>
    </div>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>{l s='Time' mod='lvhpayments'}</th>
                    <th>{l s='Order' mod='lvhpayments'}</th>
                    <th>{l s='Canonical Tender' mod='lvhpayments'}</th>
                    <th>{l s='Raw Entry' mod='lvhpayments'}</th>
                    <th>{l s='Tx ID / Ref' mod='lvhpayments'}</th>
                    <th class="text-right">{l s='Amount (GHS)' mod='lvhpayments'}</th>
                    <th>{l s='Staff' mod='lvhpayments'}</th>
                    <th>{l s='Verification' mod='lvhpayments'}</th>
                    <th>{l s='Actions' mod='lvhpayments'}</th>
                </tr>
            </thead>
            <tbody>
                {if empty($lvh_day_payments)}
                    <tr>
                        <td colspan="9" class="text-center text-muted">{l s='No payments recorded on this date.' mod='lvhpayments'}</td>
                    </tr>
                {else}
                    {foreach from=$lvh_day_payments item=dp}
                        <tr>
                            <td>{$dp.date_add|escape:'html':'UTF-8'}</td>
                            <td>
                                {if $dp.id_order > 0}
                                    <a href="{$lvh_order_base_url|escape:'html':'UTF-8'}&id_order={$dp.id_order|intval}&vieworder">
                                        <strong>{$dp.order_reference|escape:'html':'UTF-8'}</strong> (#{$dp.id_order|intval})
                                    </a>
                                {else}
                                    {$dp.order_reference|escape:'html':'UTF-8'}
                                {/if}
                            </td>
                            <td><strong>{$dp.canonical_label|escape:'html':'UTF-8'}</strong></td>
                            <td class="text-muted"><small>{$dp.payment_method|escape:'html':'UTF-8'}</small></td>
                            <td>
                                {if $dp.transaction_id}
                                    <code>{$dp.transaction_id|escape:'html':'UTF-8'}</code>
                                {elseif $dp.group != 'cash'}
                                    <span class="label label-warning">{l s='Missing Ref' mod='lvhpayments'}</span>
                                {else}
                                    &mdash;
                                {/if}
                            </td>
                            <td class="text-right"><strong>{$dp.amount|string_format:"%.2f"}</strong></td>
                            <td>{$dp.recorded_by_name|escape:'html':'UTF-8'}</td>
                            <td>
                                {if $dp.verified_status == 'verified'}
                                    <span class="label label-success">{l s='Verified' mod='lvhpayments'} ({$dp.verified_by_name|escape:'html':'UTF-8'})</span>
                                {elseif $dp.verified_status == 'flagged'}
                                    <span class="label label-danger">{l s='Flagged' mod='lvhpayments'}</span>
                                {else}
                                    <span class="label label-default">{l s='Pending' mod='lvhpayments'}</span>
                                {/if}
                            </td>
                            <td style="white-space: nowrap;">
                                {if $dp.id_order > 0}
                                    <a href="{$lvh_controller_url|escape:'html':'UTF-8'}&printReceipt=1&id_order={$dp.id_order|intval}&format=thermal" target="_blank" class="btn btn-default btn-xs" title="Print Receipt">
                                        <i class="icon-print"></i>
                                    </a>
                                {/if}
                                {if $lvh_is_supervisor}
                                    <form method="post" action="{$lvh_controller_url|escape:'html':'UTF-8'}&shift_date={$lvh_shift_date|escape:'url'}" style="display:inline;">
                                        <input type="hidden" name="id_order_payment" value="{$dp.id_order_payment|intval}" />
                                        <input type="hidden" name="id_order" value="{$dp.id_order|intval}" />
                                        <input type="hidden" name="canonical_code" value="{$dp.canonical_code|escape:'html':'UTF-8'}" />
                                        <button type="submit" name="verifyPaymentAction" value="verified" class="btn btn-success btn-xs" title="Verify Reference">
                                            <i class="icon-ok"></i>
                                        </button>
                                        <button type="submit" name="verifyPaymentAction" value="flagged" class="btn btn-danger btn-xs" title="Flag Issue">
                                            <i class="icon-flag"></i>
                                        </button>
                                    </form>
                                {/if}
                            </td>
                        </tr>
                    {/foreach}
                {/if}
            </tbody>
        </table>
    </div>
</div>
