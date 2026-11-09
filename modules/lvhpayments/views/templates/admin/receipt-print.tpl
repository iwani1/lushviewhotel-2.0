{*
* Lush View Hotel - Standalone Printable Guest Receipt (80mm Thermal or A4)
*
* @author    Lush View Hotel
* @copyright 2026 Lush View Hotel
* @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
*}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Lush View Hotel Receipt - {$lvh_order.reference|escape:'html':'UTF-8'}</title>
    <style>
        {literal}
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            color: #111827;
            margin: 0;
            padding: 28px;
            background: #f3f4f6;
        }
        body.fmt-thermal { padding: 8px; }
        .receipt-sheet {
            background: #ffffff;
            margin: 0 auto;
            width: 190mm;
            padding: 28px 34px;
            border: 1px solid #d1d5db;
            font-size: 13px;
            line-height: 1.45;
        }
        body.fmt-thermal .receipt-sheet {
            width: 76mm;
            padding: 10px;
            font-size: 12px;
        }
        .header { text-align: center; border-bottom: 2px solid #14532d; padding-bottom: 10px; margin-bottom: 12px; }
        .header h1 { margin: 0; font-size: 24px; color: #14532d; letter-spacing: 0.5px; }
        body.fmt-thermal .header h1 { font-size: 17px; }
        .header p { margin: 2px 0; color: #4b5563; font-size: 12px; }
        body.fmt-thermal .header p { font-size: 11px; }
        .meta-grid { width: 100%; margin-bottom: 12px; border-collapse: collapse; }
        .meta-grid td { padding: 3px 0; vertical-align: top; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        table.items th, table.items td {
            border-bottom: 1px solid #e5e7eb;
            padding: 8px 6px;
            text-align: left;
        }
        body.fmt-thermal table.items th, body.fmt-thermal table.items td { padding: 5px 2px; }
        table.items th { background: #f9fafb; font-weight: 600; font-size: 11px; text-transform: uppercase; }
        .text-right { text-align: right !important; }
        .totals { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .totals td { padding: 4px 2px; }
        .totals .grand td { font-weight: 700; font-size: 16px; border-top: 2px solid #111827; padding-top: 7px; }
        body.fmt-thermal .totals .grand td { font-size: 14px; }
        .footer { margin-top: 18px; text-align: center; border-top: 1px dashed #9ca3af; padding-top: 10px; color: #4b5563; font-size: 11px; }
        .toolbar { text-align: center; margin-bottom: 14px; }
        .btn { display: inline-block; padding: 7px 14px; background: #14532d; color: #fff; text-decoration: none; border-radius: 4px; border: 0; cursor: pointer; font-size: 13px; margin: 0 4px; }
        .btn-alt { background: #374151; }
        @media print {
            body, body.fmt-thermal { background: #ffffff; padding: 0; }
            .toolbar { display: none; }
            .receipt-sheet { border: 0; width: 100%; padding: 0; }
            body.fmt-thermal .receipt-sheet { padding: 2mm; }
        }
        {/literal}
    </style>
</head>
<body class="fmt-{$lvh_format|escape:'html':'UTF-8'}">
    <div class="toolbar">
        <button class="btn" onclick="window.print();">Print Receipt</button>
        <a class="btn btn-alt" href="{$lvh_switch_url|escape:'html':'UTF-8'}">
            Switch to {if $lvh_format == 'thermal'}A4 Format{else}80mm Thermal{/if}
        </a>
    </div>

    <div class="receipt-sheet">
        <div class="header">
            <h1>LUSH VIEW HOTEL</h1>
            <p>{$lvh_hotel_address|escape:'html':'UTF-8'}</p>
            {if $lvh_hotel_phone}<p>Tel: {$lvh_hotel_phone|escape:'html':'UTF-8'} | lushviewhotel.net</p>{/if}
            <p><strong>OFFICIAL GUEST RECEIPT</strong></p>
        </div>

        <table class="meta-grid">
            <tr>
                <td><strong>Receipt / Ref:</strong> {$lvh_order.reference|escape:'html':'UTF-8'} (#{$lvh_order.id_order|intval})</td>
                <td class="text-right"><strong>Date:</strong> {$lvh_printed_at|escape:'html':'UTF-8'}</td>
            </tr>
            <tr>
                <td colspan="2"><strong>Guest:</strong> {$lvh_guest_name|escape:'html':'UTF-8'}{if $lvh_guest_phone} ({$lvh_guest_phone|escape:'html':'UTF-8'}){/if}</td>
            </tr>
        </table>

        <table class="items">
            <thead>
                <tr>
                    <th>Room / Description</th>
                    <th>Stay / Dates</th>
                    <th class="text-right">Amount (GHS)</th>
                </tr>
            </thead>
            <tbody>
                {foreach from=$lvh_bookings item=bk}
                    <tr>
                        <td>
                            <strong>{$bk.room_num|escape:'html':'UTF-8'}</strong>
                            <br><small>{$bk.room_type|escape:'html':'UTF-8'}</small>
                        </td>
                        <td>
                            {$bk.date_from|date_format:"d M Y"} &rarr; {$bk.date_to|date_format:"d M Y"}
                        </td>
                        <td class="text-right">{$bk.total_price_tax_incl|string_format:"%.2f"}</td>
                    </tr>
                {/foreach}
                {foreach from=$lvh_extras item=ex}
                    <tr>
                        <td>
                            <strong>{$ex.category|escape:'html':'UTF-8'}</strong>: {$ex.description|escape:'html':'UTF-8'}
                        </td>
                        <td>{$ex.date_add|date_format:"d M Y"}</td>
                        <td class="text-right">{$ex.amount|string_format:"%.2f"}</td>
                    </tr>
                {/foreach}
            </tbody>
        </table>

        <table class="totals">
            <tr class="grand">
                <td>Total Charges (All-Inclusive GHS)</td>
                <td class="text-right">GHS {$lvh_grand_total|string_format:"%.2f"}</td>
            </tr>
            <tr>
                <td>Total Payments Received</td>
                <td class="text-right">GHS {$lvh_paid_total|string_format:"%.2f"}</td>
            </tr>
            <tr>
                <td><strong>Balance Due</strong></td>
                <td class="text-right"><strong>GHS {$lvh_balance_due|string_format:"%.2f"}</strong></td>
            </tr>
        </table>

        {if !empty($lvh_payments)}
            <h4 style="margin: 14px 0 6px; font-size: 12px; text-transform: uppercase;">Payment Breakdown</h4>
            <table class="items">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Method</th>
                        <th>Ref</th>
                        <th class="text-right">GHS</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach from=$lvh_payments item=pm}
                        <tr>
                            <td>{$pm.date_add|date_format:"d M H:i"}</td>
                            <td>{$pm.canonical_label|escape:'html':'UTF-8'}</td>
                            <td>{$pm.transaction_id|escape:'html':'UTF-8'}</td>
                            <td class="text-right">{$pm.amount|string_format:"%.2f"}</td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        {/if}

        <div class="footer">
            <p>Served by: {$lvh_staff_name|escape:'html':'UTF-8'}</p>
            <p>Check-In: 13:00 | Check-Out: 12:00 Noon &bull; Thank you for staying with Lush View Hotel!</p>
        </div>
    </div>
</body>
</html>
