{*
* Lush View Hotel - Staff Action & Security Audit Trail View
*
* @author    Lush View Hotel
* @copyright 2026 Lush View Hotel
* @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
*}

<div class="row" style="margin-bottom: 14px;">
    <div class="col-md-4 col-sm-4">
        <div class="panel" style="margin-bottom: 10px;">
            <div class="text-muted">{l s='Total Recorded Events' mod='lvhauditlog'}</div>
            <h3 style="margin: 4px 0 0;">{$lvh_stats.total|intval}</h3>
        </div>
    </div>
    <div class="col-md-4 col-sm-4">
        <div class="panel" style="margin-bottom: 10px;">
            <div class="text-muted">{l s='Logged Today' mod='lvhauditlog'}</div>
            <h3 style="margin: 4px 0 0;">{$lvh_stats.today|intval}</h3>
        </div>
    </div>
    <div class="col-md-4 col-sm-4">
        <div class="panel" style="margin-bottom: 10px;">
            <div class="text-muted">{l s='Warnings / Comps / Overrides' mod='lvhauditlog'}</div>
            <h3 style="margin: 4px 0 0; color: #b45309;">{$lvh_stats.warnings|intval}</h3>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-heading">
        <i class="icon-shield"></i> {l s='Filter Staff Activity Trail (Append-Only)' mod='lvhauditlog'}
    </div>
    <form method="post" action="{$lvh_controller_url|escape:'html':'UTF-8'}" class="form-inline">
        <div class="form-group" style="margin-right: 8px; margin-bottom: 8px;">
            <label>{l s='From' mod='lvhauditlog'}</label>
            <input type="date" name="date_from" value="{$lvh_filters.date_from|escape:'html':'UTF-8'}" class="form-control" />
        </div>
        <div class="form-group" style="margin-right: 8px; margin-bottom: 8px;">
            <label>{l s='To' mod='lvhauditlog'}</label>
            <input type="date" name="date_to" value="{$lvh_filters.date_to|escape:'html':'UTF-8'}" class="form-control" />
        </div>
        <div class="form-group" style="margin-right: 8px; margin-bottom: 8px;">
            <select name="event_code" class="form-control">
                <option value="">{l s='All Events' mod='lvhauditlog'}</option>
                {foreach from=$lvh_event_codes item=ec}
                    <option value="{$ec.event_code|escape:'html':'UTF-8'}" {if $lvh_filters.event_code == $ec.event_code}selected="selected"{/if}>
                        {$ec.event_code|escape:'html':'UTF-8'}
                    </option>
                {/foreach}
            </select>
        </div>
        <div class="form-group" style="margin-right: 8px; margin-bottom: 8px;">
            <select name="id_employee" class="form-control">
                <option value="0">{l s='All Staff' mod='lvhauditlog'}</option>
                {foreach from=$lvh_employees item=emp}
                    <option value="{$emp.id_employee|intval}" {if $lvh_filters.id_employee == $emp.id_employee}selected="selected"{/if}>
                        {$emp.employee_name|escape:'html':'UTF-8'} ({$emp.role_name|escape:'html':'UTF-8'})
                    </option>
                {/foreach}
            </select>
        </div>
        <div class="form-group" style="margin-right: 8px; margin-bottom: 8px;">
            <select name="severity" class="form-control">
                <option value="">{l s='All Severities' mod='lvhauditlog'}</option>
                <option value="info" {if $lvh_filters.severity == 'info'}selected="selected"{/if}>{l s='Info' mod='lvhauditlog'}</option>
                <option value="notice" {if $lvh_filters.severity == 'notice'}selected="selected"{/if}>{l s='Notice' mod='lvhauditlog'}</option>
                <option value="warning" {if $lvh_filters.severity == 'warning'}selected="selected"{/if}>{l s='Warning' mod='lvhauditlog'}</option>
                <option value="critical" {if $lvh_filters.severity == 'critical'}selected="selected"{/if}>{l s='Critical' mod='lvhauditlog'}</option>
            </select>
        </div>
        <div class="form-group" style="margin-right: 8px; margin-bottom: 8px;">
            <input type="text" name="q" value="{$lvh_filters.q|escape:'html':'UTF-8'}" placeholder="{l s='Search order, room, staff...' mod='lvhauditlog'}" class="form-control" />
        </div>
        <button type="submit" name="submitFilterAudit" class="btn btn-primary" style="margin-bottom: 8px;">
            <i class="icon-search"></i> {l s='Filter' mod='lvhauditlog'}
        </button>
        <button type="submit" name="exportLvhAuditCsv" value="1" class="btn btn-default" style="margin-bottom: 8px;">
            <i class="icon-download"></i> {l s='Export CSV' mod='lvhauditlog'}
        </button>
    </form>
</div>

<div class="panel">
    <div class="panel-heading">
        <i class="icon-list"></i> {l s='Audit Log Entries' mod='lvhauditlog'}
        <span class="badge">{$lvh_entries|@count}</span>
    </div>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>{l s='Time' mod='lvhauditlog'}</th>
                    <th>{l s='Staff Member' mod='lvhauditlog'}</th>
                    <th>{l s='Role' mod='lvhauditlog'}</th>
                    <th>{l s='Event' mod='lvhauditlog'}</th>
                    <th>{l s='Target' mod='lvhauditlog'}</th>
                    <th>{l s='Summary' mod='lvhauditlog'}</th>
                    <th>{l s='IP' mod='lvhauditlog'}</th>
                </tr>
            </thead>
            <tbody>
                {if empty($lvh_entries)}
                    <tr>
                        <td colspan="7" class="text-center text-muted">
                            {l s='No audit entries match your current filter.' mod='lvhauditlog'}
                        </td>
                    </tr>
                {else}
                    {foreach from=$lvh_entries item=row}
                        <tr>
                            <td style="white-space: nowrap;">{$row.date_add|escape:'html':'UTF-8'}</td>
                            <td><strong>{$row.employee_name|escape:'html':'UTF-8'}</strong></td>
                            <td><span class="label label-default">{$row.role_name|escape:'html':'UTF-8'}</span></td>
                            <td>
                                {if $row.severity == 'warning' || $row.severity == 'critical'}
                                    <span class="label label-warning">{$row.event_code|escape:'html':'UTF-8'}</span>
                                {else}
                                    <code>{$row.event_code|escape:'html':'UTF-8'}</code>
                                {/if}
                            </td>
                            <td>
                                {if $row.object_type}
                                    {$row.object_type|escape:'html':'UTF-8'} #{$row.object_id|intval}
                                {else}
                                    &mdash;
                                {/if}
                            </td>
                            <td>{$row.summary|escape:'html':'UTF-8'}</td>
                            <td class="text-muted"><small>{$row.ip_addr|escape:'html':'UTF-8'}</small></td>
                        </tr>
                    {/foreach}
                {/if}
            </tbody>
        </table>
    </div>
</div>
