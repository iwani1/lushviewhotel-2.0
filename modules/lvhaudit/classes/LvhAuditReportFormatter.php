<?php
/**
 * Lush View Hotel - Executive Markdown & Back-Office Summary Formatter.
 *
 * Formats the structured array from LvhAuditCollector into:
 *   1. audit-summary.md inside the downloadable audit bundle
 *   2. Quick KPI cards displayed on the module Configure screen
 *
 * @author    Lush View Hotel
 * @copyright 2026 Lush View Hotel
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class LvhAuditReportFormatter
{
    /**
     * Extract headline KPIs for the Back-Office Configure screen.
     *
     * @param array $r
     * @return array
     */
    public static function extractDashboardKpis($r)
    {
        $qloVer = isset($r['environment']['qloapps']['code_version']) ? $r['environment']['qloapps']['code_version'] : 'unknown';
        $phpVer = isset($r['environment']['php']['version']) ? $r['environment']['php']['version'] : 'unknown';
        $dbVer = isset($r['environment']['database_server']['version']) ? $r['environment']['database_server']['version'] : 'unknown';
        $tables = isset($r['database']['total_tables']) ? (int) $r['database']['total_tables'] : 0;
        $dbSizeMb = (isset($r['database']['total_data_mb']) ? (float) $r['database']['total_data_mb'] : 0.0)
            + (isset($r['database']['total_index_mb']) ? (float) $r['database']['total_index_mb'] : 0.0);

        $modCounts = isset($r['modules']['counts']) ? $r['modules']['counts'] : array();
        $codeSummary = isset($r['code_integrity']['summary']) ? $r['code_integrity']['summary'] : array();

        $roomTypesCount = isset($r['hotel_catalog']['room_types']) ? count($r['hotel_catalog']['room_types']) : 0;
        $physRoomsCount = isset($r['hotel_catalog']['physical_rooms']) ? count($r['hotel_catalog']['physical_rooms']) : 0;
        $totalOrders = isset($r['operations_analytics']['totals']['orders']['total_orders'])
            ? (int) $r['operations_analytics']['totals']['orders']['total_orders']
            : 0;

        $customModNames = array();
        if (!empty($r['modules']['custom_or_third_party_modules'])) {
            foreach ($r['modules']['custom_or_third_party_modules'] as $cm) {
                $customModNames[] = $cm['name'] . ($cm['disk_version'] ? ' (' . $cm['disk_version'] . ')' : '');
            }
        }

        return array(
            'qloapps_version' => $qloVer,
            'php_version' => $phpVer,
            'db_version' => $dbVer,
            'tables_count' => $tables,
            'db_size_mb' => round($dbSizeMb, 2),
            'modules_active' => isset($modCounts['active_in_db']) ? (int) $modCounts['active_in_db'] : 0,
            'modules_custom_count' => isset($modCounts['custom_or_third_party']) ? (int) $modCounts['custom_or_third_party'] : 0,
            'modules_custom_list' => !empty($customModNames) ? implode(', ', $customModNames) : 'None',
            'modules_orphans' => isset($modCounts['db_only_orphans']) ? (int) $modCounts['db_only_orphans'] : 0,
            'modified_core_files' => isset($codeSummary['modified_upstream_files']) ? (int) $codeSummary['modified_upstream_files'] : 0,
            'override_files' => isset($codeSummary['active_override_files']) ? (int) $codeSummary['active_override_files'] : 0,
            'baseline_tag' => isset($r['code_integrity']['compared_against_upstream_tag']) ? $r['code_integrity']['compared_against_upstream_tag'] : 'v1.7.0',
            'room_types_count' => $roomTypesCount,
            'physical_rooms_count' => $physRoomsCount,
            'total_orders' => $totalOrders,
            'duration_ms' => isset($r['meta']['duration_ms']) ? (int) $r['meta']['duration_ms'] : 0,
            'zip_supported' => class_exists('ZipArchive'),
        );
    }

    /**
     * Render a concise human-readable Markdown summary to pair with audit-report.json.
     *
     * @param array $r
     * @param array $bundledFiles
     * @return string
     */
    public static function toMarkdown($r, $bundledFiles = array())
    {
        $k = self::extractDashboardKpis($r);
        $md = array();
        $md[] = '# Lush View Hotel — Live System & Operations Audit';
        $md[] = '';
        $md[] = '- **Generated (UTC):** ' . (isset($r['meta']['generated_at_utc']) ? $r['meta']['generated_at_utc'] : '');
        $md[] = '- **Collection Time:** ' . $k['duration_ms'] . ' ms (read-only; zero DB/file writes)';
        $md[] = '- **Privacy Mode:** Strict — zero credentials, keys, guest PII, or employee PII exported';
        $md[] = '';

        $md[] = '## 1. Runtime & 1.8.0 Readiness';
        $md[] = '- **QloApps Code / DB Version:** `' . $k['qloapps_version'] . '` / `'
            . (isset($r['environment']['qloapps']['db_version']) ? $r['environment']['qloapps']['db_version'] : '') . '`';
        $md[] = '- **PHP Runtime:** `' . $k['php_version'] . '` (`'
            . (isset($r['environment']['php']['sapi']) ? $r['environment']['php']['sapi'] : '') . '`, memory `'
            . (isset($r['environment']['php']['memory_limit']) ? $r['environment']['php']['memory_limit'] : '') . '`, max_execution_time `'
            . (isset($r['environment']['php']['max_execution_time']) ? $r['environment']['php']['max_execution_time'] : '') . '`)';
        $md[] = '- **Database Server:** `' . $k['db_version'] . '` (`' . $k['tables_count'] . '` tables, `' . $k['db_size_mb'] . ' MB`)';
        $missingExts = isset($r['environment']['readiness_for_1_8_0']['missing_required_extensions'])
            ? $r['environment']['readiness_for_1_8_0']['missing_required_extensions']
            : array();
        $md[] = '- **Missing 1.8.0 PHP Extensions:** ' . (empty($missingExts) ? 'None (all present)' : implode(', ', $missingExts));
        $md[] = '';

        $md[] = '## 2. Code Integrity & Modules';
        $md[] = '- **Baseline Compared:** `' . $k['baseline_tag'] . '`';
        $md[] = '- **Modified Upstream Files:** `' . $k['modified_core_files'] . '`';
        $md[] = '- **Active `override/` Files:** `' . $k['override_files'] . '`';
        $md[] = '- **Active Modules in DB:** `' . $k['modules_active'] . '`';
        $md[] = '- **Custom / Non-Standard Modules:** ' . $k['modules_custom_list'];
        $md[] = '- **DB-Only Orphan Modules:** `' . $k['modules_orphans'] . '`';
        $revActive = isset($r['modules']['security_watch_qlohotelreview']['active_in_db'])
            ? (int) $r['modules']['security_watch_qlohotelreview']['active_in_db']
            : 0;
        $md[] = '- **`qlohotelreview` Status (CVE-2025-67325 watch):** ' . ($revActive ? 'ACTIVE (disable recommended)' : 'Disabled / Not active');
        if (!empty($r['code_integrity']['modified_files'])) {
            $md[] = '';
            $md[] = '### Modified Upstream Files';
            foreach ($r['code_integrity']['modified_files'] as $mf) {
                $md[] = '- `' . $mf['path'] . '` (`' . $mf['bytes'] . '` bytes, mtime `' . $mf['mtime_utc'] . ' UTC`)';
            }
        }
        $md[] = '';

        $md[] = '## 3. Hotel Catalog & Room Inventory';
        $md[] = '- **Room Types:** `' . $k['room_types_count'] . '`';
        $md[] = '- **Physical Rooms:** `' . $k['physical_rooms_count'] . '`';
        if (!empty($r['hotel_catalog']['room_types'])) {
            $md[] = '';
            $md[] = '| ID | Room Type | Base Price | Active | Max Guests | Images |';
            $md[] = '|---:|---|---:|:---:|---:|---:|';
            foreach ($r['hotel_catalog']['room_types'] as $rt) {
                $md[] = '| ' . (int) $rt['id_product']
                    . ' | ' . str_replace('|', '/', (string) $rt['room_type_name'])
                    . ' | ' . round((float) $rt['base_price_tax_excl'], 2)
                    . ' | ' . ((int) $rt['active'] ? 'Yes' : 'No')
                    . ' | ' . (int) $rt['max_guests']
                    . ' | ' . (int) $rt['image_count'] . ' |';
            }
        }
        $md[] = '';

        $md[] = '## 4. Operations & Payment Workflows (Aggregated)';
        $md[] = '- **Total Orders Recorded:** `' . $k['total_orders'] . '`';
        if (!empty($r['payments_and_finance']['orders_by_payment_module'])) {
            $md[] = '';
            $md[] = '### Payment Modules Used';
            $md[] = '| Module | Label | Currency | Orders | Total Invoiced | Total Paid Real |';
            $md[] = '|---|---|---|---:|---:|---:|';
            foreach ($r['payments_and_finance']['orders_by_payment_module'] as $pm) {
                $md[] = '| `' . $pm['module'] . '`'
                    . ' | ' . str_replace('|', '/', (string) $pm['payment'])
                    . ' | ' . $pm['currency']
                    . ' | ' . (int) $pm['order_count']
                    . ' | ' . $pm['total_invoiced']
                    . ' | ' . $pm['total_paid_real'] . ' |';
            }
        }
        $md[] = '';

        $md[] = '## 5. Bundled Custom / Modified Files in Archive';
        $md[] = '- **Files Included:** `' . count($bundledFiles) . '`';
        foreach (array_keys($bundledFiles) as $bPath) {
            $md[] = '  - `' . $bPath . '`';
        }
        $md[] = '';

        return implode("\n", $md);
    }
}
