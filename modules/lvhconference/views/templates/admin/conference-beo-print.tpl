{*
* Lush View Hotel - Printable Banquet Event Order (BEO) & Conference Receipt
*
* @author    Lush View Hotel
* @copyright 2026 Lush View Hotel
* @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
*}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Lush View Conference Hall BEO - {$lvh_conf.booking_ref|escape:'html':'UTF-8'}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif; color: #111827; padding: 24px; background: #f3f4f6; }
        .sheet { max-width: 190mm; margin: 0 auto; background: #fff; padding: 28px 34px; border: 1px solid #d1d5db; }
        h1 { margin: 0; color: #14532d; font-size: 22px; }
        table { width: 100%; border-collapse: collapse; margin: 14px 0; }
        th, td { border: 1px solid #e5e7eb; padding: 8px 10px; text-align: left; font-size: 13px; }
        th { background: #f9fafb; width: 32%; }
        .toolbar { text-align: center; margin-bottom: 14px; }
        .btn { padding: 8px 16px; background: #14532d; color: #fff; border: 0; border-radius: 4px; cursor: pointer; }
        @media print { body { background: #fff; padding: 0; } .toolbar { display: none; } .sheet { border: 0; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="btn" onclick="window.print();">Print Event Order &amp; Receipt</button>
    </div>
    <div class="sheet">
        <div style="text-align:center; border-bottom: 2px solid #14532d; padding-bottom: 10px;">
            <h1>LUSH VIEW HOTEL — CONFERENCE HALL</h1>
            <p style="margin:4px 0; font-size:12px;">BANQUET EVENT ORDER (BEO) &amp; OFFICIAL RECEIPT &bull; Printed {$lvh_printed_at|escape:'html':'UTF-8'}</p>
        </div>

        <table>
            <tr><th>Booking Reference</th><td><strong>{$lvh_conf.booking_ref|escape:'html':'UTF-8'}</strong> ({$lvh_conf.status|upper|escape:'html':'UTF-8'})</td></tr>
            <tr><th>Event Title</th><td><strong>{$lvh_conf.event_title|escape:'html':'UTF-8'}</strong></td></tr>
            <tr><th>Organization / Client</th><td>{$lvh_conf.organization|escape:'html':'UTF-8'} &mdash; {$lvh_conf.contact_name|escape:'html':'UTF-8'} ({$lvh_conf.contact_phone|escape:'html':'UTF-8'})</td></tr>
            <tr><th>Scheduled Window</th><td>{$lvh_conf.start_datetime|escape:'html':'UTF-8'} &rarr; {$lvh_conf.end_datetime|escape:'html':'UTF-8'}</td></tr>
            <tr><th>Package &amp; Setup</th><td>{$lvh_conf.package_label|escape:'html':'UTF-8'} &bull; {$lvh_conf.seating_layout|escape:'html':'UTF-8'} ({$lvh_conf.attendees|intval} Attendees)</td></tr>
            <tr><th>AV &amp; Catering</th><td>{$lvh_conf.equipment_catering|escape:'html':'UTF-8'}</td></tr>
            <tr><th>Total All-Inclusive Fee</th><td><strong>GHS {$lvh_conf.total_amount|string_format:"%.2f"}</strong></td></tr>
            <tr><th>Amount Paid</th><td>GHS {$lvh_conf.paid_amount|string_format:"%.2f"} ({$lvh_conf.payment_method|escape:'html':'UTF-8'} {if $lvh_conf.transaction_id}[Ref: {$lvh_conf.transaction_id|escape:'html':'UTF-8'}]{/if})</td></tr>
            <tr><th>Balance Due</th><td><strong>GHS {$lvh_conf.balance_due|string_format:"%.2f"}</strong></td></tr>
        </table>

        <div style="display:flex; justify-content:space-between; margin-top: 38px; font-size: 12px;">
            <div>Client Signature: ____________________________</div>
            <div>Lush View Hotel Representative: {$lvh_conf.created_by_name|escape:'html':'UTF-8'}</div>
        </div>
    </div>
</body>
</html>
