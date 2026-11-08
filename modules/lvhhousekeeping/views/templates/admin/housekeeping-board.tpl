{*
* Lush View Hotel - 34-Room Housekeeping & Readiness Floor Board
*
* @author    Lush View Hotel
* @copyright 2026 Lush View Hotel
* @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
*}

<div class="panel">
    <div class="panel-heading">
        <i class="icon-home"></i> {l s='Lush View Hotel — 34-Room Housekeeping Summary' mod='lvhhousekeeping'}
        <button type="button" onclick="window.print();" class="btn btn-default btn-xs pull-right">
            <i class="icon-print"></i> {l s='Print Attendant Sheet' mod='lvhhousekeeping'}
        </button>
    </div>
    <div class="btn-group" style="margin-bottom: 4px;">
        <a href="{$lvh_controller_url|escape:'html':'UTF-8'}" class="btn {if !$lvh_filter_status}btn-primary{else}btn-default{/if}">
            {l s='All Rooms' mod='lvhhousekeeping'} ({$lvh_counts.all|intval})
        </a>
        <a href="{$lvh_controller_url|escape:'html':'UTF-8'}&filter_status=clean" class="btn {if $lvh_filter_status == 'clean'}btn-success{else}btn-default{/if}">
            {l s='Clean / Ready' mod='lvhhousekeeping'} ({$lvh_counts.clean|intval})
        </a>
        <a href="{$lvh_controller_url|escape:'html':'UTF-8'}&filter_status=dirty" class="btn {if $lvh_filter_status == 'dirty'}btn-danger{else}btn-default{/if}">
            {l s='Dirty' mod='lvhhousekeeping'} ({$lvh_counts.dirty|intval})
        </a>
        <a href="{$lvh_controller_url|escape:'html':'UTF-8'}&filter_status=cleaning" class="btn {if $lvh_filter_status == 'cleaning'}btn-warning{else}btn-default{/if}">
            {l s='Cleaning' mod='lvhhousekeeping'} ({$lvh_counts.cleaning|intval})
        </a>
        <a href="{$lvh_controller_url|escape:'html':'UTF-8'}&filter_status=inspecting" class="btn {if $lvh_filter_status == 'inspecting'}btn-info{else}btn-default{/if}">
            {l s='Inspecting' mod='lvhhousekeeping'} ({$lvh_counts.inspecting|intval})
        </a>
        <a href="{$lvh_controller_url|escape:'html':'UTF-8'}&filter_status=ooo" class="btn {if $lvh_filter_status == 'ooo'}btn-primary{else}btn-default{/if}">
            {l s='Out of Order' mod='lvhhousekeeping'} ({$lvh_counts.ooo|intval})
        </a>
    </div>
</div>

{foreach from=$lvh_floors key=floorName item=floorRooms}
    <div class="panel">
        <div class="panel-heading">
            <i class="icon-building"></i> {$floorName|escape:'html':'UTF-8'}
            <span class="badge">{$floorRooms|@count}</span>
        </div>
        <div class="row">
            {foreach from=$floorRooms item=rm}
                <div class="col-lg-3 col-md-4 col-sm-6" style="margin-bottom: 14px;">
                    <div style="border: 2px solid {$rm.status_meta.color|escape:'html':'UTF-8'}; background: {$rm.status_meta.bg|escape:'html':'UTF-8'}; border-radius: 6px; padding: 12px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 6px;">
                            <strong style="font-size: 16px;">{$rm.room_num|escape:'html':'UTF-8'}</strong>
                            <span class="label label-{$rm.status_meta.badge|escape:'html':'UTF-8'}">
                                {$rm.status_meta.label|escape:'html':'UTF-8'}
                            </span>
                        </div>
                        <div style="font-size: 12px; color: #374151; margin-bottom: 8px;">
                            <strong>{$rm.room_type|escape:'html':'UTF-8'}</strong><br>
                            {if $rm.occ_status == 2}
                                <span style="color: #1d4ed8; font-weight: bold;">In-House: {$rm.guest_name|escape:'html':'UTF-8'}</span>
                            {elseif $rm.occ_status == 1}
                                <span style="color: #7c3aed; font-weight: bold;">Reserved Today: {$rm.guest_name|escape:'html':'UTF-8'}</span>
                            {else}
                                <span style="color: #15803d;">Vacant</span>
                            {/if}
                        </div>

                        <form method="post" action="{$lvh_controller_url|escape:'html':'UTF-8'}">
                            <input type="hidden" name="id_room" value="{$rm.id_room|intval}" />
                            <div class="form-group" style="margin-bottom: 6px;">
                                <select name="hk_status" class="form-control input-sm">
                                    {foreach from=$lvh_defs key=sKey item=sDef}
                                        <option value="{$sKey|escape:'html':'UTF-8'}" {if $rm.hk_status == $sKey}selected="selected"{/if}>
                                            {$sDef.label|escape:'html':'UTF-8'}
                                        </option>
                                    {/foreach}
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 6px;">
                                <input type="text" name="assigned_attendant" value="{$rm.assigned_attendant|escape:'html':'UTF-8'}" placeholder="{l s='Attendant name' mod='lvhhousekeeping'}" class="form-control input-sm" />
                            </div>
                            <div class="form-group" style="margin-bottom: 6px;">
                                <input type="text" name="maintenance_note" value="{$rm.maintenance_note|escape:'html':'UTF-8'}" placeholder="{l s='Notes / AC / Plumbing...' mod='lvhhousekeeping'}" class="form-control input-sm" />
                            </div>
                            <button type="submit" name="submitUpdateRoomHk" value="1" class="btn btn-xs btn-primary btn-block">
                                <i class="icon-save"></i> {l s='Save Room State' mod='lvhhousekeeping'}
                            </button>
                        </form>
                    </div>
                </div>
            {/foreach}
        </div>
    </div>
{/foreach}

<div class="panel">
    <div class="panel-heading">
        <i class="icon-time"></i> {l s='Recent Room Turnover & Maintenance Log' mod='lvhhousekeeping'}
    </div>
    <div class="table-responsive">
        <table class="table table-condensed table-striped">
            <thead>
                <tr>
                    <th>{l s='Timestamp' mod='lvhhousekeeping'}</th>
                    <th>{l s='Room' mod='lvhhousekeeping'}</th>
                    <th>{l s='Transition' mod='lvhhousekeeping'}</th>
                    <th>{l s='Note' mod='lvhhousekeeping'}</th>
                    <th>{l s='Updated By' mod='lvhhousekeeping'}</th>
                </tr>
            </thead>
            <tbody>
                {if empty($lvh_logs)}
                    <tr>
                        <td colspan="5" class="text-center text-muted">{l s='No housekeeping transitions logged yet.' mod='lvhhousekeeping'}</td>
                    </tr>
                {else}
                    {foreach from=$lvh_logs item=lg}
                        <tr>
                            <td>{$lg.date_add|escape:'html':'UTF-8'}</td>
                            <td><strong>{$lg.room_num|escape:'html':'UTF-8'}</strong></td>
                            <td>
                                <code>{$lg.old_status|escape:'html':'UTF-8'}</code> &rarr;
                                <strong>{$lg.new_status|escape:'html':'UTF-8'}</strong>
                            </td>
                            <td>{$lg.note|escape:'html':'UTF-8'}</td>
                            <td>{$lg.staff_name|escape:'html':'UTF-8'}</td>
                        </tr>
                    {/foreach}
                {/if}
            </tbody>
        </table>
    </div>
</div>
