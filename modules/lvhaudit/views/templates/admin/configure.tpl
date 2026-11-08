{*
* Lush View Hotel - System & Operations Audit Configure Screen
*
* @author    Lush View Hotel
* @copyright 2026 Lush View Hotel
* @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
*}

<div class="panel">
    <div class="panel-heading">
        <i class="icon-stethoscope"></i> {l s='LVH Live System & Operations Audit' mod='lvhaudit'}
    </div>

    <div class="alert alert-info">
        <strong>{l s='100% Read-Only & Privacy-Safe:' mod='lvhaudit'}</strong>
        {l s='This audit runs strictly read-only SELECT and file-inspection checks. It never modifies your database or files, never reads config/settings.inc.php, and automatically redacts passwords, cryptographic keys, API secrets, bank details, and all guest/staff personal data (names, emails, phones, addresses).' mod='lvhaudit'}
    </div>

    {if isset($lvh_audit_error) && $lvh_audit_error}
        <div class="alert alert-danger">
            {$lvh_audit_error|escape:'html':'UTF-8'}
        </div>
    {/if}

    <div class="row" style="margin-bottom: 18px;">
        <div class="col-md-3 col-sm-6">
            <div class="well well-sm" style="margin-bottom: 10px;">
                <div class="text-muted">{l s='Runtime Stack' mod='lvhaudit'}</div>
                <h4 style="margin: 6px 0 2px;">QloApps {$lvh_kpis.qloapps_version|escape:'html':'UTF-8'}</h4>
                <small>PHP {$lvh_kpis.php_version|escape:'html':'UTF-8'} &middot; DB {$lvh_kpis.db_version|escape:'html':'UTF-8'}</small>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="well well-sm" style="margin-bottom: 10px;">
                <div class="text-muted">{l s='Database Footprint' mod='lvhaudit'}</div>
                <h4 style="margin: 6px 0 2px;">{$lvh_kpis.tables_count|intval} {l s='tables' mod='lvhaudit'}</h4>
                <small>{$lvh_kpis.db_size_mb|escape:'html':'UTF-8'} MB &middot; {$lvh_kpis.total_orders|intval} {l s='orders' mod='lvhaudit'}</small>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="well well-sm" style="margin-bottom: 10px;">
                <div class="text-muted">{l s='Code & Modules' mod='lvhaudit'}</div>
                <h4 style="margin: 6px 0 2px;">{$lvh_kpis.modules_active|intval} {l s='active modules' mod='lvhaudit'}</h4>
                <small>
                    {$lvh_kpis.modified_core_files|intval} {l s='modified vs' mod='lvhaudit'} {$lvh_kpis.baseline_tag|escape:'html':'UTF-8'}
                    &middot; {$lvh_kpis.override_files|intval} {l s='overrides' mod='lvhaudit'}
                </small>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="well well-sm" style="margin-bottom: 10px;">
                <div class="text-muted">{l s='Hotel Inventory' mod='lvhaudit'}</div>
                <h4 style="margin: 6px 0 2px;">{$lvh_kpis.room_types_count|intval} {l s='room types' mod='lvhaudit'}</h4>
                <small>{$lvh_kpis.physical_rooms_count|intval} {l s='physical rooms' mod='lvhaudit'}</small>
            </div>
        </div>
    </div>

    <p>
        <strong>{l s='Detected custom / third-party modules:' mod='lvhaudit'}</strong>
        <code>{$lvh_kpis.modules_custom_list|escape:'html':'UTF-8'}</code>
        &nbsp;&middot;&nbsp;
        <strong>{l s='DB-only orphan modules:' mod='lvhaudit'}</strong>
        <code>{$lvh_kpis.modules_orphans|intval}</code>
    </p>

    <hr />

    <form action="{$lvh_form_action|escape:'html':'UTF-8'}" method="post" class="form-horizontal">
        <div class="form-group">
            <div class="col-lg-12">
                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="lvh_include_custom_files" value="1" checked="checked" />
                        <strong>{l s='Include custom modules (e.g. cashpayment), override/ files, and any modified templates/code in bundle' mod='lvhaudit'}</strong>
                        <span class="help-block" style="margin-top: 4px;">
                            {l s='Recommended. Packs non-standard module source files and any modified core/theme files (strictly excluding config/settings.inc.php, caches, logs, and guest uploads) so custom modules and 1.8.0 compatibility can be built from your exact live setup.' mod='lvhaudit'}
                        </span>
                    </label>
                </div>
            </div>
        </div>

        <div class="panel-footer" style="margin-top: 15px;">
            {if $lvh_kpis.zip_supported}
                <button type="submit" name="submitLvhAuditDownloadZip" value="1" class="btn btn-default btn-lg pull-right" style="background-color: #166534; color: #fff; border-color: #14532d; margin-left: 10px;">
                    <i class="process-icon-save" style="color: #fff;"></i> {l s='Download Full Audit Bundle (.zip)' mod='lvhaudit'}
                </button>
            {/if}
            <button type="submit" name="submitLvhAuditDownloadJson" value="1" class="btn btn-default pull-right">
                <i class="process-icon-export"></i> {l s='Download JSON Report Only (.json)' mod='lvhaudit'}
            </button>
            <div class="clearfix"></div>
        </div>
    </form>
</div>

<div class="panel">
    <div class="panel-heading">
        <i class="icon-check"></i> {l s='How to use & clean up' mod='lvhaudit'}
    </div>
    <ol style="margin-bottom: 0; line-height: 1.8;">
        <li>{l s='Click "Download Full Audit Bundle (.zip)" above to save the audit archive to your computer.' mod='lvhaudit'}</li>
        <li>{l s='Upload that .zip file directly into your workspace chat so we can design your tailored LVH modules and finalize pre-flight checks.' mod='lvhaudit'}</li>
        <li>{l s='Optional: return to Modules and Services and click Uninstall / Delete on "LVH Audit" at any time — it leaves zero database tables or hooks behind.' mod='lvhaudit'}</li>
    </ol>
</div>
