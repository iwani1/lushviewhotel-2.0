{*
* Lush View Hotel - 1-Screen Express Front Desk & 34-Room Visual Rack
*
* @author    Lush View Hotel
* @copyright 2026 Lush View Hotel
* @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
*}

{if $lvh_created_banner}
    <div class="alert alert-success" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap;">
        <div>
            <strong><i class="icon-check"></i> {l s='Walk-In Checked In / Confirmed:' mod='lvhfrontdesk'}</strong>
            {$lvh_created_banner.reference|escape:'html':'UTF-8'} (#{$lvh_created_banner.id_order|intval})
        </div>
        <div>
            <a href="{$lvh_payments_url|escape:'html':'UTF-8'}&printReceipt=1&id_order={$lvh_created_banner.id_order|intval}&format=thermal" target="_blank" class="btn btn-default btn-sm">
                <i class="icon-print"></i> {l s='Print 80mm Thermal Receipt' mod='lvhfrontdesk'}
            </a>
            <a href="{$lvh_payments_url|escape:'html':'UTF-8'}&printReceipt=1&id_order={$lvh_created_banner.id_order|intval}&format=a4" target="_blank" class="btn btn-primary btn-sm">
                <i class="icon-file-text"></i> {l s='Print A4 Receipt' mod='lvhfrontdesk'}
            </a>
            <a href="{$lvh_order_base_url|escape:'html':'UTF-8'}&id_order={$lvh_created_banner.id_order|intval}&vieworder" class="btn btn-default btn-sm">
                <i class="icon-external-link"></i> {l s='Open Order' mod='lvhfrontdesk'}
            </a>
        </div>
    </div>
{/if}

<div class="row" style="margin-bottom: 12px;">
    <div class="col-md-2 col-sm-4">
        <div class="panel" style="margin-bottom: 8px; padding: 10px 14px;">
            <small class="text-muted">{l s='Occupancy' mod='lvhfrontdesk'}</small>
            <h3 style="margin: 2px 0 0; color: #1d4ed8;">{$lvh_kpis.occupied|intval} / {$lvh_kpis.total_rooms|intval} <small>({$lvh_kpis.occupancy_pct}%)</small></h3>
        </div>
    </div>
    <div class="col-md-2 col-sm-4">
        <div class="panel" style="margin-bottom: 8px; padding: 10px 14px;">
            <small class="text-muted">{l s='Vacant & Ready' mod='lvhfrontdesk'}</small>
            <h3 style="margin: 2px 0 0; color: #15803d;">{$lvh_kpis.vacant_clean|intval}</h3>
        </div>
    </div>
    <div class="col-md-2 col-sm-4">
        <div class="panel" style="margin-bottom: 8px; padding: 10px 14px;">
            <small class="text-muted">{l s='Due Out (12:00)' mod='lvhfrontdesk'}</small>
            <h3 style="margin: 2px 0 0; color: #d97706;">{$lvh_kpis.departures_due|intval}</h3>
        </div>
    </div>
    <div class="col-md-2 col-sm-4">
        <div class="panel" style="margin-bottom: 8px; padding: 10px 14px;">
            <small class="text-muted">{l s='Arrivals Today' mod='lvhfrontdesk'}</small>
            <h3 style="margin: 2px 0 0; color: #7c3aed;">{$lvh_kpis.arrivals_today|intval}</h3>
        </div>
    </div>
    <div class="col-md-2 col-sm-4">
        <div class="panel" style="margin-bottom: 8px; padding: 10px 14px;">
            <small class="text-muted">{l s='Dirty / OOO' mod='lvhfrontdesk'}</small>
            <h3 style="margin: 2px 0 0; color: #dc2626;">{$lvh_kpis.dirty_or_ooo|intval}</h3>
        </div>
    </div>
    <div class="col-md-2 col-sm-4">
        <div class="panel" style="margin-bottom: 8px; padding: 10px 14px;">
            <small class="text-muted">{l s='Unpaid Folios' mod='lvhfrontdesk'}</small>
            <h3 style="margin: 2px 0 0; color: {if $lvh_kpis.unpaid_folios > 0}#dc2626{else}#15803d{/if};">{$lvh_kpis.unpaid_folios|intval}</h3>
        </div>
    </div>
</div>

{* 30-SECOND EXPRESS WALK-IN PANEL *}
<div class="panel" id="lvh-express-walkin-panel" style="border-left: 4px solid #15803d;">
    <div class="panel-heading">
        <i class="icon-bolt"></i> {l s='30-Second Express Walk-In & Instant Check-In' mod='lvhfrontdesk'}
        <span class="pull-right">
            <form method="get" action="{$lvh_controller_url|escape:'html':'UTF-8'}" class="form-inline" style="display:inline;">
                <input type="hidden" name="controller" value="AdminLvhFrontDesk" />
                <input type="hidden" name="token" value="{$lvh_token|escape:'html':'UTF-8'}" />
                <label style="font-size:12px;">{l s='Rack Date:' mod='lvhfrontdesk'}</label>
                <input type="date" name="business_date" value="{$lvh_business_date|escape:'html':'UTF-8'}" class="form-control input-sm" onchange="this.form.submit();" />
            </form>
        </span>
    </div>

    <form method="post" action="{$lvh_controller_url|escape:'html':'UTF-8'}&business_date={$lvh_business_date|escape:'url'}" id="lvh-walkin-form">
        <input type="hidden" name="id_customer" id="lvh_id_customer" value="0" />

        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label>{l s='1. Select Ready Room (Flat GHS Rate)' mod='lvhfrontdesk'} *</label>
                    <select name="id_room" id="lvh_id_room" class="form-control" required="required">
                        <option value="" data-price="0">{l s='-- Choose Vacant Clean Room --' mod='lvhfrontdesk'}</option>
                        {foreach from=$lvh_available_rooms item=ar}
                            <option value="{$ar.id_room|intval}" data-price="{$ar.unit_price|floatval}">
                                {$ar.room_num|escape:'html':'UTF-8'} — {$ar.room_type|escape:'html':'UTF-8'} (GHS {$ar.unit_price|string_format:"%.0f"}/night, {$ar.floor|escape:'html':'UTF-8'} Flr)
                            </option>
                        {/foreach}
                    </select>
                </div>
                <div class="row">
                    <div class="col-xs-5">
                        <div class="form-group">
                            <label>{l s='Check-In Date' mod='lvhfrontdesk'}</label>
                            <input type="date" name="date_from" id="lvh_date_from" value="{$lvh_business_date|escape:'html':'UTF-8'}" class="form-control" required="required" />
                        </div>
                    </div>
                    <div class="col-xs-4">
                        <div class="form-group">
                            <label>{l s='Nights' mod='lvhfrontdesk'}</label>
                            <input type="number" name="nights" id="lvh_nights" value="1" min="1" max="90" class="form-control" required="required" />
                        </div>
                    </div>
                    <div class="col-xs-3">
                        <div class="form-group">
                            <label>{l s='Adults' mod='lvhfrontdesk'}</label>
                            <input type="number" name="adults" value="1" min="1" max="6" class="form-control" />
                        </div>
                    </div>
                </div>
                <div class="checkbox" style="margin-top: 2px;">
                    <label>
                        <input type="checkbox" name="instant_checkin" value="1" checked="checked" />
                        <strong>{l s='Check-In Guest Immediately (In-House Now)' mod='lvhfrontdesk'}</strong>
                    </label>
                </div>
            </div>

            <div class="col-md-4">
                <div class="form-group" style="position: relative;">
                    <label>{l s='2. Lookup Returning Guest (Phone / Name / Ghana Card)' mod='lvhfrontdesk'}</label>
                    <input type="text" id="lvh_guest_search" class="form-control" autocomplete="off" placeholder="{l s='Type phone or name to auto-fill VIP guest...' mod='lvhfrontdesk'}" />
                    <div id="lvh_guest_results" style="display:none; position:absolute; z-index:999; left:0; right:0; background:#fff; border:1px solid #cbd5e1; max-height:220px; overflow-y:auto; box-shadow:0 6px 16px rgba(0,0,0,0.12);"></div>
                    <div id="lvh_selected_vip_badge" style="margin-top:4px; display:none;"></div>
                </div>
                <div class="row">
                    <div class="col-xs-4">
                        <div class="form-group">
                            <label>{l s='Phone' mod='lvhfrontdesk'} *</label>
                            <input type="text" name="phone" id="lvh_phone" class="form-control" required="required" placeholder="024..." />
                        </div>
                    </div>
                    <div class="col-xs-4">
                        <div class="form-group">
                            <label>{l s='First Name' mod='lvhfrontdesk'} *</label>
                            <input type="text" name="firstname" id="lvh_firstname" class="form-control" required="required" />
                        </div>
                    </div>
                    <div class="col-xs-4">
                        <div class="form-group">
                            <label>{l s='Last Name' mod='lvhfrontdesk'} *</label>
                            <input type="text" name="lastname" id="lvh_lastname" class="form-control" required="required" />
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-xs-5">
                        <div class="form-group">
                            <label>{l s='ID Type' mod='lvhfrontdesk'}</label>
                            <select name="doc_type" id="lvh_doc_type" class="form-control input-sm">
                                <option value="Ghana Card">Ghana Card</option>
                                <option value="Passport">Passport</option>
                                <option value="Driver License">Driver License</option>
                                <option value="Voter ID">Voter ID</option>
                                <option value="ECOWAS Card">ECOWAS Card</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-xs-7">
                        <div class="form-group">
                            <label>{l s='ID Number (GTA / Police Reg.)' mod='lvhfrontdesk'}</label>
                            <input type="text" name="doc_number" id="lvh_doc_number" class="form-control input-sm" placeholder="GHA-..." />
                            <input type="hidden" name="nationality" id="lvh_nationality" value="Ghanaian" />
                            <input type="hidden" name="email" id="lvh_email" value="" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="row">
                    <div class="col-xs-7">
                        <div class="form-group">
                            <label>{l s='3. Payment Method' mod='lvhfrontdesk'} *</label>
                            <select name="payment_method" id="lvh_payment_method" class="form-control">
                                {foreach from=$lvh_payment_methods item=pm}
                                    <option value="{$pm.label|escape:'html':'UTF-8'}" data-req-ref="{if $pm.requires_ref}1{else}0{/if}">
                                        {$pm.label|escape:'html':'UTF-8'}
                                    </option>
                                {/foreach}
                            </select>
                        </div>
                    </div>
                    <div class="col-xs-5">
                        <div class="form-group">
                            <label>{l s='Paid Now (GHS)' mod='lvhfrontdesk'}</label>
                            <input type="number" step="0.01" min="0" name="amount_paid" id="lvh_amount_paid" value="0.00" class="form-control" style="font-weight:bold;" />
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>{l s='MoMo Tx ID / POS Slip / Reference' mod='lvhfrontdesk'}</label>
                    <input type="text" name="transaction_id" id="lvh_tx_id" class="form-control" placeholder="{l s='Required for MoMo, POS, Bank & Comp' mod='lvhfrontdesk'}" />
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top: 10px;">
                    <div>
                        <small class="text-muted">{l s='Calculated Total:' mod='lvhfrontdesk'}</small><br>
                        <strong style="font-size: 18px; color: #14532d;" id="lvh_calc_total_display">GHS 0.00</strong>
                    </div>
                    <button type="submit" name="submitExpressWalkIn" value="1" class="btn btn-success btn-lg">
                        <i class="icon-check"></i> {l s='Complete Walk-In' mod='lvhfrontdesk'}
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

{* 34-ROOM VISUAL RACK BY FLOOR *}
{foreach from=$lvh_floors key=floorTitle item=roomsList}
    <div class="panel">
        <div class="panel-heading">
            <i class="icon-th"></i> {$floorTitle|escape:'html':'UTF-8'} — {l s='Live Room Rack' mod='lvhfrontdesk'}
            <span class="badge">{$roomsList|@count}</span>
        </div>
        <div class="row">
            {foreach from=$roomsList item=rm}
                <div class="col-lg-3 col-md-4 col-sm-6" style="margin-bottom: 14px;">
                    <div style="border: 2px solid {$rm.RackColor|escape:'html':'UTF-8'}; background: {$rm.RackBg|escape:'html':'UTF-8'}; border-radius: 6px; padding: 11px; min-height: 168px; display:flex; flex-direction:column; justify-content:space-between;">
                        <div>
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <strong style="font-size: 16px;">{$rm.room_num|escape:'html':'UTF-8'}</strong>
                                <span style="background: {$rm.RackColor|escape:'html':'UTF-8'}; color:#fff; padding: 2px 7px; border-radius: 4px; font-size: 11px; font-weight: 600;">
                                    {$rm.RackLabel|escape:'html':'UTF-8'}
                                </span>
                            </div>
                            <div style="font-size: 12px; color: #4b5563; margin-top: 2px;">
                                {$rm.room_type|escape:'html':'UTF-8'} &bull; <strong>GHS {$rm.unit_price|string_format:"%.0f"}</strong>
                            </div>

                            {if $rm.booking}
                                <div style="margin-top: 8px; padding-top: 6px; border-top: 1px dashed #cbd5e1; font-size: 12px;">
                                    <strong>{$rm.booking.guest_name|escape:'html':'UTF-8'}</strong>
                                    {if $rm.vip_tier}
                                        <span class="label label-warning" style="font-size:10px;">{$rm.vip_tier|escape:'html':'UTF-8'} ({$rm.booking.stays_count|intval}x)</span>
                                    {/if}
                                    <div class="text-muted">
                                        {$rm.booking.date_from|date_format:"%d %b"} &rarr; {$rm.booking.date_to|date_format:"%d %b"}
                                        (#{$rm.booking.id_order|intval})
                                    </div>
                                    {if $rm.balance_due > 0.01}
                                        <div style="color:#dc2626; font-weight:bold; margin-top:2px;">
                                            Due: GHS {$rm.balance_due|string_format:"%.2f"}
                                        </div>
                                    {else}
                                        <div style="color:#15803d; font-weight:600; margin-top:2px;">Paid in Full</div>
                                    {/if}
                                </div>
                            {else}
                                <div style="margin-top: 12px; font-size: 12px; color: #374151;">
                                    {if $rm.hk_note}
                                        <em>{$rm.hk_note|escape:'html':'UTF-8'}</em>
                                    {elseif $rm.RackState == 'vacant_clean'}
                                        {l s='Inspected & Ready for Walk-In' mod='lvhfrontdesk'}
                                    {/if}
                                </div>
                            {/if}
                        </div>

                        <div style="margin-top: 10px;">
                            {if $rm.RackState == 'vacant_clean'}
                                <button type="button" class="btn btn-success btn-xs btn-block" onclick="lvhPickRoom({$rm.id_room|intval});">
                                    <i class="icon-plus"></i> {l s='Walk-In Here' mod='lvhfrontdesk'}
                                </button>
                            {elseif $rm.RackState == 'dirty'}
                                <form method="post" action="{$lvh_controller_url|escape:'html':'UTF-8'}&business_date={$lvh_business_date|escape:'url'}" style="display:inline;">
                                    <input type="hidden" name="id_room" value="{$rm.id_room|intval}" />
                                    <button type="submit" name="submitQuickMarkClean" value="1" class="btn btn-default btn-xs btn-block">
                                        <i class="icon-ok"></i> {l s='Mark Clean & Ready' mod='lvhfrontdesk'}
                                    </button>
                                </form>
                            {elseif $rm.booking}
                                <div style="display:flex; gap:4px; flex-wrap:wrap;">
                                    {if $rm.booking.id_status == 1}
                                        <form method="post" action="{$lvh_controller_url|escape:'html':'UTF-8'}&business_date={$lvh_business_date|escape:'url'}" style="display:inline;">
                                            <input type="hidden" name="id_booking_detail" value="{$rm.booking.id_booking_detail|intval}" />
                                            <button type="submit" name="submitQuickCheckIn" value="1" class="btn btn-primary btn-xs">
                                                <i class="icon-signin"></i> {l s='Check-In' mod='lvhfrontdesk'}
                                            </button>
                                        </form>
                                    {elseif $rm.booking.id_status == 2}
                                        <form method="post" action="{$lvh_controller_url|escape:'html':'UTF-8'}&business_date={$lvh_business_date|escape:'url'}" style="display:inline;" onsubmit="return lvhConfirmCheckout({$rm.balance_due|floatval}, {if $lvh_is_supervisor}true{else}false{/if}, this);">
                                            <input type="hidden" name="id_booking_detail" value="{$rm.booking.id_booking_detail|intval}" />
                                            <input type="hidden" name="balance_due" value="{$rm.balance_due|floatval}" />
                                            <input type="hidden" name="force_unpaid_checkout" value="0" />
                                            <button type="submit" name="submitQuickCheckOut" value="1" class="btn btn-warning btn-xs">
                                                <i class="icon-signout"></i> {l s='Check-Out' mod='lvhfrontdesk'}
                                            </button>
                                        </form>
                                    {/if}
                                    <a href="{$lvh_payments_url|escape:'html':'UTF-8'}&printReceipt=1&id_order={$rm.booking.id_order|intval}&format=thermal" target="_blank" class="btn btn-default btn-xs" title="Print Receipt">
                                        <i class="icon-print"></i>
                                    </a>
                                    <a href="{$lvh_order_base_url|escape:'html':'UTF-8'}&id_order={$rm.booking.id_order|intval}&vieworder" class="btn btn-default btn-xs" title="Open Folio / Add Payment">
                                        <i class="icon-folder-open"></i> {l s='Folio' mod='lvhfrontdesk'}
                                    </a>
                                </div>
                            {/if}
                        </div>
                    </div>
                </div>
            {/foreach}
        </div>
    </div>
{/foreach}

<script type="text/javascript">
(function () {
    var roomSel = document.getElementById('lvh_id_room');
    var nightsEl = document.getElementById('lvh_nights');
    var paidEl = document.getElementById('lvh_amount_paid');
    var calcDisp = document.getElementById('lvh_calc_total_display');
    var pmSel = document.getElementById('lvh_payment_method');
    var txEl = document.getElementById('lvh_tx_id');

    function recalcWalkinTotal() {
        if (!roomSel || !nightsEl) return;
        var opt = roomSel.options[roomSel.selectedIndex];
        var unit = opt ? parseFloat(opt.getAttribute('data-price') || '0') : 0;
        var nights = Math.max(1, parseInt(nightsEl.value || '1', 10));
        var total = unit * nights;
        if (calcDisp) {
            calcDisp.textContent = 'GHS ' + total.toFixed(2);
        }
        if (paidEl) {
            var isPayLater = pmSel && pmSel.value.toLowerCase().indexOf('later') !== -1;
            paidEl.value = isPayLater ? '0.00' : total.toFixed(2);
        }
    }

    function syncPaymentReq() {
        if (!pmSel || !txEl) return;
        var opt = pmSel.options[pmSel.selectedIndex];
        var req = opt && opt.getAttribute('data-req-ref') === '1';
        if (req) {
            txEl.setAttribute('required', 'required');
            txEl.style.borderColor = '#d97706';
        } else {
            txEl.removeAttribute('required');
            txEl.style.borderColor = '';
        }
        recalcWalkinTotal();
    }

    if (roomSel) roomSel.addEventListener('change', recalcWalkinTotal);
    if (nightsEl) nightsEl.addEventListener('input', recalcWalkinTotal);
    if (pmSel) pmSel.addEventListener('change', syncPaymentReq);

    window.lvhPickRoom = function (idRoom) {
        if (!roomSel) return;
        roomSel.value = String(idRoom);
        recalcWalkinTotal();
        var panel = document.getElementById('lvh-express-walkin-panel');
        if (panel && panel.scrollIntoView) {
            panel.scrollIntoView({ behavior: 'smooth' });
        }
        var searchInput = document.getElementById('lvh_guest_search');
        if (searchInput) searchInput.focus();
    };

    window.lvhConfirmCheckout = function (balanceDue, isSup, formEl) {
        if (balanceDue > 0.01) {
            if (!isSup) {
                alert('Cannot Check-Out: Room still owes GHS ' + balanceDue.toFixed(2) + '. Click "Folio" to record payment first, or ask a Supervisor.');
                return false;
            }
            var ok = confirm('WARNING: Guest owes GHS ' + balanceDue.toFixed(2) + '. Authorize Supervisor Check-Out with Open Corporate/Pay-Later Balance?');
            if (!ok) return false;
            formEl.querySelector('input[name="force_unpaid_checkout"]').value = '1';
            return true;
        }
        return confirm('Confirm Check-Out and send room to Housekeeping as Dirty?');
    };

    // Returning / VIP Guest Live Search
    var searchBox = document.getElementById('lvh_guest_search');
    var resultsBox = document.getElementById('lvh_guest_results');
    var vipBadge = document.getElementById('lvh_selected_vip_badge');
    var timer = null;

    if (searchBox && resultsBox) {
        searchBox.addEventListener('input', function () {
            clearTimeout(timer);
            var q = searchBox.value.trim();
            if (q.length < 2) {
                resultsBox.style.display = 'none';
                return;
            }
            timer = setTimeout(function () {
                var url = '{$lvh_controller_url|escape:'javascript':'UTF-8'}&ajaxSearchGuest=1&q=' + encodeURIComponent(q);
                fetch(url, { credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        resultsBox.innerHTML = '';
                        if (!data.guests || !data.guests.length) {
                            resultsBox.style.display = 'none';
                            return;
                        }
                        data.guests.forEach(function (g) {
                            var row = document.createElement('div');
                            row.style.padding = '7px 10px';
                            row.style.borderBottom = '1px solid #f1f5f9';
                            row.style.cursor = 'pointer';
                            var vip = g.vip_tier ? ' <span class="label label-warning">' + g.vip_tier + ' (' + g.stays_count + ' stays)</span>' : '';
                            row.innerHTML = '<strong>' + g.firstname + ' ' + g.lastname + '</strong> (' + (g.phone || 'No phone') + ')' + vip;
                            row.addEventListener('click', function () {
                                document.getElementById('lvh_id_customer').value = g.id_customer;
                                document.getElementById('lvh_phone').value = g.phone || '';
                                document.getElementById('lvh_firstname').value = g.firstname || '';
                                document.getElementById('lvh_lastname').value = g.lastname || '';
                                document.getElementById('lvh_email').value = g.email || '';
                                if (g.doc_type) document.getElementById('lvh_doc_type').value = g.doc_type;
                                if (g.doc_number) document.getElementById('lvh_doc_number').value = g.doc_number;
                                if (vipBadge) {
                                    vipBadge.style.display = 'block';
                                    vipBadge.innerHTML = '<span class="label label-success">Selected Returning Guest #' + g.id_customer + ' (' + g.stays_count + ' prior stays)</span>';
                                }
                                resultsBox.style.display = 'none';
                            });
                            resultsBox.appendChild(row);
                        });
                        resultsBox.style.display = 'block';
                    })
                    .catch(function () { });
            }, 220);
        });
    }
})();
</script>
