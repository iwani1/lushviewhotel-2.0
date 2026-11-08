{*
* Lush View Hotel - Order View Guest ID, VIP Badge & Room Folio Extras Panel
*
* @author    Lush View Hotel
* @copyright 2026 Lush View Hotel
* @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
*}

<div class="panel">
    <div class="panel-heading">
        <i class="icon-user"></i> {l s='Lush View Guest ID & Room Folio Extras' mod='lvhfrontdesk'}
        {if $lvh_vip_tier}
            <span class="label label-warning pull-right">{$lvh_vip_tier|escape:'html':'UTF-8'} ({$lvh_stays_count|intval} {l s='Stays' mod='lvhfrontdesk'})</span>
        {/if}
    </div>

    <div class="well well-sm">
        <strong><i class="icon-credit-card"></i> {l s='Guest Identification (GTA / Police Register):' mod='lvhfrontdesk'}</strong>
        {if $lvh_guest_doc}
            <div style="margin-top: 4px;">
                <span class="label label-info">{$lvh_guest_doc.doc_type|escape:'html':'UTF-8'}</span>
                <code>{$lvh_guest_doc.doc_number|escape:'html':'UTF-8'}</code>
                &bull; {$lvh_guest_doc.nationality|escape:'html':'UTF-8'}
                <small class="text-muted">({$lvh_guest_doc.recorded_by_name|escape:'html':'UTF-8'})</small>
            </div>
        {else}
            <span class="text-danger">{l s='No ID recorded yet for this guest.' mod='lvhfrontdesk'}</span>
        {/if}

        <form method="post" action="{$lvh_post_url|escape:'html':'UTF-8'}" class="form-inline" style="margin-top: 8px;">
            <select name="lvh_doc_type" class="form-control input-sm">
                <option value="Ghana Card">Ghana Card</option>
                <option value="Passport">Passport</option>
                <option value="Driver License">Driver License</option>
                <option value="Voter ID">Voter ID</option>
                <option value="ECOWAS Card">ECOWAS Card</option>
            </select>
            <input type="text" name="lvh_doc_number" class="form-control input-sm" placeholder="{l s='ID Number...' mod='lvhfrontdesk'}" required="required" />
            <input type="text" name="lvh_nationality" class="form-control input-sm" value="Ghanaian" style="width:110px;" />
            <button type="submit" name="submitLvhSaveGuestDoc" value="1" class="btn btn-default btn-sm">
                <i class="icon-save"></i> {l s='Save ID' mod='lvhfrontdesk'}
            </button>
        </form>
    </div>

    <h4 style="margin-top: 12px;">{l s='Room Folio Extras (Restaurant / Laundry / Late Check-Out)' mod='lvhfrontdesk'}</h4>
    {if !empty($lvh_folio_extras)}
        <table class="table table-condensed table-striped">
            <thead>
                <tr>
                    <th>{l s='Date' mod='lvhfrontdesk'}</th>
                    <th>{l s='Category' mod='lvhfrontdesk'}</th>
                    <th>{l s='Description' mod='lvhfrontdesk'}</th>
                    <th class="text-right">{l s='GHS' mod='lvhfrontdesk'}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                {foreach from=$lvh_folio_extras item=fx}
                    <tr {if $fx.voided}style="text-decoration: line-through; opacity: 0.55;"{/if}>
                        <td>{$fx.date_add|date_format:"%d %b %H:%M"}</td>
                        <td><strong>{$fx.category|escape:'html':'UTF-8'}</strong></td>
                        <td>{$fx.description|escape:'html':'UTF-8'}</td>
                        <td class="text-right">{$fx.amount|string_format:"%.2f"}</td>
                        <td class="text-right">
                            {if !$fx.voided && $lvh_is_supervisor}
                                <form method="post" action="{$lvh_post_url|escape:'html':'UTF-8'}" style="display:inline;">
                                    <input type="hidden" name="id_folio_charge" value="{$fx.id_folio_charge|intval}" />
                                    <button type="submit" name="submitLvhVoidFolio" value="1" class="btn btn-danger btn-xs" title="Void Extra">
                                        <i class="icon-trash"></i>
                                    </button>
                                </form>
                            {/if}
                        </td>
                    </tr>
                {/foreach}
            </tbody>
        </table>
    {/if}

    <form method="post" action="{$lvh_post_url|escape:'html':'UTF-8'}" class="form-inline">
        <select name="lvh_extra_category" class="form-control input-sm">
            <option value="Restaurant / Bar">{l s='Restaurant / Bar' mod='lvhfrontdesk'}</option>
            <option value="Laundry">{l s='Laundry' mod='lvhfrontdesk'}</option>
            <option value="Late Check-Out">{l s='Late Check-Out' mod='lvhfrontdesk'}</option>
            <option value="Extra Mattress / Linen">{l s='Extra Mattress / Linen' mod='lvhfrontdesk'}</option>
            <option value="Minibar / Water">{l s='Minibar / Water' mod='lvhfrontdesk'}</option>
            <option value="Damage / Replacement">{l s='Damage / Replacement' mod='lvhfrontdesk'}</option>
        </select>
        <input type="text" name="lvh_extra_desc" class="form-control input-sm" placeholder="{l s='Item / Slip #...' mod='lvhfrontdesk'}" required="required" />
        <input type="number" step="0.01" min="0.01" name="lvh_extra_amount" class="form-control input-sm" placeholder="GHS 0.00" style="width:105px;" required="required" />
        <button type="submit" name="submitLvhAddFolioExtra" value="1" class="btn btn-primary btn-sm">
            <i class="icon-plus"></i> {l s='Post to Folio' mod='lvhfrontdesk'}
        </button>
    </form>
</div>
