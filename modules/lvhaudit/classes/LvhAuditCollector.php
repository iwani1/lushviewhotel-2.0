<?php
/**
 * Lush View Hotel - Read-Only System, Schema, Code & Operations Audit Collector.
 *
 * Strictly SELECT-only against the database and read-only on the filesystem.
 * Never reads config/settings.inc.php, never exports credentials/keys, and
 * never exports guest or employee PII.
 *
 * Compatible with PHP 5.6 - 8.5 and QloApps 1.6.x / 1.7.0 / 1.8.0.
 *
 * @author    Lush View Hotel
 * @copyright 2026 Lush View Hotel
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class LvhAuditCollector
{
    /** @var Db */
    protected $db;

    /** @var string */
    protected $prefix;

    /** @var int */
    protected $idLang;

    /** @var array table_name_with_prefix => array of table metadata */
    protected $tables = array();

    /** @var array table_name_with_prefix => array(col_name => column metadata) */
    protected $columns = array();

    /** @var array collector warnings/errors (non-fatal) */
    protected $diagnostics = array();

    /** @var array cached code integrity result for reuse by zip bundler */
    protected $lastCodeIntegrity = null;

    /** @var array cached modules result for reuse by zip bundler */
    protected $lastModulesAudit = null;

    public function __construct()
    {
        $this->db = Db::getInstance();
        $this->prefix = _DB_PREFIX_;
        $ctx = Context::getContext();
        $this->idLang = (isset($ctx->language) && isset($ctx->language->id) && (int) $ctx->language->id > 0)
            ? (int) $ctx->language->id
            : (int) Configuration::get('PS_LANG_DEFAULT');
        if ($this->idLang <= 0) {
            $this->idLang = 1;
        }
        $this->loadSchemaMap();
    }

    /**
     * Run the full 10-domain read-only audit and return a structured array.
     *
     * @return array
     */
    public function collectAll()
    {
        $startedAt = microtime(true);

        $report = array(
            'meta' => array(
                'schema_version' => '1.0.0',
                'module' => 'lvhaudit',
                'module_version' => '1.0.0',
                'generated_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
                'generated_at_local' => date('Y-m-d H:i:s P'),
                'privacy_mode' => 'strict_redacted_no_pii_no_secrets',
                'read_only' => true,
            ),
            'environment' => $this->runSafe('environment', 'collectEnvironment'),
            'database' => $this->runSafe('database', 'collectDatabase'),
            'modules' => $this->runSafe('modules', 'collectModules'),
            'code_integrity' => $this->runSafe('code_integrity', 'collectCodeIntegrity'),
            'staff_and_access' => $this->runSafe('staff_and_access', 'collectStaffAndAccess'),
            'hotel_catalog' => $this->runSafe('hotel_catalog', 'collectHotelCatalog'),
            'operations_analytics' => $this->runSafe('operations_analytics', 'collectOperationsAnalytics'),
            'payments_and_finance' => $this->runSafe('payments_and_finance', 'collectPaymentsAndFinance'),
            'localization_and_tax' => $this->runSafe('localization_and_tax', 'collectLocalizationAndTax'),
            'branding_content_and_logs' => $this->runSafe('branding_content_and_logs', 'collectBrandingContentAndLogs'),
        );

        $report['meta']['duration_ms'] = (int) round((microtime(true) - $startedAt) * 1000);
        $report['meta']['collector_diagnostics'] = $this->diagnostics;

        return $report;
    }

    /**
     * Load information_schema.TABLES and information_schema.COLUMNS into memory
     * in two fast queries so every collector can check table/column existence
     * in O(1) without risking SQL errors across QloApps versions.
     */
    protected function loadSchemaMap()
    {
        $tRows = $this->queryRows(
            "SELECT TABLE_NAME, ENGINE, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH,
                    AUTO_INCREMENT, TABLE_COLLATION, CREATE_TIME, UPDATE_TIME
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
             ORDER BY TABLE_NAME"
        );
        foreach ($tRows as $r) {
            $name = $r['TABLE_NAME'];
            $this->tables[$name] = $r;
            $this->columns[$name] = array();
        }

        $cRows = $this->queryRows(
            "SELECT TABLE_NAME, COLUMN_NAME, ORDINAL_POSITION, COLUMN_TYPE,
                    IS_NULLABLE, COLUMN_KEY, COLUMN_DEFAULT, EXTRA
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
             ORDER BY TABLE_NAME, ORDINAL_POSITION"
        );
        foreach ($cRows as $c) {
            $tName = $c['TABLE_NAME'];
            if (!isset($this->columns[$tName])) {
                $this->columns[$tName] = array();
            }
            $this->columns[$tName][$c['COLUMN_NAME']] = array(
                'pos' => (int) $c['ORDINAL_POSITION'],
                'type' => $c['COLUMN_TYPE'],
                'null' => ($c['IS_NULLABLE'] === 'YES'),
                'key' => $c['COLUMN_KEY'],
                'default' => $c['COLUMN_DEFAULT'],
                'extra' => $c['EXTRA'],
            );
        }
    }

    /**
     * Execute a section collector safely so one unexpected quirk never aborts
     * the rest of the audit.
     *
     * @param string $section
     * @param string $method
     * @return array
     */
    protected function runSafe($section, $method)
    {
        try {
            return $this->{$method}();
        } catch (Exception $e) {
            $msg = LvhAuditRedactor::scrubText($e->getMessage());
            $this->diagnostics[] = $section . ': ' . $msg;
            return array('error' => $msg);
        } catch (Error $e) {
            $msg = LvhAuditRedactor::scrubText($e->getMessage());
            $this->diagnostics[] = $section . ': ' . $msg;
            return array('error' => $msg);
        }
    }

    /**
     * Run a SELECT query and always return an array of rows.
     *
     * @param string $sql
     * @return array
     */
    protected function queryRows($sql)
    {
        try {
            $res = $this->db->executeS($sql, true, false);
            return is_array($res) ? $res : array();
        } catch (Exception $e) {
            $this->diagnostics[] = 'SQL warning: ' . LvhAuditRedactor::scrubText($e->getMessage());
            return array();
        } catch (Error $e) {
            $this->diagnostics[] = 'SQL error: ' . LvhAuditRedactor::scrubText($e->getMessage());
            return array();
        }
    }

    /**
     * Run a scalar SELECT query safely.
     *
     * @param string $sql
     * @return mixed
     */
    protected function queryValue($sql)
    {
        try {
            return $this->db->getValue($sql, false);
        } catch (Exception $e) {
            $this->diagnostics[] = 'SQL scalar warning: ' . LvhAuditRedactor::scrubText($e->getMessage());
            return null;
        } catch (Error $e) {
            $this->diagnostics[] = 'SQL scalar error: ' . LvhAuditRedactor::scrubText($e->getMessage());
            return null;
        }
    }

    /**
     * Check whether `{prefix}{short}` exists in the current database.
     *
     * @param string $shortTable
     * @return bool
     */
    protected function hasTable($shortTable)
    {
        return isset($this->tables[$this->prefix . $shortTable]);
    }

    /**
     * Check whether `{prefix}{short}.{column}` exists in the current database.
     *
     * @param string $shortTable
     * @param string $column
     * @return bool
     */
    protected function hasColumn($shortTable, $column)
    {
        $full = $this->prefix . $shortTable;
        return isset($this->columns[$full]) && isset($this->columns[$full][$column]);
    }

    /**
     * Full prefixed table name.
     *
     * @param string $shortTable
     * @return string
     */
    protected function tbl($shortTable)
    {
        return '`' . bqSQL($this->prefix . $shortTable) . '`';
    }

    /* ======================================================================
     * 1. ENVIRONMENT & 1.8.0 READINESS
     * ====================================================================== */

    /**
     * Collect QloApps, PHP, extension, MySQL/MariaDB, URL, SSL and cache info.
     *
     * @return array
     */
    protected function collectEnvironment()
    {
        $reqExts = array('gd', 'pdo_mysql', 'curl', 'soap', 'openssl', 'simplexml', 'zip', 'phar', 'dom');
        $optExts = array('mbstring', 'intl', 'Zend OPcache', 'imagick', 'bcmath', 'exif');
        $extCheck = array();
        foreach ($reqExts as $ext) {
            $extCheck[$ext] = extension_loaded($ext);
        }
        $optCheck = array();
        foreach ($optExts as $ext) {
            $optCheck[$ext] = extension_loaded($ext);
        }

        $dbVars = array();
        foreach (array(
            'SELECT VERSION()' => 'version',
            'SELECT @@version_comment' => 'version_comment',
            'SELECT @@sql_mode' => 'sql_mode',
            'SELECT @@character_set_database' => 'charset_database',
            'SELECT @@collation_database' => 'collation_database',
            'SELECT @@time_zone' => 'db_time_zone',
            'SELECT @@system_time_zone' => 'db_system_time_zone',
            'SELECT @@max_allowed_packet' => 'max_allowed_packet',
            'SELECT NOW()' => 'db_now',
        ) as $q => $k) {
            $dbVars[$k] = (string) $this->queryValue($q);
        }

        $shopUrls = array();
        if ($this->hasTable('shop_url')) {
            $shopUrls = $this->queryRows(
                'SELECT id_shop, domain, domain_ssl, physical_uri, virtual_uri, main, active FROM ' . $this->tbl('shop_url')
            );
        }

        $adminDirStatus = 'unknown';
        if (defined('_PS_ADMIN_DIR_')) {
            $base = basename(_PS_ADMIN_DIR_);
            if ($base === 'admin' || $base === 'admin-dev') {
                $adminDirStatus = 'default_unrenamed (' . $base . ')';
            } else {
                $adminDirStatus = 'custom_renamed (len=' . strlen($base) . ')';
            }
        }

        $phpVer = PHP_VERSION;
        $php180Ready = (version_compare($phpVer, '8.2.0', '>=') && version_compare($phpVer, '8.6.0', '<'));
        $missingReqExts = array();
        foreach ($extCheck as $eName => $loaded) {
            if (!$loaded) {
                $missingReqExts[] = $eName;
            }
        }

        return array(
            'qloapps' => array(
                'code_version' => defined('_PS_VERSION_') ? _PS_VERSION_ : 'unknown',
                'db_version' => (string) Configuration::get('PS_VERSION_DB'),
                'install_version' => defined('_PS_INSTALL_VERSION_') ? _PS_INSTALL_VERSION_ : '',
                'dev_mode' => defined('_PS_MODE_DEV_') ? (bool) _PS_MODE_DEV_ : false,
                'debug_profiling' => defined('_PS_DEBUG_PROFILING_') ? (bool) _PS_DEBUG_PROFILING_ : false,
                'db_prefix' => $this->prefix,
                'admin_directory_status' => $adminDirStatus,
                'install_dir_present' => is_dir(_PS_ROOT_DIR_ . '/install') || is_dir(_PS_ROOT_DIR_ . '/install-dev'),
                'htaccess_present' => is_file(_PS_ROOT_DIR_ . '/.htaccess'),
                'robots_present' => is_file(_PS_ROOT_DIR_ . '/robots.txt'),
                'shop_enabled' => (bool) Configuration::get('PS_SHOP_ENABLE'),
                'multishop_active' => (bool) Configuration::get('PS_MULTISHOP_FEATURE_ACTIVE'),
                'ssl_enabled' => (bool) Configuration::get('PS_SSL_ENABLED'),
                'ssl_everywhere' => (bool) Configuration::get('PS_SSL_ENABLED_EVERYWHERE'),
                'friendly_urls' => (bool) Configuration::get('PS_REWRITING_SETTINGS'),
                'smarty_cache' => (bool) Configuration::get('PS_SMARTY_CACHE'),
                'smarty_force_compile' => (int) Configuration::get('PS_SMARTY_FORCE_COMPILE'),
                'ccc_css' => (bool) Configuration::get('PS_CSS_THEME_CACHE'),
                'ccc_js' => (bool) Configuration::get('PS_JS_THEME_CACHE'),
                'shop_urls' => $shopUrls,
            ),
            'php' => array(
                'version' => $phpVer,
                'sapi' => PHP_SAPI,
                'os' => PHP_OS,
                'pointer_size_bits' => PHP_INT_SIZE * 8,
                'memory_limit' => (string) ini_get('memory_limit'),
                'max_execution_time' => (string) ini_get('max_execution_time'),
                'upload_max_filesize' => (string) ini_get('upload_max_filesize'),
                'post_max_size' => (string) ini_get('post_max_size'),
                'max_input_vars' => (string) ini_get('max_input_vars'),
                'allow_url_fopen' => (bool) ini_get('allow_url_fopen'),
                'file_uploads' => (bool) ini_get('file_uploads'),
                'open_basedir_restricted' => (ini_get('open_basedir') !== '' && ini_get('open_basedir') !== false),
                'disable_functions' => (string) ini_get('disable_functions'),
                'php_timezone' => date_default_timezone_get(),
                'ps_timezone' => (string) Configuration::get('PS_TIMEZONE'),
                'required_extensions_1_8_0' => $extCheck,
                'optional_extensions' => $optCheck,
                'all_loaded_extensions' => get_loaded_extensions(),
            ),
            'database_server' => $dbVars,
            'readiness_for_1_8_0' => array(
                'php_8_2_to_8_5_active_now' => $php180Ready,
                'note_cpanel_php_selector' => $php180Ready
                    ? 'Current PHP already satisfies QloApps 1.8.0 (>=8.2 <8.6).'
                    : 'Running PHP ' . $phpVer . ' right now (expected for 1.7.0); switch cPanel MultiPHP to PHP 8.2/8.3 during cutover step 4.6.',
                'missing_required_extensions' => $missingReqExts,
                'allow_url_fopen_ok' => (bool) ini_get('allow_url_fopen'),
            ),
        );
    }

    /* ======================================================================
     * 2. DATABASE SCHEMA, SIZES, COLLATIONS & SPECIAL TABLES
     * ====================================================================== */

    /**
     * Collect exact row counts, byte sizes, collations, schema signature, and
     * key migration watchpoints (passwd widths, payment decimals, bed types).
     *
     * @return array
     */
    protected function collectDatabase()
    {
        $tableSummaries = array();
        $collationCounts = array();
        $engineCounts = array();
        $totalDataBytes = 0;
        $totalIndexBytes = 0;

        foreach ($this->tables as $tName => $meta) {
            $approxRows = (int) $meta['TABLE_ROWS'];
            $exactRows = $approxRows;
            if ($approxRows < 250000) {
                $val = $this->queryValue('SELECT COUNT(*) FROM `' . bqSQL($tName) . '`');
                if ($val !== null && $val !== false) {
                    $exactRows = (int) $val;
                }
            }
            $dBytes = (int) $meta['DATA_LENGTH'];
            $iBytes = (int) $meta['INDEX_LENGTH'];
            $totalDataBytes += $dBytes;
            $totalIndexBytes += $iBytes;

            $col = (string) $meta['TABLE_COLLATION'];
            $eng = (string) $meta['ENGINE'];
            $collationCounts[$col] = isset($collationCounts[$col]) ? $collationCounts[$col] + 1 : 1;
            $engineCounts[$eng] = isset($engineCounts[$eng]) ? $engineCounts[$eng] + 1 : 1;

            $tableSummaries[$tName] = array(
                'rows' => $exactRows,
                'engine' => $eng,
                'collation' => $col,
                'data_bytes' => $dBytes,
                'index_bytes' => $iBytes,
                'auto_increment' => $meta['AUTO_INCREMENT'] !== null ? (int) $meta['AUTO_INCREMENT'] : null,
                'column_count' => isset($this->columns[$tName]) ? count($this->columns[$tName]) : 0,
            );
        }

        // Migration watchpoints (passwd column widths, payment decimal precision, bed types)
        $watchColumns = array();
        foreach (array(
            array('employee', 'passwd'),
            array('customer', 'passwd'),
            array('referrer', 'passwd'),
            array('order_payment', 'amount'),
            array('order_payment_detail', 'amount'),
        ) as $pair) {
            $full = $this->prefix . $pair[0];
            $cName = $pair[1];
            $watchColumns[$full . '.' . $cName] = (isset($this->columns[$full]) && isset($this->columns[$full][$cName]))
                ? $this->columns[$full][$cName]['type']
                : 'ABSENT';
        }

        $bedTypes = array();
        if ($this->hasTable('htl_bed_type')) {
            if ($this->hasTable('htl_bed_type_lang')) {
                $bedTypes = $this->queryRows(
                    'SELECT b.*, bl.id_lang, bl.name FROM ' . $this->tbl('htl_bed_type') . ' b
                     LEFT JOIN ' . $this->tbl('htl_bed_type_lang') . ' bl ON (b.id_bed_type = bl.id_bed_type)'
                );
            } else {
                $bedTypes = $this->queryRows('SELECT * FROM ' . $this->tbl('htl_bed_type'));
            }
        }
        $roomBedLinks = array();
        if ($this->hasTable('htl_room_type_bed_type')) {
            $roomBedLinks = $this->queryRows('SELECT * FROM ' . $this->tbl('htl_room_type_bed_type'));
        }

        return array(
            'total_tables' => count($this->tables),
            'total_data_mb' => round($totalDataBytes / 1048576, 2),
            'total_index_mb' => round($totalIndexBytes / 1048576, 2),
            'engines' => $engineCounts,
            'collations' => $collationCounts,
            'migration_watch_columns' => $watchColumns,
            'dormant_bed_type_data' => array(
                'bed_types' => $bedTypes,
                'room_type_bed_links' => $roomBedLinks,
            ),
            'tables' => $tableSummaries,
            'schema_columns' => $this->columns,
        );
    }

    /* ======================================================================
     * 3. MODULES AUDIT (DISK VS DATABASE, CUSTOM MODULES, PAYMENT MATRIX)
     * ====================================================================== */

    /**
     * Cross-reference modules on disk with qlo_module, flagging custom modules,
     * orphans, uninstalled modules, CVE status, and payment restrictions.
     *
     * @return array
     */
    protected function collectModules()
    {
        $upstreamAll = array_flip(LvhAuditBaseline::getUpstreamModules('all'));

        $dbModules = array();
        if ($this->hasTable('module')) {
            $rows = $this->queryRows('SELECT id_module, name, active, version FROM ' . $this->tbl('module') . ' ORDER BY name');
            foreach ($rows as $r) {
                $dbModules[$r['name']] = array(
                    'id_module' => (int) $r['id_module'],
                    'name' => $r['name'],
                    'active' => (int) $r['active'],
                    'db_version' => (string) $r['version'],
                );
            }
        }

        $diskModules = array();
        if (is_dir(_PS_MODULE_DIR_)) {
            $entries = scandir(_PS_MODULE_DIR_);
            sort($entries);
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..' || $entry[0] === '.') {
                    continue;
                }
                $mPath = _PS_MODULE_DIR_ . $entry;
                if (!is_dir($mPath)) {
                    continue;
                }
                $mainPhp = $mPath . '/' . $entry . '.php';
                $meta = $this->extractModuleHeaderMeta($entry, $mainPhp);
                $stats = $this->dirQuickStats($mPath);

                $origin = 'custom_or_third_party';
                if (isset($upstreamAll[$entry])) {
                    $origin = 'upstream_qloapps';
                } elseif (strpos($entry, 'lvh') === 0) {
                    $origin = 'lvh_first_party';
                }

                $diskModules[$entry] = array(
                    'name' => $entry,
                    'origin' => $origin,
                    'has_main_php' => is_file($mainPhp),
                    'disk_version' => $meta['version'],
                    'author' => $meta['author'],
                    'tab' => $meta['tab'],
                    'display_name' => $meta['display_name'],
                    'file_count' => $stats['files'],
                    'total_bytes' => $stats['bytes'],
                    'has_override_dir' => is_dir($mPath . '/override'),
                    'has_upgrade_dir' => is_dir($mPath . '/upgrade'),
                    'has_controllers' => is_dir($mPath . '/controllers'),
                    'installed_in_db' => isset($dbModules[$entry]),
                    'active_in_db' => isset($dbModules[$entry]) ? (int) $dbModules[$entry]['active'] : 0,
                    'db_version' => isset($dbModules[$entry]) ? $dbModules[$entry]['db_version'] : null,
                );
            }
        }

        $dbOnlyOrphans = array();
        foreach ($dbModules as $mName => $info) {
            if (!isset($diskModules[$mName])) {
                $dbOnlyOrphans[] = $info;
            }
        }

        $customModules = array();
        $diskOnlyUninstalled = array();
        foreach ($diskModules as $mName => $info) {
            if ($info['origin'] === 'custom_or_third_party') {
                $customModules[] = $info;
            }
            if (!$info['installed_in_db']) {
                $diskOnlyUninstalled[] = $mName;
            }
        }

        $paymentCurrencies = array();
        if ($this->hasTable('module_currency') && $this->hasTable('module')) {
            $paymentCurrencies = $this->queryRows(
                'SELECT m.name AS module_name, mc.id_currency, mc.id_shop
                 FROM ' . $this->tbl('module_currency') . ' mc
                 JOIN ' . $this->tbl('module') . ' m ON (m.id_module = mc.id_module)
                 ORDER BY m.name, mc.id_currency'
            );
        }

        $paymentCountries = array();
        if ($this->hasTable('module_country') && $this->hasTable('module')) {
            $paymentCountries = $this->queryRows(
                'SELECT m.name AS module_name, COUNT(*) AS allowed_countries_count
                 FROM ' . $this->tbl('module_country') . ' mc
                 JOIN ' . $this->tbl('module') . ' m ON (m.id_module = mc.id_module)
                 GROUP BY m.name ORDER BY m.name'
            );
        }

        $reviewCheck = array(
            'present_on_disk' => isset($diskModules['qlohotelreview']),
            'installed_in_db' => isset($dbModules['qlohotelreview']),
            'active_in_db' => isset($dbModules['qlohotelreview']) ? (int) $dbModules['qlohotelreview']['active'] : 0,
            'disk_version' => isset($diskModules['qlohotelreview']) ? $diskModules['qlohotelreview']['disk_version'] : null,
        );

        $result = array(
            'counts' => array(
                'on_disk' => count($diskModules),
                'installed_in_db' => count($dbModules),
                'active_in_db' => count(array_filter($dbModules, array($this, 'isModuleActiveRow'))),
                'custom_or_third_party' => count($customModules),
                'db_only_orphans' => count($dbOnlyOrphans),
                'disk_only_uninstalled' => count($diskOnlyUninstalled),
            ),
            'security_watch_qlohotelreview' => $reviewCheck,
            'custom_or_third_party_modules' => $customModules,
            'db_only_orphans' => $dbOnlyOrphans,
            'disk_only_uninstalled' => $diskOnlyUninstalled,
            'payment_module_currencies' => $paymentCurrencies,
            'payment_module_country_counts' => $paymentCountries,
            'all_modules' => array_values($diskModules),
        );

        $this->lastModulesAudit = $result;
        return $result;
    }

    /**
     * Filter helper for active module rows.
     *
     * @param array $row
     * @return bool
     */
    public function isModuleActiveRow($row)
    {
        return !empty($row['active']);
    }

    /**
     * Parse version/author/tab/displayName statically from a module's main PHP
     * file without instantiating untrusted module constructors.
     *
     * @param string $modName
     * @param string $mainPhp
     * @return array
     */
    protected function extractModuleHeaderMeta($modName, $mainPhp)
    {
        $out = array(
            'version' => '',
            'author' => '',
            'tab' => '',
            'display_name' => $modName,
        );
        if (!is_file($mainPhp) || !is_readable($mainPhp)) {
            return $out;
        }
        $src = @file_get_contents($mainPhp, false, null, 0, 16384);
        if (!is_string($src)) {
            return $out;
        }
        if (preg_match('/\$this->version\s*=\s*[\'"]([^\'"]+)[\'"]/', $src, $m)) {
            $out['version'] = $m[1];
        }
        if (preg_match('/\$this->author\s*=\s*[\'"]([^\'"]+)[\'"]/', $src, $m)) {
            $out['author'] = $m[1];
        }
        if (preg_match('/\$this->tab\s*=\s*[\'"]([^\'"]+)[\'"]/', $src, $m)) {
            $out['tab'] = $m[1];
        }
        if (preg_match('/\$this->displayName\s*=\s*(?:\$this->l\(\s*)?[\'"]([^\'"]+)[\'"]/', $src, $m)) {
            $out['display_name'] = $m[1];
        }
        return $out;
    }

    /**
     * Count files and total bytes in a directory (bounded for speed).
     *
     * @param string $dir
     * @return array
     */
    protected function dirQuickStats($dir)
    {
        $files = 0;
        $bytes = 0;
        if (!is_dir($dir)) {
            return array('files' => 0, 'bytes' => 0);
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $item) {
            if ($item->isFile()) {
                $files++;
                $bytes += (int) $item->getSize();
                if ($files >= 5000) {
                    break;
                }
            }
        }
        return array('files' => $files, 'bytes' => $bytes);
    }

    /* ======================================================================
     * 4. CODE INTEGRITY, OVERRIDES & ACTIVE HOOKS
     * ====================================================================== */

    /**
     * Compare live core/theme/PDF/mail/upstream-module files against the
     * upstream QloApps baseline (CRLF-normalized SHA-256) and inventory
     * override/ + active hooks.
     *
     * @return array
     */
    protected function collectCodeIntegrity()
    {
        $psVer = defined('_PS_VERSION_') ? _PS_VERSION_ : '1.7.0.0';
        $baseInfo = LvhAuditBaseline::getFileBaselineForVersion($psVer);
        $baselineVersion = $baseInfo['baseline_version'];
        $expected = $baseInfo['files'];
        $upstreamMods = array_flip(LvhAuditBaseline::getUpstreamModules($baselineVersion));

        $modifiedFiles = array();
        $missingFiles = array();
        $matchedCount = 0;

        foreach ($expected as $rel => $expHash) {
            $abs = _PS_ROOT_DIR_ . '/' . $rel;
            if (!is_file($abs)) {
                $missingFiles[] = $rel;
                continue;
            }
            $actualHash = $this->normalizedHash16($abs);
            if ($actualHash === $expHash) {
                $matchedCount++;
            } else {
                $modifiedFiles[] = array(
                    'path' => $rel,
                    'expected_hash16' => $expHash,
                    'actual_hash16' => $actualHash,
                    'bytes' => (int) @filesize($abs),
                    'mtime_utc' => gmdate('Y-m-d H:i:s', (int) @filemtime($abs)),
                );
            }
        }

        // Scan for extra/added files inside core/theme/pdf/mails/upstream-modules
        $addedFiles = array();
        $scanRoots = array('classes', 'controllers', 'pdf', 'themes/hotel-reservation-theme', 'mails/en', 'modules');
        $exts = array('php' => 1, 'tpl' => 1, 'css' => 1, 'js' => 1, 'html' => 1);
        foreach ($scanRoots as $sr) {
            $fullSr = _PS_ROOT_DIR_ . '/' . $sr;
            if (!is_dir($fullSr)) {
                continue;
            }
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($fullSr, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $item) {
                if (!$item->isFile()) {
                    continue;
                }
                $fn = $item->getFilename();
                if ($fn === 'index.php' || $fn === 'config.xml' || substr($fn, -10) === '.cache.php') {
                    continue;
                }
                $ext = strtolower(pathinfo($fn, PATHINFO_EXTENSION));
                if (!isset($exts[$ext])) {
                    continue;
                }
                $abs = str_replace('\\', '/', $item->getPathname());
                $rootNorm = rtrim(str_replace('\\', '/', _PS_ROOT_DIR_), '/');
                if (strpos($abs, $rootNorm . '/') !== 0) {
                    continue;
                }
                $rel = substr($abs, strlen($rootNorm) + 1);
                if (strpos($rel, '/cache/') !== false || strpos($rel, '/translations/') !== false) {
                    continue;
                }
                $parts = explode('/', $rel);
                if ($parts[0] === 'modules' && isset($parts[1]) && !isset($upstreamMods[$parts[1]])) {
                    // Custom/LVH/1.8-only module already tracked in modules section
                    continue;
                }
                if (!isset($expected[$rel])) {
                    $addedFiles[] = array(
                        'path' => $rel,
                        'hash16' => $this->normalizedHash16($abs),
                        'bytes' => (int) $item->getSize(),
                        'mtime_utc' => gmdate('Y-m-d H:i:s', (int) $item->getMTime()),
                    );
                }
            }
        }

        // Inventory override/
        $overrideFiles = array();
        $overrideRoot = _PS_ROOT_DIR_ . '/override';
        if (is_dir($overrideRoot)) {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($overrideRoot, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $item) {
                if (!$item->isFile()) {
                    continue;
                }
                $fn = $item->getFilename();
                if ($fn === 'index.php' || $fn === '.htaccess' || $fn === 'readme_override.txt') {
                    continue;
                }
                $abs = str_replace('\\', '/', $item->getPathname());
                $rootNorm = rtrim(str_replace('\\', '/', _PS_ROOT_DIR_), '/');
                $rel = (strpos($abs, $rootNorm . '/') === 0) ? substr($abs, strlen($rootNorm) + 1) : $fn;
                $overrideFiles[] = array(
                    'path' => $rel,
                    'hash16' => $this->normalizedHash16($abs),
                    'bytes' => (int) $item->getSize(),
                    'mtime_utc' => gmdate('Y-m-d H:i:s', (int) $item->getMTime()),
                );
            }
        }

        // Registered hooks summary
        $hooks = array();
        if ($this->hasTable('hook_module') && $this->hasTable('hook') && $this->hasTable('module')) {
            $hooks = $this->queryRows(
                'SELECT h.name AS hook_name, m.name AS module_name, m.active AS module_active,
                        hm.id_shop, hm.position
                 FROM ' . $this->tbl('hook_module') . ' hm
                 JOIN ' . $this->tbl('hook') . ' h ON (h.id_hook = hm.id_hook)
                 JOIN ' . $this->tbl('module') . ' m ON (m.id_module = hm.id_module)
                 ORDER BY h.name, hm.position'
            );
        }

        $result = array(
            'compared_against_upstream_tag' => 'v' . $baselineVersion,
            'summary' => array(
                'baseline_total_files' => count($expected),
                'identical_to_upstream' => $matchedCount,
                'modified_upstream_files' => count($modifiedFiles),
                'missing_upstream_files' => count($missingFiles),
                'added_extra_files' => count($addedFiles),
                'active_override_files' => count($overrideFiles),
                'overrides_disabled_flag' => (bool) Configuration::get('PS_DISABLE_OVERRIDES'),
                'non_native_modules_disabled_flag' => (bool) Configuration::get('PS_DISABLE_NON_NATIVE_MODULES'),
            ),
            'modified_files' => $modifiedFiles,
            'added_files' => $addedFiles,
            'missing_files' => $missingFiles,
            'override_files' => $overrideFiles,
            'hook_registrations' => $hooks,
        );

        $this->lastCodeIntegrity = $result;
        return $result;
    }

    /**
     * Compute 16-hex normalized SHA-256 (CRLF -> LF) of a text file.
     *
     * @param string $path
     * @return string
     */
    protected function normalizedHash16($path)
    {
        $data = @file_get_contents($path);
        if ($data === false) {
            return 'unreadable';
        }
        $data = str_replace("\r\n", "\n", $data);
        return substr(hash('sha256', $data), 0, 16);
    }

    /* ======================================================================
     * 5. STAFF ROLES, MENU TABS & PERMISSIONS (ZERO EMPLOYEE PII)
     * ====================================================================== */

    /**
     * Collect employee role profiles, headcounts per role (no names/emails),
     * back-office tab structure, and role permission matrix.
     *
     * @return array
     */
    protected function collectStaffAndAccess()
    {
        $profiles = array();
        if ($this->hasTable('profile') && $this->hasTable('profile_lang')) {
            $profiles = $this->queryRows(
                'SELECT p.id_profile, pl.name AS role_name,
                        COALESCE(SUM(CASE WHEN e.active = 1 THEN 1 ELSE 0 END), 0) AS active_staff_count,
                        COALESCE(SUM(CASE WHEN e.active = 0 AND e.id_employee IS NOT NULL THEN 1 ELSE 0 END), 0) AS inactive_staff_count
                 FROM ' . $this->tbl('profile') . ' p
                 LEFT JOIN ' . $this->tbl('profile_lang') . ' pl
                   ON (pl.id_profile = p.id_profile AND pl.id_lang = ' . (int) $this->idLang . ')
                 LEFT JOIN ' . $this->tbl('employee') . ' e ON (e.id_profile = p.id_profile)
                 GROUP BY p.id_profile, pl.name
                 ORDER BY p.id_profile'
            );
        }

        $tabs = array();
        if ($this->hasTable('tab') && $this->hasTable('tab_lang')) {
            $tabs = $this->queryRows(
                'SELECT t.id_tab, t.id_parent, t.class_name, t.module, t.position, t.active, tl.name AS title
                 FROM ' . $this->tbl('tab') . ' t
                 LEFT JOIN ' . $this->tbl('tab_lang') . ' tl
                   ON (tl.id_tab = t.id_tab AND tl.id_lang = ' . (int) $this->idLang . ')
                 ORDER BY t.id_parent, t.position, t.id_tab'
            );
        }

        $nonSuperAdminAccess = array();
        if ($this->hasTable('access') && $this->hasTable('tab')) {
            $nonSuperAdminAccess = $this->queryRows(
                'SELECT a.id_profile, t.class_name, t.module, a.`view`, a.`add`, a.`edit`, a.`delete`
                 FROM ' . $this->tbl('access') . ' a
                 JOIN ' . $this->tbl('tab') . ' t ON (t.id_tab = a.id_tab)
                 WHERE a.id_profile > 1 AND (a.`view` = 1 OR a.`add` = 1 OR a.`edit` = 1 OR a.`delete` = 1)
                 ORDER BY a.id_profile, t.class_name'
            );
        }

        $hotelBranchAccess = array();
        if ($this->hasTable('htl_access')) {
            $hotelBranchAccess = $this->queryRows('SELECT * FROM ' . $this->tbl('htl_access') . ' ORDER BY id_profile, id_hotel');
        }

        return array(
            'roles_and_headcount' => $profiles,
            'admin_tabs' => $tabs,
            'non_superadmin_tab_permissions' => $nonSuperAdminAccess,
            'hotel_branch_permissions' => $hotelBranchAccess,
        );
    }

    /* ======================================================================
     * 6. HOTEL PROPERTY, ROOM TYPES, PHYSICAL ROOMS & PRICING SETUP
     * ====================================================================== */

    /**
     * Collect hotel branch setup, room types, physical room numbers/statuses,
     * extra demands/services, features/amenities, advance payment rules, and
     * seasonal/feature pricing rules.
     *
     * @return array
     */
    protected function collectHotelCatalog()
    {
        $hotels = array();
        if ($this->hasTable('htl_branch_info') && $this->hasTable('htl_branch_info_lang')) {
            $hotels = $this->queryRows(
                'SELECT b.id, b.id_category, b.check_in, b.check_out, b.rating, b.active,
                        b.active_refund, b.latitude, b.longitude, b.map_formated_address,
                        bl.hotel_name, CHAR_LENGTH(COALESCE(bl.description, \'\')) AS description_len,
                        CHAR_LENGTH(COALESCE(bl.policies, \'\')) AS policies_len
                 FROM ' . $this->tbl('htl_branch_info') . ' b
                 LEFT JOIN ' . $this->tbl('htl_branch_info_lang') . ' bl
                   ON (bl.id = b.id AND bl.id_lang = ' . (int) $this->idLang . ')
                 ORDER BY b.id'
            );
        }

        $roomStatuses = array();
        if ($this->hasTable('htl_room_status')) {
            $roomStatuses = $this->queryRows('SELECT * FROM ' . $this->tbl('htl_room_status') . ' ORDER BY id');
        }

        $roomTypes = array();
        if ($this->hasTable('htl_room_type') && $this->hasTable('product') && $this->hasTable('product_lang')) {
            $roomTypes = $this->queryRows(
                'SELECT rt.id AS id_htl_room_type, rt.id_product, rt.id_hotel,
                        p.reference, p.price AS base_price_tax_excl, p.id_tax_rules_group, p.active,
                        pl.name AS room_type_name, pl.link_rewrite,
                        CHAR_LENGTH(COALESCE(pl.description_short, \'\')) AS short_desc_len,
                        CHAR_LENGTH(COALESCE(pl.description, \'\')) AS full_desc_len,
                        rt.adults AS base_adults, rt.children AS base_children,
                        rt.max_adults, rt.max_children, rt.max_guests, rt.min_los, rt.max_los,
                        (SELECT COUNT(*) FROM ' . $this->tbl('image') . ' i WHERE i.id_product = p.id_product) AS image_count
                 FROM ' . $this->tbl('htl_room_type') . ' rt
                 JOIN ' . $this->tbl('product') . ' p ON (p.id_product = rt.id_product)
                 LEFT JOIN ' . $this->tbl('product_lang') . ' pl
                   ON (pl.id_product = p.id_product AND pl.id_lang = ' . (int) $this->idLang . ' AND pl.id_shop = 1)
                 ORDER BY rt.id_hotel, rt.id_product'
            );
        }

        $physicalRooms = array();
        if ($this->hasTable('htl_room_information')) {
            $physicalRooms = $this->queryRows(
                'SELECT id, id_product, id_hotel, room_num, id_status, floor,
                        (CASE WHEN comment IS NOT NULL AND TRIM(comment) <> \'\' THEN 1 ELSE 0 END) AS has_comment
                 FROM ' . $this->tbl('htl_room_information') . '
                 ORDER BY id_hotel, id_product, id'
            );
        }

        $disabledDatesSummary = array();
        if ($this->hasTable('htl_room_disable_dates')) {
            $disabledDatesSummary = $this->queryRows(
                'SELECT id_room_type, COUNT(*) AS block_entries, MIN(date_from) AS earliest_from, MAX(date_to) AS latest_to
                 FROM ' . $this->tbl('htl_room_disable_dates') . '
                 GROUP BY id_room_type ORDER BY id_room_type'
            );
        }

        $globalDemands = array();
        if ($this->hasTable('htl_room_type_global_demand') && $this->hasTable('htl_room_type_global_demand_lang')) {
            $globalDemands = $this->queryRows(
                'SELECT gd.id_global_demand, gdl.name, gd.price, gd.id_tax_rules_group, gd.price_calc_method,
                        (SELECT COUNT(*) FROM ' . $this->tbl('htl_room_type_demand') . ' rtd
                         WHERE rtd.id_global_demand = gd.id_global_demand) AS assigned_room_types
                 FROM ' . $this->tbl('htl_room_type_global_demand') . ' gd
                 LEFT JOIN ' . $this->tbl('htl_room_type_global_demand_lang') . ' gdl
                   ON (gdl.id_global_demand = gd.id_global_demand AND gdl.id_lang = ' . (int) $this->idLang . ')
                 ORDER BY gd.id_global_demand'
            );
        }

        $hotelFeatures = array();
        if ($this->hasTable('htl_features') && $this->hasTable('htl_features_lang')) {
            $hotelFeatures = $this->queryRows(
                'SELECT f.id, f.parent_feature_id, f.active, fl.name
                 FROM ' . $this->tbl('htl_features') . ' f
                 LEFT JOIN ' . $this->tbl('htl_features_lang') . ' fl
                   ON (fl.id = f.id AND fl.id_lang = ' . (int) $this->idLang . ')
                 ORDER BY f.parent_feature_id, f.position, f.id'
            );
        }

        $roomFeatures = array();
        if ($this->hasTable('feature') && $this->hasTable('feature_lang')) {
            $roomFeatures = $this->queryRows(
                'SELECT f.id_feature, f.position, fl.name,
                        (SELECT COUNT(DISTINCT fp.id_product) FROM ' . $this->tbl('feature_product') . ' fp
                         WHERE fp.id_feature = f.id_feature) AS room_types_using
                 FROM ' . $this->tbl('feature') . ' f
                 LEFT JOIN ' . $this->tbl('feature_lang') . ' fl
                   ON (fl.id_feature = f.id_feature AND fl.id_lang = ' . (int) $this->idLang . ')
                 ORDER BY f.position, f.id_feature'
            );
        }

        $advancePayments = array();
        if ($this->hasTable('htl_advance_payment')) {
            $advancePayments = $this->queryRows('SELECT * FROM ' . $this->tbl('htl_advance_payment') . ' ORDER BY id');
        }

        $featurePricing = array();
        if ($this->hasTable('htl_room_type_feature_pricing')) {
            $featurePricing = $this->queryRows(
                'SELECT fp.id_feature_price, fp.id_product, fp.impact_way, fp.impact_type,
                        fp.impact_value, fp.active, fpl.feature_price_name
                 FROM ' . $this->tbl('htl_room_type_feature_pricing') . ' fp
                 LEFT JOIN ' . $this->tbl('htl_room_type_feature_pricing_lang') . ' fpl
                   ON (fpl.id_feature_price = fp.id_feature_price AND fpl.id_lang = ' . (int) $this->idLang . ')
                 ORDER BY fp.id_feature_price'
            );
        }

        $refundRules = array();
        if ($this->hasTable('htl_order_refund_rules')) {
            $refundRules = $this->queryRows(
                'SELECT r.*, rl.name FROM ' . $this->tbl('htl_order_refund_rules') . ' r
                 LEFT JOIN ' . $this->tbl('htl_order_refund_rules_lang') . ' rl
                   ON (rl.id_refund_rule = r.id_refund_rule AND rl.id_lang = ' . (int) $this->idLang . ')
                 ORDER BY r.id_refund_rule'
            );
        }

        $cartRulesSummary = array();
        if ($this->hasTable('cart_rule') && $this->hasTable('cart_rule_lang')) {
            $cartRulesSummary = $this->queryRows(
                'SELECT cr.id_cart_rule, crl.name, cr.date_from, cr.date_to, cr.quantity,
                        cr.reduction_percent, cr.reduction_amount, cr.active,
                        (CASE WHEN cr.code <> \'\' THEN 1 ELSE 0 END) AS requires_voucher_code
                 FROM ' . $this->tbl('cart_rule') . ' cr
                 LEFT JOIN ' . $this->tbl('cart_rule_lang') . ' crl
                   ON (crl.id_cart_rule = cr.id_cart_rule AND crl.id_lang = ' . (int) $this->idLang . ')
                 ORDER BY cr.id_cart_rule DESC LIMIT 50'
            );
        }

        return array(
            'hotel_branches' => $hotels,
            'room_status_vocabulary' => $roomStatuses,
            'room_types' => $roomTypes,
            'physical_rooms' => $physicalRooms,
            'room_disabled_dates_summary' => $disabledDatesSummary,
            'extra_demands_catalog' => $globalDemands,
            'hotel_amenities' => $hotelFeatures,
            'room_features' => $roomFeatures,
            'advance_payment_rules' => $advancePayments,
            'feature_seasonal_pricing_rules' => $featurePricing,
            'refund_rules' => $refundRules,
            'cart_discount_rules' => $cartRulesSummary,
        );
    }

    /* ======================================================================
     * 7. OPERATIONS & WORKFLOW ANALYTICS (100% AGGREGATED - ZERO GUEST PII)
     * ====================================================================== */

    /**
     * Collect anonymized operational metrics showing how staff and guests
     * actually use bookings, check-in/out, channels, stays, add-ons & documents.
     *
     * @return array
     */
    protected function collectOperationsAnalytics()
    {
        $totals = array();
        if ($this->hasTable('orders')) {
            $row = $this->queryRows(
                'SELECT COUNT(*) AS total_orders,
                        COALESCE(SUM(CASE WHEN valid = 1 THEN 1 ELSE 0 END), 0) AS valid_orders,
                        MIN(date_add) AS first_order_at,
                        MAX(date_add) AS latest_order_at,
                        COALESCE(SUM(CASE WHEN is_advance_payment = 1 THEN 1 ELSE 0 END), 0) AS advance_payment_orders,
                        COALESCE(SUM(CASE WHEN total_paid_real < total_paid_tax_incl - 0.01 THEN 1 ELSE 0 END), 0) AS balance_due_orders,
                        COALESCE(SUM(CASE WHEN total_paid_real > total_paid_tax_incl + 0.01 THEN 1 ELSE 0 END), 0) AS overpaid_orders
                 FROM ' . $this->tbl('orders')
            );
            $totals['orders'] = isset($row[0]) ? $row[0] : array();
        }

        $orderSources = array();
        if ($this->hasTable('orders') && $this->hasColumn('orders', 'source')) {
            $orderSources = $this->queryRows(
                'SELECT COALESCE(source, \'unspecified\') AS order_source, COUNT(*) AS order_count,
                        ROUND(SUM(total_paid_tax_incl), 2) AS gross_invoiced,
                        ROUND(SUM(total_paid_real), 2) AS total_collected
                 FROM ' . $this->tbl('orders') . '
                 GROUP BY source ORDER BY order_count DESC'
            );
        }

        $customerPatterns = array();
        if ($this->hasTable('customer') && $this->hasTable('orders')) {
            $cStats = $this->queryRows(
                'SELECT COUNT(*) AS total_customers,
                        COALESCE(SUM(CASE WHEN is_guest = 1 THEN 1 ELSE 0 END), 0) AS guest_checkout_accounts,
                        COALESCE(SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END), 0) AS active_customers
                 FROM ' . $this->tbl('customer') . ' WHERE deleted = 0'
            );
            $repeatRow = $this->queryRows(
                'SELECT
                    SUM(CASE WHEN cnt = 1 THEN 1 ELSE 0 END) AS one_time_bookers,
                    SUM(CASE WHEN cnt BETWEEN 2 AND 4 THEN 1 ELSE 0 END) AS repeat_2_to_4_orders,
                    SUM(CASE WHEN cnt >= 5 THEN 1 ELSE 0 END) AS loyal_5_plus_orders,
                    MAX(cnt) AS max_orders_single_customer
                 FROM (SELECT id_customer, COUNT(*) AS cnt FROM ' . $this->tbl('orders') . ' GROUP BY id_customer) x'
            );
            $customerPatterns = array(
                'accounts' => isset($cStats[0]) ? $cStats[0] : array(),
                'repeat_distribution' => isset($repeatRow[0]) ? $repeatRow[0] : array(),
            );
        }

        $bookingStatusDist = array();
        $bookingTypeDist = array();
        $stayLengthBuckets = array();
        $leadTimeBuckets = array();
        $monthlyTrend = array();
        $roomTypePerformance = array();

        if ($this->hasTable('htl_booking_detail')) {
            $bookingStatusDist = $this->queryRows(
                'SELECT id_status, is_cancelled, is_refunded, is_back_order,
                        COUNT(*) AS booking_rows,
                        SUM(GREATEST(DATEDIFF(date_to, date_from), 1)) AS room_nights
                 FROM ' . $this->tbl('htl_booking_detail') . '
                 GROUP BY id_status, is_cancelled, is_refunded, is_back_order
                 ORDER BY booking_rows DESC'
            );

            if ($this->hasColumn('htl_booking_detail', 'booking_type')) {
                $bookingTypeDist = $this->queryRows(
                    'SELECT booking_type, COUNT(*) AS booking_rows
                     FROM ' . $this->tbl('htl_booking_detail') . '
                     GROUP BY booking_type ORDER BY booking_rows DESC'
                );
            }

            $stayLengthBuckets = $this->queryRows(
                'SELECT
                    CASE
                        WHEN DATEDIFF(date_to, date_from) <= 1 THEN \'1_night\'
                        WHEN DATEDIFF(date_to, date_from) = 2 THEN \'2_nights\'
                        WHEN DATEDIFF(date_to, date_from) BETWEEN 3 AND 6 THEN \'3_to_6_nights\'
                        WHEN DATEDIFF(date_to, date_from) BETWEEN 7 AND 13 THEN \'7_to_13_nights\'
                        ELSE \'14_plus_nights\'
                    END AS stay_bucket,
                    COUNT(*) AS bookings,
                    ROUND(AVG(GREATEST(DATEDIFF(date_to, date_from), 1)), 2) AS avg_nights_in_bucket
                 FROM ' . $this->tbl('htl_booking_detail') . '
                 WHERE is_cancelled = 0
                 GROUP BY stay_bucket'
            );

            $leadTimeBuckets = $this->queryRows(
                'SELECT
                    CASE
                        WHEN DATEDIFF(DATE(date_from), DATE(date_add)) <= 0 THEN \'0_same_day_or_walkin\'
                        WHEN DATEDIFF(DATE(date_from), DATE(date_add)) BETWEEN 1 AND 3 THEN \'1_to_3_days_ahead\'
                        WHEN DATEDIFF(DATE(date_from), DATE(date_add)) BETWEEN 4 AND 14 THEN \'4_to_14_days_ahead\'
                        ELSE \'15_plus_days_ahead\'
                    END AS lead_bucket,
                    COUNT(*) AS bookings
                 FROM ' . $this->tbl('htl_booking_detail') . '
                 WHERE is_cancelled = 0
                 GROUP BY lead_bucket'
            );

            $monthlyTrend = $this->queryRows(
                'SELECT DATE_FORMAT(date_from, \'%Y-%m\') AS stay_month,
                        COUNT(*) AS room_bookings,
                        COUNT(DISTINCT id_order) AS distinct_orders,
                        SUM(CASE WHEN is_cancelled = 0 THEN GREATEST(DATEDIFF(date_to, date_from), 1) ELSE 0 END) AS active_room_nights,
                        SUM(CASE WHEN is_cancelled = 1 THEN 1 ELSE 0 END) AS cancelled_rows,
                        ROUND(SUM(CASE WHEN is_cancelled = 0 THEN total_price_tax_incl ELSE 0 END), 2) AS room_revenue_tax_incl
                 FROM ' . $this->tbl('htl_booking_detail') . '
                 GROUP BY stay_month
                 ORDER BY stay_month DESC
                 LIMIT 24'
            );

            $roomTypePerformance = $this->queryRows(
                'SELECT bd.id_product, COALESCE(pl.name, MAX(bd.room_type_name)) AS room_type_name,
                        COUNT(*) AS total_bookings,
                        SUM(CASE WHEN bd.is_cancelled = 0 THEN GREATEST(DATEDIFF(bd.date_to, bd.date_from), 1) ELSE 0 END) AS sold_room_nights,
                        ROUND(SUM(CASE WHEN bd.is_cancelled = 0 THEN bd.total_price_tax_incl ELSE 0 END), 2) AS gross_room_revenue,
                        ROUND(AVG(bd.adults), 2) AS avg_adults,
                        ROUND(AVG(bd.children), 2) AS avg_children
                 FROM ' . $this->tbl('htl_booking_detail') . ' bd
                 LEFT JOIN ' . $this->tbl('product_lang') . ' pl
                   ON (pl.id_product = bd.id_product AND pl.id_lang = ' . (int) $this->idLang . ' AND pl.id_shop = 1)
                 GROUP BY bd.id_product, pl.name
                 ORDER BY sold_room_nights DESC'
            );
        }

        $demandsOrdered = array();
        if ($this->hasTable('htl_booking_demands')) {
            $demandsOrdered = $this->queryRows(
                'SELECT name, COUNT(*) AS times_ordered, ROUND(SUM(total_price_tax_incl), 2) AS total_revenue
                 FROM ' . $this->tbl('htl_booking_demands') . '
                 GROUP BY name ORDER BY times_ordered DESC LIMIT 30'
            );
        }

        $guestDocUsage = array();
        if ($this->hasTable('htl_booking_document')) {
            $guestDocUsage = $this->queryRows(
                'SELECT COALESCE(file_type, \'unknown\') AS file_type, COUNT(*) AS uploaded_count,
                        MIN(date_add) AS first_upload, MAX(date_add) AS latest_upload
                 FROM ' . $this->tbl('htl_booking_document') . '
                 GROUP BY file_type'
            );
        }

        return array(
            'totals' => $totals,
            'order_sources' => $orderSources,
            'customer_patterns' => $customerPatterns,
            'booking_status_distribution' => $bookingStatusDist,
            'booking_type_distribution' => $bookingTypeDist,
            'length_of_stay_buckets' => $stayLengthBuckets,
            'booking_lead_time_buckets' => $leadTimeBuckets,
            'monthly_stay_trend_last_24m' => $monthlyTrend,
            'room_type_performance' => $roomTypePerformance,
            'extra_demands_ordered' => $demandsOrdered,
            'booking_documents_usage' => $guestDocUsage,
        );
    }

    /* ======================================================================
     * 8. PAYMENTS, ORDER STATES & FINANCE WORKFLOWS
     * ====================================================================== */

    /**
     * Collect payment methods, modules, split-tender habits, order state
     * distribution, refunds, and invoice setup (zero card/tx/guest PII).
     *
     * @return array
     */
    protected function collectPaymentsAndFinance()
    {
        $ordersByPaymentModule = array();
        if ($this->hasTable('orders') && $this->hasTable('currency')) {
            $ordersByPaymentModule = $this->queryRows(
                'SELECT o.module, o.payment, c.iso_code AS currency,
                        COUNT(*) AS order_count,
                        ROUND(SUM(o.total_paid_tax_incl), 2) AS total_invoiced,
                        ROUND(SUM(o.total_paid_real), 2) AS total_paid_real,
                        MIN(o.date_add) AS first_used,
                        MAX(o.date_add) AS last_used
                 FROM ' . $this->tbl('orders') . ' o
                 LEFT JOIN ' . $this->tbl('currency') . ' c ON (c.id_currency = o.id_currency)
                 GROUP BY o.module, o.payment, c.iso_code
                 ORDER BY order_count DESC'
            );
        }

        $paymentLedgerMethods = array();
        $splitTenderSummary = array();
        if ($this->hasTable('order_payment') && $this->hasTable('currency')) {
            $paymentLedgerMethods = $this->queryRows(
                'SELECT op.payment_method, c.iso_code AS currency,
                        COUNT(*) AS payment_rows,
                        ROUND(SUM(op.amount), 2) AS total_amount,
                        ROUND(MIN(op.amount), 2) AS min_amount,
                        ROUND(MAX(op.amount), 2) AS max_amount,
                        SUM(CASE WHEN op.transaction_id IS NOT NULL AND TRIM(op.transaction_id) <> \'\' THEN 1 ELSE 0 END) AS rows_with_tx_reference
                 FROM ' . $this->tbl('order_payment') . ' op
                 LEFT JOIN ' . $this->tbl('currency') . ' c ON (c.id_currency = op.id_currency)
                 GROUP BY op.payment_method, c.iso_code
                 ORDER BY payment_rows DESC'
            );

            $splitRow = $this->queryRows(
                'SELECT
                    SUM(CASE WHEN cnt = 1 THEN 1 ELSE 0 END) AS single_payment_orders,
                    SUM(CASE WHEN cnt = 2 THEN 1 ELSE 0 END) AS two_installment_orders,
                    SUM(CASE WHEN cnt >= 3 THEN 1 ELSE 0 END) AS three_plus_installment_orders,
                    MAX(cnt) AS max_payments_on_one_order
                 FROM (SELECT order_reference, COUNT(*) AS cnt FROM ' . $this->tbl('order_payment') . ' GROUP BY order_reference) p'
            );
            $splitTenderSummary = isset($splitRow[0]) ? $splitRow[0] : array();
        }

        $orderStates = array();
        if ($this->hasTable('order_state') && $this->hasTable('order_state_lang')) {
            $orderStates = $this->queryRows(
                'SELECT os.id_order_state, osl.name, os.module_name, os.invoice, os.send_email,
                        os.paid, os.logable, os.pdf_invoice, os.deleted, os.color,
                        (SELECT COUNT(*) FROM ' . $this->tbl('orders') . ' o WHERE o.current_state = os.id_order_state) AS current_orders_count
                 FROM ' . $this->tbl('order_state') . ' os
                 LEFT JOIN ' . $this->tbl('order_state_lang') . ' osl
                   ON (osl.id_order_state = os.id_order_state AND osl.id_lang = ' . (int) $this->idLang . ')
                 ORDER BY os.id_order_state'
            );
        }

        $invoiceStats = array(
            'invoicing_enabled' => (bool) Configuration::get('PS_INVOICE'),
            'invoice_prefix' => (string) Configuration::get('PS_INVOICE_PREFIX', $this->idLang),
            'total_invoices' => $this->hasTable('order_invoice')
                ? (int) $this->queryValue('SELECT COUNT(*) FROM ' . $this->tbl('order_invoice'))
                : 0,
            'total_credit_slips' => $this->hasTable('order_slip')
                ? (int) $this->queryValue('SELECT COUNT(*) FROM ' . $this->tbl('order_slip'))
                : 0,
        );

        return array(
            'orders_by_payment_module' => $ordersByPaymentModule,
            'order_payment_ledger_methods' => $paymentLedgerMethods,
            'split_payment_installments' => $splitTenderSummary,
            'order_states_and_usage' => $orderStates,
            'invoicing_and_slips' => $invoiceStats,
        );
    }

    /* ======================================================================
     * 9. LOCALIZATION, CURRENCIES, GUEST COUNTRIES & TAXES
     * ====================================================================== */

    /**
     * Collect currencies, languages, aggregated guest country distribution,
     * taxes, and tax rule groups.
     *
     * @return array
     */
    protected function collectLocalizationAndTax()
    {
        $currencies = $this->hasTable('currency')
            ? $this->queryRows('SELECT * FROM ' . $this->tbl('currency') . ' ORDER BY id_currency')
            : array();

        $languages = $this->hasTable('lang')
            ? $this->queryRows('SELECT id_lang, name, active, iso_code, language_code, date_format_lite, date_format_full FROM ' . $this->tbl('lang') . ' ORDER BY id_lang')
            : array();

        $guestCountries = array();
        if ($this->hasTable('address') && $this->hasTable('country') && $this->hasTable('country_lang')) {
            $guestCountries = $this->queryRows(
                'SELECT c.iso_code, cl.name AS country_name, COUNT(*) AS address_records
                 FROM ' . $this->tbl('address') . ' a
                 JOIN ' . $this->tbl('country') . ' c ON (c.id_country = a.id_country)
                 LEFT JOIN ' . $this->tbl('country_lang') . ' cl
                   ON (cl.id_country = c.id_country AND cl.id_lang = ' . (int) $this->idLang . ')
                 WHERE a.deleted = 0
                 GROUP BY c.iso_code, cl.name
                 ORDER BY address_records DESC
                 LIMIT 20'
            );
        }

        $taxes = array();
        if ($this->hasTable('tax') && $this->hasTable('tax_lang')) {
            $taxes = $this->queryRows(
                'SELECT t.id_tax, tl.name, t.rate, t.active, t.deleted
                 FROM ' . $this->tbl('tax') . ' t
                 LEFT JOIN ' . $this->tbl('tax_lang') . ' tl
                   ON (tl.id_tax = t.id_tax AND tl.id_lang = ' . (int) $this->idLang . ')
                 ORDER BY t.id_tax'
            );
        }

        $taxRuleGroups = array();
        if ($this->hasTable('tax_rules_group')) {
            $taxRuleGroups = $this->queryRows(
                'SELECT trg.id_tax_rules_group, trg.name, trg.active, trg.deleted,
                        (SELECT COUNT(*) FROM ' . $this->tbl('tax_rule') . ' tr
                         WHERE tr.id_tax_rules_group = trg.id_tax_rules_group) AS rules_count,
                        (SELECT GROUP_CONCAT(DISTINCT CONCAT(c.iso_code, \':\', t.rate, \'%\') SEPARATOR \', \')
                         FROM ' . $this->tbl('tax_rule') . ' tr
                         LEFT JOIN ' . $this->tbl('country') . ' c ON (c.id_country = tr.id_country)
                         LEFT JOIN ' . $this->tbl('tax') . ' t ON (t.id_tax = tr.id_tax)
                         WHERE tr.id_tax_rules_group = trg.id_tax_rules_group) AS country_rates_preview
                 FROM ' . $this->tbl('tax_rules_group') . ' trg
                 ORDER BY trg.id_tax_rules_group'
            );
        }

        return array(
            'defaults' => array(
                'default_currency_id' => (int) Configuration::get('PS_CURRENCY_DEFAULT'),
                'default_country_id' => (int) Configuration::get('PS_COUNTRY_DEFAULT'),
                'default_lang_id' => (int) Configuration::get('PS_LANG_DEFAULT'),
                'taxes_enabled' => (bool) Configuration::get('PS_TAX'),
                'tax_display_enabled' => (bool) Configuration::get('PS_TAX_DISPLAY'),
                'price_display_precision' => (int) Configuration::get('PS_PRICE_DISPLAY_PRECISION'),
            ),
            'currencies' => $currencies,
            'languages' => $languages,
            'guest_origin_countries_top20' => $guestCountries,
            'taxes' => $taxes,
            'tax_rules_groups' => $taxRuleGroups,
        );
    }

    /* ======================================================================
     * 10. BRANDING ASSETS, CMS, EMAILS, REDACTED CONFIG & LOGS
     * ====================================================================== */

    /**
     * Collect brand image metadata (custom vs stock QloApps), CMS pages,
     * navigation links, email delivery summary, redacted configuration keys,
     * webservice status, cron jobs, and scrubbed recent error logs.
     *
     * @return array
     */
    protected function collectBrandingContentAndLogs()
    {
        $stockBrandHashes = LvhAuditBaseline::getStockBrandImageHashes();
        $brandAssets = array();
        foreach ($stockBrandHashes as $rel => $stockList) {
            $abs = _PS_ROOT_DIR_ . '/' . $rel;
            if (!is_file($abs)) {
                $brandAssets[$rel] = array('exists' => false);
                continue;
            }
            $data = @file_get_contents($abs);
            $h16 = ($data !== false) ? substr(hash('sha256', $data), 0, 16) : '';
            $dims = @getimagesize($abs);
            $brandAssets[$rel] = array(
                'exists' => true,
                'bytes' => (int) @filesize($abs),
                'width' => is_array($dims) ? (int) $dims[0] : null,
                'height' => is_array($dims) ? (int) $dims[1] : null,
                'hash16' => $h16,
                'status' => in_array($h16, $stockList, true) ? 'qloapps_default_stock' : 'customized_hotel_artwork',
            );
        }

        $cmsPages = array();
        if ($this->hasTable('cms') && $this->hasTable('cms_lang')) {
            $cmsPages = $this->queryRows(
                'SELECT c.id_cms, c.active, c.position, cl.meta_title, cl.link_rewrite,
                        CHAR_LENGTH(COALESCE(cl.content, \'\')) AS content_len
                 FROM ' . $this->tbl('cms') . ' c
                 LEFT JOIN ' . $this->tbl('cms_lang') . ' cl
                   ON (cl.id_cms = c.id_cms AND cl.id_lang = ' . (int) $this->idLang . ')
                 ORDER BY c.position, c.id_cms'
            );
        }

        $navLinks = array();
        if ($this->hasTable('htl_custom_navigation_link') && $this->hasTable('htl_custom_navigation_link_lang')) {
            $navLinks = $this->queryRows(
                'SELECT n.id_navigation_link, nl.name, n.link, n.is_custom_link, n.id_cms,
                        n.show_at_navigation, n.show_at_footer, n.active
                 FROM ' . $this->tbl('htl_custom_navigation_link') . ' n
                 LEFT JOIN ' . $this->tbl('htl_custom_navigation_link_lang') . ' nl
                   ON (nl.id_navigation_link = n.id_navigation_link AND nl.id_lang = ' . (int) $this->idLang . ')
                 ORDER BY n.position'
            );
        }

        // Mail template activity (strictly excluding recipient email column)
        $mailActivity = array();
        if ($this->hasTable('mail')) {
            $mailActivity = $this->queryRows(
                'SELECT template, COUNT(*) AS sent_count, MIN(date_add) AS first_sent, MAX(date_add) AS last_sent
                 FROM ' . $this->tbl('mail') . '
                 GROUP BY template ORDER BY sent_count DESC LIMIT 40'
            );
        }

        // Redacted qlo_configuration snapshot
        $configs = array();
        if ($this->hasTable('configuration')) {
            $cRows = $this->queryRows(
                'SELECT name, value, id_shop, date_upd FROM ' . $this->tbl('configuration') . ' ORDER BY name, id_shop'
            );
            foreach ($cRows as $cr) {
                $configs[] = array(
                    'name' => $cr['name'],
                    'id_shop' => $cr['id_shop'] !== null ? (int) $cr['id_shop'] : null,
                    'value' => LvhAuditRedactor::redactConfig($cr['name'], $cr['value']),
                    'date_upd' => $cr['date_upd'],
                );
            }
        }

        // Webservice accounts summary (keys strictly excluded!)
        $webservice = array(
            'enabled' => (bool) Configuration::get('PS_WEBSERVICE'),
            'accounts_count' => 0,
            'resources' => array(),
        );
        if ($this->hasTable('webservice_account')) {
            $webservice['accounts_count'] = (int) $this->queryValue(
                'SELECT COUNT(*) FROM ' . $this->tbl('webservice_account') . ' WHERE active = 1'
            );
            if ($this->hasTable('webservice_permission')) {
                $webservice['resources'] = $this->queryRows(
                    'SELECT resource, GROUP_CONCAT(DISTINCT method ORDER BY method SEPARATOR \',\') AS methods
                     FROM ' . $this->tbl('webservice_permission') . '
                     GROUP BY resource ORDER BY resource'
                );
            }
        }

        // Cron jobs if cronjobs or qlocrontaskmanager exists
        $cronTasks = array();
        if ($this->hasTable('cronjobs')) {
            $cRows = $this->queryRows('SELECT id_cronjob, id_module, description, hour, day, month, day_of_week, updated_at, one_shot, active FROM ' . $this->tbl('cronjobs'));
            foreach ($cRows as $c) {
                $c['description'] = LvhAuditRedactor::scrubText($c['description']);
                $cronTasks[] = $c;
            }
        } elseif ($this->hasTable('qctm_cron_task')) {
            $cronTasks = $this->queryRows('SELECT id_cron_task, id_module, task_name, cron_expression, active, last_status, last_executed_at FROM ' . $this->tbl('qctm_cron_task'));
        }

        // Scrubbed recent DB logs
        $dbLogs = array(
            'severity_counts' => array(),
            'recent_entries' => array(),
        );
        if ($this->hasTable('log')) {
            $dbLogs['severity_counts'] = $this->queryRows(
                'SELECT severity, COUNT(*) AS cnt FROM ' . $this->tbl('log') . ' GROUP BY severity ORDER BY severity'
            );
            $recent = $this->queryRows(
                'SELECT severity, error_code, message, object_type, date_add
                 FROM ' . $this->tbl('log') . '
                 ORDER BY id_log DESC LIMIT 40'
            );
            foreach ($recent as $r) {
                $r['message'] = LvhAuditRedactor::scrubText($r['message']);
                $dbLogs['recent_entries'][] = $r;
            }
        }

        // Log files inventory on disk (sizes + scrubbed tail of PHP error_log if small/readable)
        $logFiles = array();
        foreach (array(_PS_ROOT_DIR_ . '/log', _PS_ROOT_DIR_) as $lDir) {
            if (!is_dir($lDir)) {
                continue;
            }
            foreach (scandir($lDir) as $fn) {
                if ($fn === '.' || $fn === '..' || $fn === 'index.php' || $fn === '.htaccess') {
                    continue;
                }
                if ($lDir === _PS_ROOT_DIR_ && $fn !== 'error_log' && substr($fn, -4) !== '.log') {
                    continue;
                }
                $fp = $lDir . '/' . $fn;
                if (is_file($fp)) {
                    $rel = ltrim(str_replace(str_replace('\\', '/', _PS_ROOT_DIR_), '', str_replace('\\', '/', $fp)), '/');
                    $logFiles[] = array(
                        'path' => $rel,
                        'bytes' => (int) @filesize($fp),
                        'mtime_utc' => gmdate('Y-m-d H:i:s', (int) @filemtime($fp)),
                        'tail_scrubbed' => $this->readScrubbedLogTail($fp, 20),
                    );
                }
            }
        }

        return array(
            'brand_assets' => $brandAssets,
            'cms_pages' => $cmsPages,
            'custom_navigation_links' => $navLinks,
            'mail_template_activity' => $mailActivity,
            'webservice' => $webservice,
            'cron_tasks' => $cronTasks,
            'db_logs' => $dbLogs,
            'disk_log_files' => $logFiles,
            'sanitized_configuration' => $configs,
        );
    }

    /**
     * Read up to the last N lines of a log file safely (reading at most the
     * trailing 16 KB) and scrub all PII/tokens/IPs.
     *
     * @param string $filePath
     * @param int    $maxLines
     * @return array
     */
    protected function readScrubbedLogTail($filePath, $maxLines)
    {
        if (!is_readable($filePath)) {
            return array();
        }
        $size = (int) @filesize($filePath);
        if ($size <= 0) {
            return array();
        }
        $fh = @fopen($filePath, 'rb');
        if (!$fh) {
            return array();
        }
        $readLen = min($size, 16384);
        @fseek($fh, -$readLen, SEEK_END);
        $chunk = @fread($fh, $readLen);
        @fclose($fh);
        if (!is_string($chunk) || $chunk === '') {
            return array();
        }
        $lines = preg_split('/\r\n|\r|\n/', trim($chunk));
        $tail = array_slice($lines, -$maxLines);
        $out = array();
        foreach ($tail as $line) {
            $clean = LvhAuditRedactor::scrubText($line);
            if (strlen($clean) > 300) {
                $clean = substr($clean, 0, 300) . '...';
            }
            $out[] = $clean;
        }
        return $out;
    }

    /* ======================================================================
     * CUSTOM / MODIFIED FILES PACKAGER FOR ZIP BUNDLE
     * ====================================================================== */

    /**
     * Gather safe source copies of:
     *   1. Non-standard third-party/custom modules (e.g. cashpayment)
     *   2. Active override/ files
     *   3. Core/theme/PDF/mail/upstream-module files flagged as modified or added
     *
     * Strictly excludes config/, cache/, log/, backups, and files > 256 KB,
     * and scrubs any hardcoded PHP secret literals.
     *
     * @return array zip_entry_path => file_contents_string
     */
    public function collectCustomFilesForBundle()
    {
        if ($this->lastModulesAudit === null) {
            $this->collectModules();
        }
        if ($this->lastCodeIntegrity === null) {
            $this->collectCodeIntegrity();
        }

        $bundle = array();
        $totalBytes = 0;
        $maxTotalBytes = 5 * 1048576; // 5 MB safety ceiling
        $maxSingleBytes = 262144;     // 256 KB per file
        $allowedExts = array(
            'php' => true, 'tpl' => true, 'css' => true, 'js' => true,
            'xml' => true, 'json' => true, 'sql' => true, 'md' => true,
            'txt' => true, 'html' => true, 'png' => true, 'gif' => true,
            'jpg' => true, 'jpeg' => true, 'svg' => true,
        );

        // 1. Custom / third-party modules (e.g. modules/cashpayment/)
        if (!empty($this->lastModulesAudit['custom_or_third_party_modules'])) {
            foreach ($this->lastModulesAudit['custom_or_third_party_modules'] as $mod) {
                $mName = $mod['name'];
                if ($mName === 'lvhaudit') {
                    continue;
                }
                $mDir = _PS_MODULE_DIR_ . $mName;
                if (!is_dir($mDir)) {
                    continue;
                }
                $it = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($mDir, FilesystemIterator::SKIP_DOTS)
                );
                foreach ($it as $item) {
                    if (!$item->isFile()) {
                        continue;
                    }
                    $size = (int) $item->getSize();
                    if ($size > $maxSingleBytes || ($totalBytes + $size) > $maxTotalBytes) {
                        continue;
                    }
                    $ext = strtolower(pathinfo($item->getFilename(), PATHINFO_EXTENSION));
                    if (!isset($allowedExts[$ext])) {
                        continue;
                    }
                    $abs = str_replace('\\', '/', $item->getPathname());
                    if (strpos($abs, '/.git/') !== false || strpos($abs, '/cache/') !== false) {
                        continue;
                    }
                    $rootNorm = rtrim(str_replace('\\', '/', _PS_ROOT_DIR_), '/');
                    $rel = (strpos($abs, $rootNorm . '/') === 0) ? substr($abs, strlen($rootNorm) + 1) : ('modules/' . $mName . '/' . $item->getFilename());
                    $raw = @file_get_contents($abs);
                    if ($raw === false) {
                        continue;
                    }
                    if ($ext === 'php') {
                        $raw = LvhAuditRedactor::scrubPhpSource($raw);
                    }
                    $bundle['custom-files/' . $rel] = $raw;
                    $totalBytes += strlen($raw);
                }
            }
        }

        // 2. Active override/ files + modified/added upstream files
        $pathsToCopy = array();
        foreach (array('override_files', 'modified_files', 'added_files') as $listKey) {
            if (!empty($this->lastCodeIntegrity[$listKey])) {
                foreach ($this->lastCodeIntegrity[$listKey] as $entry) {
                    $pathsToCopy[$entry['path']] = $listKey;
                }
            }
        }

        foreach ($pathsToCopy as $rel => $reason) {
            // Never touch config/ or hidden files
            if (strpos($rel, 'config/') === 0 || strpos($rel, '.') === 0) {
                continue;
            }
            $abs = _PS_ROOT_DIR_ . '/' . $rel;
            if (!is_file($abs) || !is_readable($abs)) {
                continue;
            }
            $size = (int) @filesize($abs);
            if ($size > $maxSingleBytes || ($totalBytes + $size) > $maxTotalBytes) {
                continue;
            }
            $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
            if (!isset($allowedExts[$ext])) {
                continue;
            }
            $raw = @file_get_contents($abs);
            if ($raw === false) {
                continue;
            }
            if ($ext === 'php') {
                $raw = LvhAuditRedactor::scrubPhpSource($raw);
            }
            $prefixDir = ($reason === 'override_files') ? 'custom-files/' : 'custom-files/modified-or-added/';
            $bundle[$prefixDir . $rel] = $raw;
            $totalBytes += strlen($raw);
        }

        return $bundle;
    }
}
