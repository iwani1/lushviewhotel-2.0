{*
* Lush View Hotel - Shared Conference Hall Hourly Scheduler View
*
* @author    Lush View Hotel
* @copyright 2026 Lush View Hotel
* @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
*}

{if $lvh_active_con_products > 0 && $lvh_is_supervisor}
    <div class="alert alert-warning" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap;">
        <div>
            <strong><i class="icon-warning-sign"></i> {l s='Shared Hall Consolidation Notice:' mod='lvhconference'}</strong>
            {$lvh_active_con_products|intval} {l s='legacy CON-1..CON-4 room products are still active in bedroom search. Hide them so online guests only see the 34 physical guest rooms while staff book the Conference Hall here.' mod='lvhconference'}
        </div>
        <form method="post" action="{$lvh_controller_url|escape:'html':'UTF-8'}">
            <button type="submit" name="submitHideLegacyConRooms" value="1" class="btn btn-warning btn-sm">
                <i class="icon-eye-close"></i> {l s='Unpublish CON-1..CON-4 from Bedroom Search' mod='lvhconference'}
            </button>
        </form>
    </div>
{/if}

<div class="panel" style="border-left: 4px solid #1d4ed8;">
    <div class="panel-heading">
        <i class="icon-calendar"></i> {l s='Book Lush View Conference Hall (Single Shared Hall — Overlap Protected)' mod='lvhconference'}
    </div>

    <form method="post" action="{$lvh_controller_url|escape:'html':'UTF-8'}">
        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label>{l s='Tariff Package' mod='lvhconference'} *</label>
                    <select name="package_code" id="lvh_conf_pkg" class="form-control">
                        {foreach from=$lvh_packages item=pk}
                            <option value="{$pk.code|escape:'html':'UTF-8'}" data-price="{$pk.price|floatval}" data-mins="{$pk.minutes|intval}">
                                {$pk.label|escape:'html':'UTF-8'}
                            </option>
                        {/foreach}
                    </select>
                </div>
                <div class="row">
                    <div class="col-xs-6">
                        <div class="form-group">
                            <label>{l s='Event Date' mod='lvhconference'} *</label>
                            <input type="date" name="event_date" value="{$lvh_today|escape:'html':'UTF-8'}" class="form-control" required="required" />
                        </div>
                    </div>
                    <div class="col-xs-6">
                        <div class="form-group">
                            <label>{l s='Start Time' mod='lvhconference'} *</label>
                            <input type="time" name="start_time" value="09:00" class="form-control" required="required" />
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-xs-6" id="lvh_custom_mins_wrap" style="display:none;">
                        <div class="form-group">
                            <label>{l s='Custom Mins' mod='lvhconference'}</label>
                            <input type="number" name="custom_minutes" value="240" min="30" step="30" class="form-control" />
                        </div>
                    </div>
                    <div class="col-xs-6">
                        <div class="form-group">
                            <label>{l s='Turnaround Buffer (Mins)' mod='lvhconference'}</label>
                            <input type="number" name="buffer_minutes" value="30" min="0" max="180" step="15" class="form-control" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="form-group">
                    <label>{l s='Event / Meeting Title' mod='lvhconference'} *</label>
                    <input type="text" name="event_title" class="form-control" required="required" placeholder="{l s='e.g. Strategy Workshop / AGM' mod='lvhconference'}" />
                </div>
                <div class="row">
                    <div class="col-xs-6">
                        <div class="form-group">
                            <label>{l s='Organization / Company' mod='lvhconference'}</label>
                            <input type="text" name="organization" class="form-control" />
                        </div>
                    </div>
                    <div class="col-xs-6">
                        <div class="form-group">
                            <label>{l s='Contact Person' mod='lvhconference'} *</label>
                            <input type="text" name="contact_name" class="form-control" required="required" />
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-xs-5">
                        <div class="form-group">
                            <label>{l s='Phone' mod='lvhconference'}</label>
                            <input type="text" name="contact_phone" class="form-control" placeholder="024..." />
                        </div>
                    </div>
                    <div class="col-xs-3">
                        <div class="form-group">
                            <label>{l s='Pax' mod='lvhconference'}</label>
                            <input type="number" name="attendees" value="25" min="1" class="form-control" />
                        </div>
                    </div>
                    <div class="col-xs-4">
                        <div class="form-group">
                            <label>{l s='Layout' mod='lvhconference'}</label>
                            <select name="seating_layout" class="form-control">
                                <option value="Boardroom">Boardroom</option>
                                <option value="Classroom">Classroom</option>
                                <option value="Theatre">Theatre</option>
                                <option value="U-Shape">U-Shape</option>
                                <option value="Banquet">Banquet</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="form-group">
                    <label>{l s='AV / Catering / Setup Notes' mod='lvhconference'}</label>
                    <input type="text" name="equipment_catering" class="form-control" placeholder="{l s='Projector, PA, Water, Tea Break...' mod='lvhconference'}" />
                </div>
                <div class="row">
                    <div class="col-xs-6">
                        <div class="form-group">
                            <label>{l s='Total Fee (GHS)' mod='lvhconference'}</label>
                            <input type="number" step="0.01" name="total_amount" id="lvh_conf_total" value="1000.00" class="form-control" style="font-weight:bold;" />
                        </div>
                    </div>
                    <div class="col-xs-6">
                        <div class="form-group">
                            <label>{l s='Deposit / Paid (GHS)' mod='lvhconference'}</label>
                            <input type="number" step="0.01" name="paid_amount" id="lvh_conf_paid" value="1000.00" class="form-control" />
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-xs-6">
                        <select name="payment_method" class="form-control">
                            {foreach from=$lvh_payment_methods item=pm}
                                <option value="{$pm.label|escape:'html':'UTF-8'}">{$pm.label|escape:'html':'UTF-8'}</option>
                            {/foreach}
                        </select>
                    </div>
                    <div class="col-xs-6">
                        <input type="text" name="transaction_id" class="form-control" placeholder="{l s='Tx ID / Ref' mod='lvhconference'}" />
                    </div>
                </div>
                <div style="margin-top: 10px; display:flex; justify-content:space-between; align-items:center;">
                    <select name="status" class="form-control input-sm" style="width: 140px;">
                        <option value="confirmed">{l s='Confirmed' mod='lvhconference'}</option>
                        <option value="tentative">{l s='Tentative Hold' mod='lvhconference'}</option>
                    </select>
                    <button type="submit" name="submitCreateConfBooking" value="1" class="btn btn-primary">
                        <i class="icon-check"></i> {l s='Reserve Hall Slot' mod='lvhconference'}
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<div class="panel">
    <div class="panel-heading">
        <i class="icon-list"></i> {l s='Unified Conference Hall Schedule' mod='lvhconference'}
        <span class="badge">{$lvh_bookings|@count}</span>
    </div>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>{l s='Ref' mod='lvhconference'}</th>
                    <th>{l s='Time Window (+Buffer)' mod='lvhconference'}</th>
                    <th>{l s='Event & Client' mod='lvhconference'}</th>
                    <th>{l s='Package / Setup' mod='lvhconference'}</th>
                    <th class="text-right">{l s='Total (GHS)' mod='lvhconference'}</th>
                    <th class="text-right">{l s='Balance Due' mod='lvhconference'}</th>
                    <th>{l s='Status' mod='lvhconference'}</th>
                    <th>{l s='Actions' mod='lvhconference'}</th>
                </tr>
            </thead>
            <tbody>
                {if empty($lvh_bookings)}
                    <tr>
                        <td colspan="8" class="text-center text-muted">{l s='No Conference Hall bookings scheduled from this date.' mod='lvhconference'}</td>
                    </tr>
                {else}
                    {foreach from=$lvh_bookings item=cb}
                        <tr {if $cb.status == 'cancelled'}style="opacity: 0.5; text-decoration: line-through;"{/if}>
                            <td><code>{$cb.booking_ref|escape:'html':'UTF-8'}</code></td>
                            <td style="white-space: nowrap;">
                                <strong>{$cb.start_datetime|date_format:"%d %b %Y"}</strong><br>
                                {$cb.start_datetime|date_format:"%H:%M"} &rarr; {$cb.end_datetime|date_format:"%H:%M"}
                                {if $cb.buffer_minutes > 0}
                                    <small class="text-muted">(+{$cb.buffer_minutes|intval}m)</small>
                                {/if}
                            </td>
                            <td>
                                <strong>{$cb.event_title|escape:'html':'UTF-8'}</strong><br>
                                <small>{$cb.organization|escape:'html':'UTF-8'} &bull; {$cb.contact_name|escape:'html':'UTF-8'} {if $cb.contact_phone}({$cb.contact_phone|escape:'html':'UTF-8'}){/if}</small>
                            </td>
                            <td>
                                <span class="label label-default">{$cb.package_code|escape:'html':'UTF-8'}</span>
                                {$cb.seating_layout|escape:'html':'UTF-8'} ({$cb.attendees|intval} pax)
                            </td>
                            <td class="text-right"><strong>{$cb.total_amount|string_format:"%.2f"}</strong></td>
                            <td class="text-right">
                                {if $cb.balance_due > 0.01}
                                    <strong style="color:#dc2626;">GHS {$cb.balance_due|string_format:"%.2f"}</strong>
                                {else}
                                    <span style="color:#15803d;">Paid</span>
                                {/if}
                            </td>
                            <td>
                                {if $cb.status == 'confirmed'}
                                    <span class="label label-success">{l s='Confirmed' mod='lvhconference'}</span>
                                {elseif $cb.status == 'tentative'}
                                    <span class="label label-warning">{l s='Tentative' mod='lvhconference'}</span>
                                {elseif $cb.status == 'completed'}
                                    <span class="label label-info">{l s='Completed' mod='lvhconference'}</span>
                                {else}
                                    <span class="label label-danger">{l s='Cancelled' mod='lvhconference'}</span>
                                {/if}
                            </td>
                            <td style="white-space: nowrap;">
                                <a href="{$lvh_controller_url|escape:'html':'UTF-8'}&printBeo=1&id_conf_booking={$cb.id_conf_booking|intval}" target="_blank" class="btn btn-default btn-xs" title="Print BEO & Receipt">
                                    <i class="icon-print"></i> {l s='BEO / Receipt' mod='lvhconference'}
                                </a>
                                {if $cb.status != 'cancelled'}
                                    <a href="{$lvh_controller_url|escape:'html':'UTF-8'}&id_conf_booking={$cb.id_conf_booking|intval}&updateConfStatus=completed" class="btn btn-success btn-xs" title="Mark Completed">
                                        <i class="icon-ok"></i>
                                    </a>
                                    {if $lvh_is_supervisor}
                                        <a href="{$lvh_controller_url|escape:'html':'UTF-8'}&id_conf_booking={$cb.id_conf_booking|intval}&updateConfStatus=cancelled" class="btn btn-danger btn-xs" onclick="return confirm('Cancel this Conference Hall slot?');" title="Cancel">
                                            <i class="icon-remove"></i>
                                        </a>
                                    {/if}
                                {/if}
                            </td>
                        </tr>
                    {/foreach}
                {/if}
            </tbody>
        </table>
    </div>
</div>

<script type="text/javascript">
(function () {
    var sel = document.getElementById('lvh_conf_pkg');
    var tot = document.getElementById('lvh_conf_total');
    var paid = document.getElementById('lvh_conf_paid');
    var customWrap = document.getElementById('lvh_custom_mins_wrap');

    if (sel) {
        sel.addEventListener('change', function () {
            var opt = sel.options[sel.selectedIndex];
            var p = parseFloat(opt.getAttribute('data-price') || '1000');
            if (tot) tot.value = p.toFixed(2);
            if (paid) paid.value = p.toFixed(2);
            if (customWrap) {
                customWrap.style.display = (sel.value === 'CUSTOM') ? 'block' : 'none';
            }
        });
    }
})();
</script>
