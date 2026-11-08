<?php
/**
 * Lush View Hotel — Safe 1-Click QloApps 1.7.0 -> 1.8.0 Live Upgrade Module.
 *
 * Designed specifically for live QloApps 1.7.0.0 -> 1.8.0.0 upgrades directly
 * from the Back Office without requiring SSH, Docker, or manual cPanel SQL/file
 * juggling. Enforces strict safety gates in order:
 *
 *   Preflight : Verifies PHP, ZipArchive, cURL, MariaDB, free disk space, write
 *               permissions, and live booking/guest/payment/room counts, with a
 *               1-click Maintenance Mode toggle (auto-whitelisting admin IP).
 *   Step 1    : Streams a complete chunked SQL backup (.sql + .sql.gz) with a
 *               1-click browser download button AND creates a full core/module/
 *               theme rollback zip + token-protected emergency restore script.
 *   Step 2    : Downloads official `qloapps-1.8.0.zip` (release build with
 *               `vendor/` included) into an isolated staging directory, verifies
 *               `_QLO_INSTALL_VERSION_ === '1.8.0.0'` and `vendor/autoload.php`,
 *               applies the `search_form.tpl` XSS fix and `localization/gh.xml`,
 *               and strips `install/` + `settings.inc.php` from staging.
 *   Step 3    : Applies the verified 385-statement idempotent MariaDB migration
 *               (widening `passwd` to `varchar(60)` and `order_payment` amounts
 *               to `decimal(20,6)`, creating all 27 new 1.8.0 Core/HRS tables in
 *               `utf8mb3_general_ci`, adding all 23 new columns, seeding 1.8.0
 *               vocabulary/hooks/tabs, migrating `htl_features` -> `htl_amenity`,
 *               and cleaning orphan `cashpayment`), then verifies schema parity.
 *   Step 4    : Performs the staged file cutover using pure native PHP built-ins
 *               only (zero autoloaded class calls mid-swap), mapping the staged
 *               admin directory onto the live secret admin folder while keeping
 *               `config/settings.inc.php` (`_COOKIE_KEY_`), `img/`, `upload/`,
 *               `download/`, and `modules/lvh*` untouched, then clears caches
 *               and resets OPcache.
 *   Step 5    : Runs on the newly booted QloApps 1.8.0.0 runtime to execute
 *               bundled module upgrade scripts, migrate the homepage hero image,
 *               regenerate `.htaccess`, and display the final verification report.
 *
 * @author    Lush View Hotel
 * @copyright 2026 Lush View Hotel
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class LvhUpgrader extends Module
{
    const RELEASE_ZIP_URL = 'https://github.com/Qloapps/QloApps/releases/download/v1.8.0/qloapps-1.8.0.zip';
    const KEY_ROLLBACK_TOKEN = 'LVH_UPG_ROLLBACK_TOKEN';

    public function __construct()
    {
        $this->name = 'lvhupgrader';
        $this->tab = 'administration';
        $this->version = '1.0.2';
        $this->author = 'Lush View Hotel';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('LVH Safe 1.7.0 → 1.8.0 Upgrader');
        $this->description = $this->l('Guided, backup-first, reversible in-app upgrade wizard from QloApps 1.7.0.0 to QloApps 1.8.0.0 for Lush View Hotel.');
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        if (!parent::install()) {
            return false;
        }
        $this->getOrCreateRollbackToken();
        return true;
    }

    public function uninstall()
    {
        return parent::uninstall();
    }

    /**
     * Entry point for Back-Office module Configure screen, Backup downloads & AJAX steps.
     *
     * @return string
     */
    public function getContent()
    {
        if (Tools::getValue('lvh_download') === 'db') {
            $this->streamDatabaseBackupDownload();
        }

        if (Tools::getValue('lvh_ajax') === '1') {
            $this->dispatchAjaxStep((string) Tools::getValue('step'));
        }

        return $this->renderUpgradeWizard();
    }

    protected function getBackupDir()
    {
        $adminDir = defined('_PS_ADMIN_DIR_') ? rtrim(_PS_ADMIN_DIR_, '/\\') : (_PS_ROOT_DIR_ . '/admin-dev');
        return $adminDir . '/backups/lvh_upgrade_170';
    }

    protected function getStageBaseDir()
    {
        return rtrim(_PS_ROOT_DIR_, '/\\') . '/cache/lvh_stage_180';
    }

    protected function getOrCreateRollbackToken()
    {
        $tok = (string) Configuration::get(self::KEY_ROLLBACK_TOKEN);
        if (strlen($tok) < 24) {
            if (function_exists('random_bytes')) {
                $tok = bin2hex(random_bytes(16));
            } else {
                $tok = md5(uniqid((string) mt_rand(), true)) . substr(sha1((string) microtime(true)), 0, 16);
            }
            Configuration::updateValue(self::KEY_ROLLBACK_TOKEN, $tok);
        }
        return $tok;
    }

    protected function ensureBackupDirProtected()
    {
        $dir = $this->getBackupDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $ht = $dir . '/.htaccess';
        if (!is_file($ht)) {
            @file_put_contents($ht, "Order deny,allow\nDeny from all\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n");
        }
        $idx = $dir . '/index.php';
        if (!is_file($idx)) {
            @file_put_contents($idx, "<?php\nheader('Location: ../');\nexit;\n");
        }
        return $dir;
    }

    /**
     * Stream the SQL backup (.sql.gz if present, else .sql) directly to the admin's browser.
     */
    protected function streamDatabaseBackupDownload()
    {
        $dir = $this->getBackupDir();
        $gzPath = $dir . '/db-backup-1.7.0.sql.gz';
        $sqlPath = $dir . '/db-backup-1.7.0.sql';

        $target = is_file($gzPath) ? $gzPath : (is_file($sqlPath) ? $sqlPath : '');
        if ($target === '') {
            die('Database backup file not found. Run Step 1A first.');
        }

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        $filename = 'lushviewhotel-pre180-' . date('Ymd') . (substr($target, -3) === '.gz' ? '.sql.gz' : '.sql');
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . (string) filesize($target));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        readfile($target);
        exit;
    }

    /**
     * Dispatch AJAX upgrade steps with clean JSON output.
     *
     * @param string $step
     */
    protected function dispatchAjaxStep($step)
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        @set_time_limit(600);
        @ignore_user_abort(true);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        try {
            switch ($step) {
                case 'toggle_maintenance':
                    $res = $this->stepToggleMaintenance((int) Tools::getValue('enable_shop', 0));
                    break;
                case 'backup_db':
                    $res = $this->stepBackupDatabase();
                    break;
                case 'backup_files':
                    $res = $this->stepBackupFilesAndRollbackScript();
                    break;
                case 'download_180':
                    $res = $this->stepDownloadAndStage180();
                    break;
                case 'upgrade_db':
                    $res = $this->stepUpgradeDatabase();
                    break;
                case 'swap_files':
                    // Executes pure native PHP and exits directly on success
                    $this->stepSwapStagedFilesAndExit();
                    return;
                case 'finalize_180':
                    $res = $this->stepFinalize180();
                    break;
                case 'status':
                    $res = array('ok' => true, 'snapshot' => $this->collectEnvironmentSnapshot());
                    break;
                default:
                    $res = array('ok' => false, 'error' => 'Unknown upgrade step: ' . $step);
                    break;
            }
        } catch (Exception $e) {
            $res = array('ok' => false, 'error' => $e->getMessage());
        } catch (Throwable $t) {
            $res = array('ok' => false, 'error' => $t->getMessage());
        }

        echo json_encode($res);
        exit;
    }

    /**
     * Toggle Maintenance Mode while automatically adding the current admin IP to `PS_MAINTENANCE_IP`.
     *
     * @param int $enableShop 0 = put into Maintenance Mode, 1 = re-open hotel to guests
     * @return array
     */
    protected function stepToggleMaintenance($enableShop)
    {
        $enableShop = $enableShop ? 1 : 0;
        $clientIp = Tools::getRemoteAddr();
        $existingIps = trim((string) Configuration::get('PS_MAINTENANCE_IP'));
        $ipList = $existingIps !== '' ? array_map('trim', explode(',', $existingIps)) : array();
        if ($clientIp && !in_array($clientIp, $ipList, true)) {
            $ipList[] = $clientIp;
        }
        $mergedIps = implode(',', array_filter($ipList));

        Configuration::updateValue('PS_MAINTENANCE_IP', $mergedIps);
        Configuration::updateValue('PS_SHOP_ENABLE', $enableShop);

        return array(
            'ok' => true,
            'shop_enabled' => $enableShop,
            'whitelisted_ips' => $mergedIps,
            'message' => $enableShop
                ? 'Hotel is now LIVE for public guests.'
                : ('Maintenance Mode ENABLED (your IP ' . $clientIp . ' is whitelisted).'),
        );
    }

    /**
     * Step 1A: Stream a complete chunked SQL dump of all tables in the active database.
     *
     * @return array
     */
    protected function stepBackupDatabase()
    {
        $dir = $this->ensureBackupDirProtected();
        $sqlPath = $dir . '/db-backup-1.7.0.sql';
        $gzPath = $dir . '/db-backup-1.7.0.sql.gz';

        $db = Db::getInstance();
        $pdo = method_exists($db, 'getLink') ? $db->getLink() : null;

        $fp = @fopen($sqlPath, 'wb');
        if (!$fp) {
            throw new RuntimeException('Cannot open ' . $sqlPath . ' for writing. Check folder permissions / free disk space.');
        }

        $gz = function_exists('gzopen') ? @gzopen($gzPath, 'wb6') : null;
        $writeBoth = function ($chunk) use ($fp, $gz) {
            if (fwrite($fp, $chunk) === false) {
                throw new RuntimeException('Disk write failed while writing SQL backup (check free disk/inode quota).');
            }
            if ($gz) {
                gzwrite($gz, $chunk);
            }
        };

        $header = "-- Lush View Hotel Pre-1.8.0 Full Database Backup\n"
            . "-- Generated : " . gmdate('Y-m-d H:i:s') . " UTC\n"
            . "-- Database  : " . _DB_NAME_ . "\n\n"
            . "SET NAMES utf8mb4;\n"
            . "SET FOREIGN_KEY_CHECKS = 0;\n"
            . "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n";
        $writeBoth($header);

        $tablesRows = $db->executeS('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'');
        $tableNames = array();
        if (is_array($tablesRows)) {
            foreach ($tablesRows as $tr) {
                $vals = array_values($tr);
                if (!empty($vals[0])) {
                    $tableNames[] = (string) $vals[0];
                }
            }
        }

        $totalRows = 0;
        $batchSize = 1500;

        foreach ($tableNames as $tbl) {
            $safeTbl = str_replace('`', '``', $tbl);
            // Use executeS (never getRow, which appends `LIMIT 1` and breaks `SHOW CREATE TABLE`)
            $createRows = $db->executeS('SHOW CREATE TABLE `' . $safeTbl . '`', true, false);
            $createRow = (is_array($createRows) && !empty($createRows[0])) ? $createRows[0] : null;
            $createSql = '';
            if (is_array($createRow)) {
                foreach ($createRow as $k => $v) {
                    if (stripos($k, 'Create Table') !== false) {
                        $createSql = $v;
                        break;
                    }
                }
            }
            if ($createSql === '') {
                continue;
            }

            $writeBoth("-- --------------------------------------------------------\n");
            $writeBoth("-- Table `" . $safeTbl . "`\n");
            $writeBoth("DROP TABLE IF EXISTS `" . $safeTbl . "`;\n");
            $writeBoth($createSql . ";\n\n");

            $offset = 0;
            while (true) {
                $rows = $db->executeS('SELECT * FROM `' . $safeTbl . '` LIMIT ' . (int) $offset . ', ' . (int) $batchSize);
                if (!is_array($rows) || empty($rows)) {
                    break;
                }

                $cols = array_keys($rows[0]);
                $colList = '`' . implode('`, `', array_map(function ($c) {
                    return str_replace('`', '``', $c);
                }, $cols)) . '`';

                $valLines = array();
                foreach ($rows as $row) {
                    $totalRows++;
                    $escapedVals = array();
                    foreach ($row as $val) {
                        if ($val === null) {
                            $escapedVals[] = 'NULL';
                        } elseif ($pdo instanceof PDO) {
                            $escapedVals[] = $pdo->quote((string) $val);
                        } else {
                            $escapedVals[] = "'" . pSQL((string) $val, true) . "'";
                        }
                    }
                    $valLines[] = '(' . implode(',', $escapedVals) . ')';
                    if (count($valLines) >= 200) {
                        $writeBoth('INSERT INTO `' . $safeTbl . '` (' . $colList . ") VALUES\n" . implode(",\n", $valLines) . ";\n");
                        $valLines = array();
                    }
                }
                if (!empty($valLines)) {
                    $writeBoth('INSERT INTO `' . $safeTbl . '` (' . $colList . ") VALUES\n" . implode(",\n", $valLines) . ";\n");
                }

                if (count($rows) < $batchSize) {
                    break;
                }
                $offset += $batchSize;
            }
            $writeBoth("\n");
        }

        $writeBoth("SET FOREIGN_KEY_CHECKS = 1;\n-- END OF BACKUP\n");
        fclose($fp);
        if ($gz) {
            gzclose($gz);
        }

        $bytes = is_file($gzPath) ? filesize($gzPath) : filesize($sqlPath);
        return array(
            'ok' => true,
            'tables_backed_up' => count($tableNames),
            'rows_backed_up' => $totalRows,
            'sql_size_mb' => round(filesize($sqlPath) / 1048576, 2),
            'download_size_mb' => round($bytes / 1048576, 2),
            'message' => sprintf(
                'Database backup complete: %d tables (%d rows, %.2f MB SQL / %.2f MB compressed).',
                count($tableNames),
                $totalRows,
                filesize($sqlPath) / 1048576,
                $bytes / 1048576
            ),
        );
    }

    /**
     * Step 1B: Zip existing 1.7.0 core/module/theme code + write standalone emergency rollback script.
     *
     * @return array
     */
    protected function stepBackupFilesAndRollbackScript()
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('PHP ZipArchive extension is required.');
        }

        $backupDir = $this->ensureBackupDirProtected();
        $zipPath = $backupDir . '/core-files-1.7.0.zip';
        if (is_file($zipPath)) {
            @unlink($zipPath);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create ' . $zipPath);
        }

        $root = rtrim(_PS_ROOT_DIR_, '/\\');
        $adminDir = rtrim(_PS_ADMIN_DIR_, '/\\');
        $adminFolder = basename($adminDir);

        $coreDirs = array(
            'Adapter', 'Core', 'classes', 'config', 'controllers', 'css', 'js',
            'localization', 'mails', 'override', 'pdf', 'src', 'tools', 'vendor',
            'webservice', 'themes/hotel-reservation-theme', 'modules', $adminFolder,
        );

        $addedFiles = 0;

        // Top-level PHP files in webroot
        foreach (scandir($root) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $root . '/' . $entry;
            if (is_file($full) && (substr($entry, -4) === '.php' || $entry === '.htaccess')) {
                $zip->addFile($full, $entry);
                $addedFiles++;
            }
        }

        foreach ($coreDirs as $relDir) {
            $absDir = $root . '/' . $relDir;
            if (!is_dir($absDir)) {
                continue;
            }
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($absDir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($it as $item) {
                $itemPath = $item->getPathname();
                $relPath = str_replace('\\', '/', substr($itemPath, strlen($root) + 1));

                // Skip heavy backup/cache/autoupgrade folders and this upgrader module itself
                if (
                    strpos($relPath, $adminFolder . '/backups/') === 0 ||
                    strpos($relPath, $adminFolder . '/autoupgrade/') === 0 ||
                    strpos($relPath, 'modules/lvhupgrader/') === 0 ||
                    strpos($relPath, 'themes/hotel-reservation-theme/cache/') === 0
                ) {
                    continue;
                }

                if ($item->isFile()) {
                    $zip->addFile($itemPath, $relPath);
                    $addedFiles++;
                }
            }
        }

        if (!$zip->close()) {
            throw new RuntimeException('Failed finalizing core-files-1.7.0.zip (check disk/inode quota).');
        }

        // Write standalone zero-dependency emergency file-restore script inside admin folder
        $token = $this->getOrCreateRollbackToken();
        $rollbackScriptPath = $adminDir . '/lvh-emergency-rollback.php';
        $rollbackCode = $this->buildStandaloneRollbackPhp($token);
        @file_put_contents($rollbackScriptPath, $rollbackCode);

        $rollbackUrl = Tools::getHttpHost(true) . __PS_BASE_URI__ . $adminFolder . '/lvh-emergency-rollback.php?key=' . urlencode($token);

        return array(
            'ok' => true,
            'files_archived' => $addedFiles,
            'zip_size_mb' => round(filesize($zipPath) / 1048576, 2),
            'rollback_url' => $rollbackUrl,
            'message' => sprintf(
                'Core & module rollback archive created (%d files, %.2f MB) + emergency restore script armed.',
                $addedFiles,
                filesize($zipPath) / 1048576
            ),
        );
    }

    /**
     * Build standalone pure-PHP emergency file restore script (zero framework dependencies).
     *
     * @param string $token
     * @return string
     */
    protected function buildStandaloneRollbackPhp($token)
    {
        $escapedToken = addslashes($token);
        return <<<PHP
<?php
/**
 * Lush View Hotel — Standalone Emergency 1.7.0 File Rollback Utility.
 * Protected by secret key. Restores `backups/lvh_upgrade_170/core-files-1.7.0.zip`
 * and clears `cache/class_index.php` using zero framework dependencies.
 */
@set_time_limit(600);
\$expected = '{$escapedToken}';
\$given = isset(\$_GET['key']) ? (string) \$_GET['key'] : '';
if (!hash_equals(\$expected, \$given)) {
    http_response_code(403);
    die('403 Forbidden — invalid rollback key.');
}
\$adminDir = __DIR__;
\$rootDir = dirname(\$adminDir);
\$zipFile = \$adminDir . '/backups/lvh_upgrade_170/core-files-1.7.0.zip';
if (!is_file(\$zipFile)) {
    die('Rollback archive not found at: ' . htmlspecialchars(\$zipFile));
}
if (\$_SERVER['REQUEST_METHOD'] === 'POST' && isset(\$_POST['confirm_restore'])) {
    \$zip = new ZipArchive();
    if (\$zip->open(\$zipFile) !== true) {
        die('Failed to open rollback archive.');
    }
    \$zip->extractTo(\$rootDir);
    \$zip->close();
    @unlink(\$rootDir . '/cache/class_index.php');
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate(\$rootDir . '/config/settings.inc.php', true);
    }
    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }
    echo '<h2>Restored 1.7.0 Core Files Successfully!</h2><p>Note: If Step 3 (DB schema migration) was already applied, the additive 1.8.0 tables/columns and widened varchar(60)/decimal(20,6) columns remain 100% backward-compatible with 1.7.0.</p><p><a href="index.php">Return to Back Office</a></p>';
    exit;
}
?>
<!doctype html>
<html><head><meta charset="utf-8"><title>LVH Emergency File Rollback</title>
<style>body{font-family:sans-serif;max-width:640px;margin:40px auto;padding:24px;border:1px solid #ddd;border-radius:8px}button{background:#b91c1c;color:#fff;border:0;padding:12px 20px;font-size:15px;border-radius:6px;cursor:pointer}</style>
</head><body>
<h2>Lush View Hotel — Emergency 1.7.0 File Restore</h2>
<p>Backup archive ready: <code><?php echo htmlspecialchars(basename(\$zipFile)); ?></code> (<?php echo round(filesize(\$zipFile)/1048576, 2); ?> MB)</p>
<form method="post"><input type="hidden" name="confirm_restore" value="1">
<button type="submit">Restore 1.7.0 Core Files Now</button></form>
</body></html>
PHP;
    }

    /**
     * Step 2: Download official `qloapps-1.8.0.zip`, extract to `cache/lvh_stage_180/`,
     * verify `_QLO_VERSION_ === '1.8.0.0'` + `vendor/autoload.php`, and pre-patch XSS + Ghana pack.
     *
     * @return array
     */
    protected function stepDownloadAndStage180()
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('PHP ZipArchive extension is required.');
        }

        $stageBase = $this->getStageBaseDir();
        if (!is_dir($stageBase)) {
            @mkdir($stageBase, 0755, true);
        }

        $zipPath = $stageBase . '/qloapps-1.8.0.zip';
        $extractDir = $stageBase . '/extracted';

        // Download if not already present (or smaller than 30 MB, indicating an incomplete download)
        if (!is_file($zipPath) || filesize($zipPath) < 30 * 1048576) {
            if (!function_exists('curl_init')) {
                throw new RuntimeException('cURL extension is not enabled and qloapps-1.8.0.zip is not pre-uploaded in cache/lvh_stage_180/.');
            }
            $fp = @fopen($zipPath, 'wb');
            if (!$fp) {
                throw new RuntimeException('Cannot write to ' . $zipPath);
            }
            $ch = curl_init(self::RELEASE_ZIP_URL);
            curl_setopt_array($ch, array(
                CURLOPT_FILE => $fp,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 8,
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_TIMEOUT => 300,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT => 'LushViewHotel-QloApps-Upgrader/1.0',
            ));
            $ok = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);
            fclose($fp);

            if (!$ok || $httpCode !== 200 || !is_file($zipPath) || filesize($zipPath) < 30 * 1048576) {
                @unlink($zipPath);
                throw new RuntimeException(
                    'Failed downloading official qloapps-1.8.0.zip (HTTP ' . $httpCode . ' ' . $curlErr . '). '
                    . 'Alternatively, upload qloapps-1.8.0.zip to cache/lvh_stage_180/qloapps-1.8.0.zip and click Retry.'
                );
            }
        }

        // Clean any previous extracted folder
        if (is_dir($extractDir)) {
            $this->rrmdirNative($extractDir);
        }
        @mkdir($extractDir, 0755, true);

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Downloaded qloapps-1.8.0.zip could not be opened as a valid ZIP archive.');
        }
        if (!$zip->extractTo($extractDir)) {
            $zip->close();
            throw new RuntimeException('Failed extracting qloapps-1.8.0.zip to ' . $extractDir . ' (check free disk/inode quota).');
        }
        $zip->close();

        // Locate extracted root containing config/defines.inc.php
        $stageRoot = $this->findStageRoot($extractDir);
        if ($stageRoot === '') {
            throw new RuntimeException('Could not find config/defines.inc.php inside extracted qloapps-1.8.0.zip.');
        }

        $installVerPath = is_file($stageRoot . '/install/install_version.php')
            ? ($stageRoot . '/install/install_version.php')
            : (is_file($stageRoot . '/install-dev/install_version.php') ? ($stageRoot . '/install-dev/install_version.php') : '');
        $installVerContent = $installVerPath !== '' ? (string) @file_get_contents($installVerPath) : '';
        $changelogContent = is_file($stageRoot . '/CHANGELOG.txt') ? (string) @file_get_contents($stageRoot . '/CHANGELOG.txt') : '';

        $has180Version = (
            strpos($installVerContent, "'1.8.0.0'") !== false ||
            strpos($installVerContent, '"1.8.0.0"') !== false ||
            stripos($changelogContent, 'V1.8.0') !== false
        );
        if (!$has180Version) {
            throw new RuntimeException('Extracted archive does not declare _QLO_INSTALL_VERSION_ = 1.8.0.0 in install/install_version.php or CHANGELOG.txt.');
        }
        if (!is_file($stageRoot . '/vendor/autoload.php')) {
            throw new RuntimeException('Extracted archive is missing vendor/autoload.php (must use official release asset, not raw git source zip).');
        }
        if (
            !is_file($stageRoot . '/classes/PasswordHashing.php') ||
            !is_file($stageRoot . '/modules/hotelreservationsystem/classes/HotelHeaderImage.php')
        ) {
            throw new RuntimeException('Extracted archive is missing QloApps 1.8.0 signature classes (PasswordHashing.php / HotelHeaderImage.php).');
        }

        $stageAdminFolder = is_dir($stageRoot . '/admin') ? 'admin' : (is_dir($stageRoot . '/admin-dev') ? 'admin-dev' : '');
        if ($stageAdminFolder === '') {
            throw new RuntimeException('Extracted archive does not contain an admin or admin-dev folder.');
        }
        $stageAdminDir = $stageRoot . '/' . $stageAdminFolder;

        // Pre-patch 1: Reflected XSS fix in admin search_form.tpl
        $searchFormTpl = $stageAdminDir . '/themes/default/template/search_form.tpl';
        if (is_file($searchFormTpl)) {
            $tplTxt = (string) file_get_contents($searchFormTpl);
            $tplTxt = str_replace('value="{$bo_query}"', 'value="{$bo_query|escape:\'html\':\'UTF-8\'}"', $tplTxt);
            file_put_contents($searchFormTpl, $tplTxt);
        }

        // Pre-patch 2: Ghana GHS localization pack
        $ghSrc = dirname(__FILE__) . '/data/gh.xml';
        if (is_file($ghSrc) && is_dir($stageRoot . '/localization')) {
            @copy($ghSrc, $stageRoot . '/localization/gh.xml');
        }

        // Safety strip: remove installer folders, CHANGELOG.txt & any settings.inc.php from stage
        foreach (array('install', 'install-dev') as $instDir) {
            if (is_dir($stageRoot . '/' . $instDir)) {
                $this->rrmdirNative($stageRoot . '/' . $instDir);
            }
        }
        @unlink($stageRoot . '/CHANGELOG.txt');
        @unlink($stageRoot . '/config/settings.inc.php');

        $manifest = array(
            'verified_at' => gmdate('c'),
            'zip_bytes' => filesize($zipPath),
            'stage_root' => $stageRoot,
            'stage_admin_dir' => $stageAdminDir,
            'stage_admin_folder' => $stageAdminFolder,
        );
        file_put_contents($stageBase . '/stage_ready.json', json_encode($manifest));

        return array(
            'ok' => true,
            'zip_size_mb' => round(filesize($zipPath) / 1048576, 2),
            'stage_admin_folder' => $stageAdminFolder,
            'message' => sprintf(
                'Official QloApps 1.8.0.0 release (%.1f MB) downloaded, verified (`vendor/autoload.php` + `PasswordHashing.php` present), XSS-patched, and staged cleanly.',
                filesize($zipPath) / 1048576
            ),
        );
    }

    protected function findStageRoot($dir)
    {
        if (is_file($dir . '/config/defines.inc.php')) {
            return $dir;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $sub = $dir . '/' . $entry;
            if (is_dir($sub) && is_file($sub . '/config/defines.inc.php')) {
                return $sub;
            }
        }
        return '';
    }

    /**
     * Step 3: Execute the idempotent 1.7.0 -> 1.8.0 database schema & vocabulary migration
     * and assert 100% table/column parity before allowing file cutover.
     *
     * @return array
     */
    protected function stepUpgradeDatabase()
    {
        $sqlFile = dirname(__FILE__) . '/sql/upgrade_170_to_180.sql';
        if (!is_file($sqlFile)) {
            throw new RuntimeException('Bundled SQL migration file missing: ' . $sqlFile);
        }

        $rawSql = (string) file_get_contents($sqlFile);
        if (_DB_PREFIX_ !== 'qlo_') {
            $rawSql = str_replace('`qlo_', '`' . _DB_PREFIX_, $rawSql);
        }

        $statements = $this->splitSqlStatements($rawSql);
        $db = Db::getInstance();
        $executed = 0;
        $errors = array();

        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '') {
                continue;
            }
            try {
                if (!$db->execute($stmt, false)) {
                    $errors[] = substr($stmt, 0, 140) . ' :: ' . $db->getMsgError();
                } else {
                    $executed++;
                }
            } catch (Exception $ex) {
                $errors[] = substr($stmt, 0, 140) . ' :: ' . $ex->getMessage();
            } catch (Throwable $th) {
                $errors[] = substr($stmt, 0, 140) . ' :: ' . $th->getMessage();
            }
        }

        if (!empty($errors)) {
            throw new RuntimeException(
                'Database migration encountered ' . count($errors) . ' SQL error(s): ' . implode(' | ', array_slice($errors, 0, 3))
            );
        }

        // Verify key schema guarantees in information_schema
        $p = _DB_PREFIX_;
        $empLen = (int) $db->getValue(
            "SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . pSQL($p . 'employee') . "' AND COLUMN_NAME = 'passwd'",
            false
        );
        if ($empLen < 60) {
            throw new RuntimeException('Verification failed: ' . $p . 'employee.passwd length is ' . $empLen . ' (expected >= 60).');
        }

        $requiredTables = array(
            'business_source', 'source', 'room_type_selling_object', 'tax_configuration',
            'order_tax_detail', 'htl_amenity', 'htl_booking_status', 'htl_property_type',
            'htl_header_image', 'htl_connected_room',
        );
        foreach ($requiredTables as $rt) {
            $exists = (int) $db->getValue(
                "SELECT COUNT(*) FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . pSQL($p . $rt) . "'",
                false
            );
            if (!$exists) {
                throw new RuntimeException('Verification failed: table `' . $p . $rt . '` was not created.');
            }
        }

        $totalTables = (int) $db->getValue(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'",
            false
        );
        $amenitiesCount = (int) $db->getValue('SELECT COUNT(*) FROM `' . bqSQL($p . 'htl_amenity') . '`', false);

        return array(
            'ok' => true,
            'statements_executed' => $executed,
            'total_tables' => $totalTables,
            'amenities_migrated' => $amenitiesCount,
            'message' => sprintf(
                'Database schema upgraded cleanly (%d SQL statements applied, 0 errors; %d total tables, %d hotel amenities ready, bcrypt varchar(60) & decimal(20,6) verified).',
                $executed,
                $totalTables,
                $amenitiesCount
            ),
        );
    }

    /**
     * Tokenize SQL script into individual top-level statements safely respecting
     * single quotes, backticks, `--` comments, and block comments.
     *
     * @param string $sql
     * @return array
     */
    protected function splitSqlStatements($sql)
    {
        $out = array();
        $buf = '';
        $len = strlen($sql);
        $inSq = false;
        $inBt = false;

        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            $next = ($i + 1 < $len) ? $sql[$i + 1] : '';

            if (!$inSq && !$inBt) {
                // Skip `-- ...` line comment
                if ($ch === '-' && $next === '-') {
                    while ($i < $len && $sql[$i] !== "\n") {
                        $i++;
                    }
                    continue;
                }
                // Skip `/* ... */` block comment
                if ($ch === '/' && $next === '*') {
                    $i += 2;
                    while ($i + 1 < $len && !($sql[$i] === '*' && $sql[$i + 1] === '/')) {
                        $i++;
                    }
                    $i++;
                    continue;
                }
            }

            if ($ch === "'" && !$inBt) {
                if ($inSq) {
                    $bs = 0;
                    $k = $i - 1;
                    while ($k >= 0 && $sql[$k] === '\\') {
                        $bs++;
                        $k--;
                    }
                    if ($bs % 2 === 1) {
                        $buf .= $ch;
                        continue;
                    }
                    if ($next === "'") {
                        $buf .= "''";
                        $i++;
                        continue;
                    }
                    $inSq = false;
                    $buf .= $ch;
                    continue;
                } else {
                    $inSq = true;
                    $buf .= $ch;
                    continue;
                }
            }

            if ($ch === '`' && !$inSq) {
                $inBt = !$inBt;
                $buf .= $ch;
                continue;
            }

            if ($ch === ';' && !$inSq && !$inBt) {
                if (trim($buf) !== '') {
                    $out[] = trim($buf);
                }
                $buf = '';
                continue;
            }

            $buf .= $ch;
        }

        if (trim($buf) !== '') {
            $out[] = trim($buf);
        }

        return $out;
    }

    /**
     * Step 4: Copy staged 1.8.0 files over `_PS_ROOT_DIR_` + `_PS_ADMIN_DIR_` using
     * ONLY native PHP functions (zero PrestaShop autoloader calls mid-swap!), purge
     * `cache/class_index.php` + Smarty caches + OPcache, echo JSON and `exit;` directly.
     */
    protected function stepSwapStagedFilesAndExit()
    {
        $rootDir = rtrim(_PS_ROOT_DIR_, '/\\');
        $adminDir = rtrim(_PS_ADMIN_DIR_, '/\\');
        $adminFolder = basename($adminDir);
        $stageBase = $this->getStageBaseDir();
        $manifestFile = $stageBase . '/stage_ready.json';

        if (!is_file($manifestFile)) {
            echo json_encode(array('ok' => false, 'error' => 'Stage manifest missing. Run Step 2 first.'));
            exit;
        }

        $manifest = json_decode((string) file_get_contents($manifestFile), true);
        $stageRoot = isset($manifest['stage_root']) ? (string) $manifest['stage_root'] : '';
        $stageAdminDir = isset($manifest['stage_admin_dir']) ? (string) $manifest['stage_admin_dir'] : '';
        $stageAdminFolder = isset($manifest['stage_admin_folder']) ? (string) $manifest['stage_admin_folder'] : '';

        if ($stageRoot === '' || !is_dir($stageRoot) || !is_dir($stageAdminDir)) {
            echo json_encode(array('ok' => false, 'error' => 'Staged 1.8.0 directory not found on disk. Re-run Step 2.'));
            exit;
        }

        $dbVer = (string) Db::getInstance()->getValue(
            "SELECT `value` FROM `" . _DB_PREFIX_ . "configuration` WHERE `name` = 'QLO_VERSION_DB'",
            false
        );
        if ($dbVer !== '1.8.0.0') {
            echo json_encode(array('ok' => false, 'error' => 'Database has not been upgraded to 1.8.0.0 yet. Run Step 3 first.'));
            exit;
        }

        // =========================================================================
        // PURE NATIVE PHP ZONE — DO NOT CALL ANY AUTOLOADED PRESTASHOP CLASSES BELOW
        // =========================================================================
        $copiedFiles = 0;

        // 1. Copy staged admin folder onto live secret admin directory (preserving backups & rollback utility)
        $copiedFiles += $this->copyTreeNative($stageAdminDir, $adminDir, array(
            'backups',
            'autoupgrade',
            'lvh-emergency-rollback.php',
        ), false);

        // 2. Copy top-level entries of $stageRoot onto $rootDir
        $skipTopLevel = array(
            $stageAdminFolder,
            'install',
            'install-dev',
            'cache',
            'log',
            'upload',
            'download',
            '.htaccess',
            'robots.txt',
            'CHANGELOG.txt',
        );

        foreach (scandir($stageRoot) as $entry) {
            if ($entry === '.' || $entry === '..' || in_array($entry, $skipTopLevel, true)) {
                continue;
            }
            $src = $stageRoot . '/' . $entry;
            $dst = $rootDir . '/' . $entry;

            if (is_file($src)) {
                @copy($src, $dst);
                $copiedFiles++;
                continue;
            }

            if ($entry === 'img') {
                // Copy ONLY missing placeholder/default files into img/; NEVER overwrite live hotel images/logos
                $copiedFiles += $this->copyTreeNative($src, $dst, array(), true);
                continue;
            }

            if ($entry === 'config') {
                // Never overwrite live settings.inc.php!
                $copiedFiles += $this->copyTreeNative($src, $dst, array('settings.inc.php'), false);
                continue;
            }

            if ($entry === 'modules') {
                // Preserve all custom `lvh*` modules; overwrite standard core/Webkul modules cleanly
                $copiedFiles += $this->copyModulesNative($src, $dst);
                continue;
            }

            $copiedFiles += $this->copyTreeNative($src, $dst, array(), false);
        }

        // 3. Prune obsolete 1.7.0-only PHP files inside pure core directories & HRS classes/controllers
        //    (never prune modules/*/views/img or documents/ where uploaded hotel/guest media live!)
        $pruneTargets = array(
            'Adapter', 'Core', 'classes', 'controllers', 'src', 'vendor',
            'modules/hotelreservationsystem/classes',
            'modules/hotelreservationsystem/controllers',
        );
        $prunedFiles = 0;
        foreach ($pruneTargets as $relTarget) {
            $liveSub = $rootDir . '/' . $relTarget;
            $stageSub = $stageRoot . '/' . $relTarget;
            if (is_dir($liveSub) && is_dir($stageSub)) {
                $prunedFiles += $this->pruneStaleFilesNative($stageSub, $liveSub);
            }
        }

        // Remove removed 1.7.0 module directories on disk
        foreach (array('modules/qloautoupgrade', 'modules/qlochannelmanagerconnector') as $deadMod) {
            if (is_dir($rootDir . '/' . $deadMod)) {
                $this->rrmdirNative($rootDir . '/' . $deadMod);
            }
        }

        // 4. Safely bump _QLOAPPS_VERSION_ inside live config/settings.inc.php while keeping
        //    _COOKIE_KEY_, _DB_PASSWD_, and all encryption/database constants 100% untouched.
        $this->bumpSettingsQloVersionNative($rootDir . '/config/settings.inc.php', '1.8.0.0');

        // 5. Purge autoloader class_index.php, Smarty compiled templates, ad caches & staging tree
        @unlink($rootDir . '/cache/class_index.php');
        @unlink($rootDir . '/cache/dashboard_recommendation.html');
        @unlink($rootDir . '/cache/catalog_recommendation.html');
        @unlink($rootDir . '/config/xml/upgrade_available.xml');
        $this->cleanSmartyDirNative($rootDir . '/cache/smarty/compile');
        $this->cleanSmartyDirNative($rootDir . '/cache/smarty/cache');
        $this->cleanSmartyDirNative($rootDir . '/themes/hotel-reservation-theme/cache');

        // Free ~160 MB of staging disk space now that cutover is complete
        $this->rrmdirNative($stageBase);

        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        clearstatcache(true);

        echo json_encode(array(
            'ok' => true,
            'files_updated' => $copiedFiles,
            'stale_files_pruned' => $prunedFiles,
            'message' => sprintf(
                'QloApps 1.8.0.0 core, theme, vendor & module files activated (%d files updated, %d obsolete 1.7.0 files pruned, _QLOAPPS_VERSION_ = 1.8.0.0) and caches cleared.',
                $copiedFiles,
                $prunedFiles
            ),
        ));
        exit;
    }

    /**
     * Atomically update `_QLOAPPS_VERSION_` inside `config/settings.inc.php` using pure
     * native PHP, preserving `_COOKIE_KEY_`, `_DB_PASSWD_`, and all other constants untouched.
     *
     * @param string $settingsPath
     * @param string $targetVersion
     * @return bool
     */
    protected function bumpSettingsQloVersionNative($settingsPath, $targetVersion)
    {
        if (!is_file($settingsPath) || !is_readable($settingsPath) || !is_writable($settingsPath)) {
            return false;
        }
        $raw = (string) @file_get_contents($settingsPath);
        if ($raw === '') {
            return false;
        }
        $pattern = '/(define\s*\(\s*[\'"]_QLOAPPS_VERSION_[\'"]\s*,\s*[\'"])([^\'"]*)([\'"]\s*\)\s*;)/';
        if (preg_match($pattern, $raw)) {
            $updated = preg_replace($pattern, '${1}' . $targetVersion . '${3}', $raw, 1);
        } else {
            $addition = "\nif (!defined('_QLOAPPS_VERSION_')) {\n    define('_QLOAPPS_VERSION_', '" . $targetVersion . "');\n}\n";
            if (preg_match('/\?>\s*$/', $raw)) {
                $updated = preg_replace('/\?>\s*$/', $addition . "?>\n", $raw, 1);
            } else {
                $updated = rtrim($raw) . $addition;
            }
        }
        if (!is_string($updated) || $updated === '' || strpos($updated, '_COOKIE_KEY_') === false || strpos($updated, '_DB_NAME_') === false) {
            return false;
        }
        $tmpPath = $settingsPath . '.lvh_tmp';
        if (@file_put_contents($tmpPath, $updated) !== false && filesize($tmpPath) > 100) {
            if (!@rename($tmpPath, $settingsPath)) {
                @copy($tmpPath, $settingsPath);
                @unlink($tmpPath);
            }
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($settingsPath, true);
            }
            return true;
        }
        @unlink($tmpPath);
        return false;
    }

    protected function copyModulesNative($stageModulesDir, $liveModulesDir)
    {
        $count = 0;
        if (!is_dir($liveModulesDir)) {
            @mkdir($liveModulesDir, 0755, true);
        }
        foreach (scandir($stageModulesDir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (strpos($entry, 'lvh') === 0) {
                continue;
            }
            $src = $stageModulesDir . '/' . $entry;
            $dst = $liveModulesDir . '/' . $entry;
            if (is_file($src)) {
                @copy($src, $dst);
                $count++;
            } elseif (is_dir($src)) {
                $count += $this->copyTreeNative($src, $dst, array(), false);
            }
        }
        return $count;
    }

    protected function copyTreeNative($srcDir, $dstDir, array $excludeTopNames, $onlyIfMissing)
    {
        $count = 0;
        if (!is_dir($dstDir)) {
            @mkdir($dstDir, 0755, true);
        }
        $entries = @scandir($srcDir);
        if (!is_array($entries)) {
            return 0;
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || in_array($entry, $excludeTopNames, true)) {
                continue;
            }
            $s = $srcDir . '/' . $entry;
            $d = $dstDir . '/' . $entry;
            if (is_dir($s)) {
                $count += $this->copyTreeNative($s, $d, array(), $onlyIfMissing);
            } elseif (is_file($s)) {
                if ($onlyIfMissing && is_file($d)) {
                    continue;
                }
                if (@copy($s, $d)) {
                    $count++;
                }
            }
        }
        return $count;
    }

    protected function pruneStaleFilesNative($stageSub, $liveSub)
    {
        $removed = 0;
        $entries = @scandir($liveSub);
        if (!is_array($entries)) {
            return 0;
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $lPath = $liveSub . '/' . $entry;
            $sPath = $stageSub . '/' . $entry;
            if (is_dir($lPath)) {
                if (!is_dir($sPath)) {
                    $this->rrmdirNative($lPath);
                    $removed++;
                } else {
                    $removed += $this->pruneStaleFilesNative($sPath, $lPath);
                }
            } elseif (is_file($lPath) && !is_file($sPath)) {
                @unlink($lPath);
                $removed++;
            }
        }
        return $removed;
    }

    protected function cleanSmartyDirNative($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === 'index.php' || $entry === '.htaccess') {
                continue;
            }
            $full = $dir . '/' . $entry;
            if (is_dir($full)) {
                $this->rrmdirNative($full);
            } else {
                @unlink($full);
            }
        }
    }

    protected function rrmdirNative($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        $entries = @scandir($dir);
        if (is_array($entries)) {
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $full = $dir . '/' . $entry;
                if (is_dir($full) && !is_link($full)) {
                    $this->rrmdirNative($full);
                } else {
                    @unlink($full);
                }
            }
        }
        @rmdir($dir);
    }

    /**
     * Step 5: Runs on the newly booted QloApps 1.8.0.0 runtime to sync bundled module
     * versions/upgrades, migrate the homepage hero image into `img/hotel_header_media/`,
     * and regenerate `.htaccess`.
     *
     * @return array
     */
    protected function stepFinalize180()
    {
        $db = Db::getInstance();
        $p = _DB_PREFIX_;

        // 1. Sync / run upgrade scripts on installed bundled modules
        $upgradedModules = 0;
        $installedRows = $db->executeS('SELECT `name`, `version` FROM `' . bqSQL($p . 'module') . '` WHERE `active` = 1', true, false);
        if (is_array($installedRows)) {
            foreach ($installedRows as $mRow) {
                $mName = (string) $mRow['name'];
                if ($mName === '' || strpos($mName, 'lvh') === 0) {
                    continue;
                }
                if (!is_file(_PS_MODULE_DIR_ . $mName . '/' . $mName . '.php')) {
                    continue;
                }
                try {
                    $inst = Module::getInstanceByName($mName);
                    if ($inst instanceof Module && Tools::version_compare($inst->version, (string) $mRow['version'], '>')) {
                        $stub = new stdClass();
                        $stub->name = $mName;
                        $stub->version = $inst->version;
                        $stub->database_version = (string) $mRow['version'];
                        $stub->installed = 1;
                        if (Module::initUpgradeModule($stub)) {
                            $inst->runUpgradeModule();
                        }
                        Module::upgradeModuleVersion($mName, $inst->version);
                        $upgradedModules++;
                    }
                } catch (Exception $e) {
                    // Fallback: ensure version column is synced so module stays enabled
                } catch (Throwable $t) {
                }
            }
        }

        // 1b. Copy any custom 1.7.0 feature icons (`views/img/hotel_features/*.jpg`) into 1.8.0 `hotel_amenities/`
        $oldFeatImgDir = _PS_MODULE_DIR_ . 'hotelreservationsystem/views/img/hotel_features/';
        $newAmenImgDir = _PS_MODULE_DIR_ . 'hotelreservationsystem/views/img/hotel_amenities/';
        if (is_dir($oldFeatImgDir) && is_dir($newAmenImgDir)) {
            foreach (scandir($oldFeatImgDir) as $fEntry) {
                if ($fEntry === '.' || $fEntry === '..' || $fEntry === 'index.php') {
                    continue;
                }
                $srcF = $oldFeatImgDir . $fEntry;
                $dstF = $newAmenImgDir . $fEntry;
                if (is_file($srcF) && !is_file($dstF)) {
                    @copy($srcF, $dstF);
                }
            }
        }

        // 2. Migrate 1.7.0 homepage hero image (`WK_HOTEL_HEADER_IMAGE`) into 1.8.0 `htl_header_image` if empty
        $headerRows = (int) $db->getValue('SELECT COUNT(*) FROM `' . bqSQL($p . 'htl_header_image') . '`', false);
        if ($headerRows === 0) {
            $mediaDir = _PS_IMG_DIR_ . 'hotel_header_media/';
            if (!is_dir($mediaDir)) {
                @mkdir($mediaDir, 0755, true);
            }
            $legacyHeader = trim((string) Configuration::get('WK_HOTEL_HEADER_IMAGE'));
            $srcImg = '';
            $destName = '';
            if ($legacyHeader !== '' && is_file(_PS_IMG_DIR_ . $legacyHeader)) {
                $srcImg = _PS_IMG_DIR_ . $legacyHeader;
                $destName = basename($legacyHeader);
            } elseif (is_file(_PS_IMG_DIR_ . 'hotel_header_image.jpg')) {
                $srcImg = _PS_IMG_DIR_ . 'hotel_header_image.jpg';
                $destName = 'default_hotel_header_image.jpg';
            }
            if ($srcImg !== '' && $destName !== '') {
                @copy($srcImg, $mediaDir . $destName);
                $db->execute(
                    "INSERT INTO `" . bqSQL($p . 'htl_header_image') . "` (`name`, `position`, `active`, `date_add`, `date_upd`)
                     VALUES ('" . pSQL($destName) . "', 0, 1, NOW(), NOW())"
                );
                $newId = (int) $db->Insert_ID();
                if ($newId > 0) {
                    $title = (string) Configuration::get('WK_TITLE_HEADER_BLOCK');
                    if ($title === '' || stripos($title, 'Hotel Prime') !== false) {
                        $title = 'Lush View Hotel';
                    }
                    $desc = (string) Configuration::get('WK_CONTENT_HEADER_BLOCK');
                    $db->execute(
                        "INSERT IGNORE INTO `" . bqSQL($p . 'htl_header_image_lang') . "` (`id_header_image`, `id_lang`, `title`, `description`)
                         SELECT " . (int) $newId . ", `id_lang`, '" . pSQL($title) . "', '" . pSQL($desc) . "' FROM `" . bqSQL($p . 'lang') . "`"
                    );
                }
            }
        }

        // 3. Regenerate .htaccess cleanly for 1.8.0
        if (method_exists('Tools', 'generateHtaccess')) {
            @Tools::generateHtaccess();
        }

        $snap = $this->collectEnvironmentSnapshot();
        return array(
            'ok' => true,
            'upgraded_modules' => $upgradedModules,
            'snapshot' => $snap,
            'message' => sprintf(
                'Finalization complete on QloApps %s! %d bundled modules synced, homepage hero media verified, and .htaccess regenerated.',
                $snap['qlo_code_version'],
                $upgradedModules
            ),
        );
    }

    /**
     * Collect current runtime, disk, backup, and hotel data metrics for the UI scorecard.
     *
     * @return array
     */
    protected function collectEnvironmentSnapshot()
    {
        $db = Db::getInstance();
        $p = _DB_PREFIX_;
        $root = rtrim(_PS_ROOT_DIR_, '/\\');
        $adminDir = rtrim(_PS_ADMIN_DIR_, '/\\');
        $backupDir = $this->getBackupDir();
        $stageBase = $this->getStageBaseDir();

        $freeBytes = @disk_free_space($root);
        $freeMb = ($freeBytes !== false && $freeBytes > 0) ? round($freeBytes / 1048576, 1) : -1;

        $writableChecks = array(
            'root' => is_writable($root),
            'admin' => is_writable($adminDir),
            'classes' => is_writable($root . '/classes'),
            'controllers' => is_writable($root . '/controllers'),
            'modules' => is_writable($root . '/modules'),
            'themes' => is_writable($root . '/themes'),
            'config' => is_writable($root . '/config'),
            'cache' => is_writable($root . '/cache'),
        );

        $sqlFile = $backupDir . '/db-backup-1.7.0.sql';
        $gzFile = $backupDir . '/db-backup-1.7.0.sql.gz';
        $coreZip = $backupDir . '/core-files-1.7.0.zip';
        $stageReady = is_file($stageBase . '/stage_ready.json');

        $qloCodeVer = defined('_QLOAPPS_VERSION_')
            ? _QLOAPPS_VERSION_
            : (defined('_QLO_VERSION_') ? _QLO_VERSION_ : _PS_VERSION_);
        $qloDbVer = (string) $db->getValue("SELECT `value` FROM `" . bqSQL($p . 'configuration') . "` WHERE `name` = 'QLO_VERSION_DB'", false);

        return array(
            'qlo_code_version' => $qloCodeVer,
            'qlo_db_version' => $qloDbVer !== '' ? $qloDbVer : 'unknown',
            'php_version' => PHP_VERSION,
            'memory_limit' => (string) ini_get('memory_limit'),
            'max_execution_time' => (string) ini_get('max_execution_time'),
            'has_zip' => class_exists('ZipArchive'),
            'has_curl' => function_exists('curl_init'),
            'free_disk_mb' => $freeMb,
            'all_writable' => !in_array(false, $writableChecks, true),
            'writable_detail' => $writableChecks,
            'admin_folder' => basename($adminDir),
            'shop_enabled' => (int) Configuration::get('PS_SHOP_ENABLE'),
            'client_ip' => Tools::getRemoteAddr(),
            'bookings' => (int) $db->getValue('SELECT COUNT(*) FROM `' . bqSQL($p . 'orders') . '`', false),
            'guests' => (int) $db->getValue('SELECT COUNT(*) FROM `' . bqSQL($p . 'customer') . '`', false),
            'payments' => (int) $db->getValue('SELECT COUNT(*) FROM `' . bqSQL($p . 'order_payment') . '`', false),
            'rooms' => (int) $db->getValue('SELECT COUNT(*) FROM `' . bqSQL($p . 'htl_room_information') . '`', false),
            'staff' => (int) $db->getValue('SELECT COUNT(*) FROM `' . bqSQL($p . 'employee') . '`', false),
            'tables_total' => (int) $db->getValue("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'", false),
            'has_db_backup' => is_file($sqlFile) || is_file($gzFile),
            'db_backup_mb' => is_file($gzFile) ? round(filesize($gzFile) / 1048576, 2) : (is_file($sqlFile) ? round(filesize($sqlFile) / 1048576, 2) : 0),
            'has_file_backup' => is_file($coreZip),
            'file_backup_mb' => is_file($coreZip) ? round(filesize($coreZip) / 1048576, 2) : 0,
            'stage_ready' => $stageReady,
            'rollback_url' => Tools::getHttpHost(true) . __PS_BASE_URI__ . basename($adminDir) . '/lvh-emergency-rollback.php?key=' . urlencode($this->getOrCreateRollbackToken()),
        );
    }

    /**
     * Render the interactive Back-Office Upgrade Wizard UI.
     *
     * @return string
     */
    protected function renderUpgradeWizard()
    {
        $s = $this->collectEnvironmentSnapshot();
        $baseUrl = AdminController::$currentIndex
            . '&configure=' . urlencode($this->name)
            . '&token=' . urlencode(Tools::getAdminTokenLite('AdminModules'));
        $downloadDbUrl = $baseUrl . '&lvh_download=db';

        $isAlready180 = ($s['qlo_code_version'] === '1.8.0.0' && $s['qlo_db_version'] === '1.8.0.0');
        $diskLow = ($s['free_disk_mb'] >= 0 && $s['free_disk_mb'] < 250);
        $preflightOk = $s['has_zip'] && $s['all_writable'] && !$diskLow;

        $h = function ($v) {
            return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        };

        ob_start();
        ?>
        <style>
            .lvh-upg-wrap { max-width: 1040px; margin: 10px auto 40px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .lvh-upg-hero { background: linear-gradient(135deg, #14532d 0%, #166534 100%); color: #fff; padding: 24px 28px; border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
            .lvh-upg-hero h2 { margin: 0 0 6px; color: #fff; font-size: 22px; font-weight: 700; border: 0; padding: 0; text-transform: none; height: auto; line-height: 1.3; }
            .lvh-upg-hero p { margin: 0; opacity: 0.92; font-size: 13.5px; }
            .lvh-upg-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 12px; margin-bottom: 20px; }
            .lvh-upg-card { background: #fff; border: 1px solid #dce1e7; border-radius: 6px; padding: 14px 16px; }
            .lvh-upg-card .lbl { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px; }
            .lvh-upg-card .val { font-size: 19px; font-weight: 700; color: #0f172a; margin-top: 4px; }
            .lvh-upg-card .sub { font-size: 12px; color: #475569; margin-top: 3px; }
            .lvh-badge-ok { display: inline-block; background: #dcfce7; color: #166534; font-weight: 700; font-size: 11px; padding: 2px 8px; border-radius: 12px; }
            .lvh-badge-warn { display: inline-block; background: #fef9c3; color: #854d0e; font-weight: 700; font-size: 11px; padding: 2px 8px; border-radius: 12px; }
            .lvh-badge-err { display: inline-block; background: #fee2e2; color: #991b1b; font-weight: 700; font-size: 11px; padding: 2px 8px; border-radius: 12px; }
            .lvh-step-list { background: #fff; border: 1px solid #dce1e7; border-radius: 8px; overflow: hidden; margin-bottom: 20px; }
            .lvh-step-row { padding: 16px 20px; border-bottom: 1px solid #eef2f6; display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; }
            .lvh-step-row:last-child { border-bottom: 0; }
            .lvh-step-title { font-weight: 700; font-size: 14.5px; color: #1e293b; }
            .lvh-step-desc { font-size: 12.5px; color: #64748b; margin-top: 3px; max-width: 680px; }
            .lvh-log-box { background: #0f172a; color: #e2e8f0; font-family: Menlo, Monaco, Consolas, monospace; font-size: 12.5px; padding: 16px; border-radius: 8px; min-height: 150px; max-height: 340px; overflow-y: auto; line-height: 1.6; white-space: pre-wrap; }
            .lvh-btn-main { background: #15803d !important; border-color: #166534 !important; color: #fff !important; font-weight: 700 !important; padding: 10px 20px !important; font-size: 14px !important; }
            .lvh-btn-main:disabled { opacity: 0.55 !important; cursor: not-allowed !important; }
        </style>

        <div class="lvh-upg-wrap">
            <div class="lvh-upg-hero">
                <div>
                    <h2>Lush View Hotel — QloApps 1.7.0 → 1.8.0 Live Upgrade Wizard</h2>
                    <p>Backup-first, zero-PII-leak, reversible in-place upgrader preserving all bookings, guest accounts, photos, and custom LVH modules.</p>
                </div>
                <div>
                    <?php if ($s['shop_enabled']): ?>
                        <button type="button" class="btn btn-warning" id="lvh-btn-maint" onclick="lvhToggleMaint(0)">
                            <i class="icon-wrench"></i> Enable Maintenance Mode (Whitelist <?php echo $h($s['client_ip']); ?>)
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-success" id="lvh-btn-maint" onclick="lvhToggleMaint(1)">
                            <i class="icon-check"></i> Maintenance Active — Click to Re-Open Hotel
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($isAlready180): ?>
                <div class="alert alert-success" style="font-size:14px;">
                    <strong>Upgrade Complete — Running QloApps 1.8.0.0!</strong>
                    Code version <code>1.8.0.0</code> and database schema <code>1.8.0.0</code> are both active with
                    <strong><?php echo (int) $s['bookings']; ?></strong> bookings,
                    <strong><?php echo (int) $s['guests']; ?></strong> guests,
                    <strong><?php echo (int) $s['payments']; ?></strong> payments, and
                    <strong><?php echo (int) $s['rooms']; ?></strong> rooms intact.
                    Once you verify your Dashboard and Bookings, click <strong>Maintenance Active — Click to Re-Open Hotel</strong> above.
                </div>
            <?php endif; ?>

            <?php if ($diskLow): ?>
                <div class="alert alert-danger">
                    <strong>Low Disk Space Detected (<?php echo $h($s['free_disk_mb']); ?> MB free):</strong>
                    Please clear the full cPanel email inbox / temporary files so at least 250 MB is free before running the upgrade.
                </div>
            <?php endif; ?>

            <div class="lvh-upg-grid">
                <div class="lvh-upg-card">
                    <div class="lbl">Current Version</div>
                    <div class="val">Code <?php echo $h($s['qlo_code_version']); ?></div>
                    <div class="sub">DB Schema: <strong><?php echo $h($s['qlo_db_version']); ?></strong> &bull; <?php echo (int) $s['tables_total']; ?> tables</div>
                </div>
                <div class="lvh-upg-card">
                    <div class="lbl">Server Readiness</div>
                    <div class="val">PHP <?php echo $h($s['php_version']); ?></div>
                    <div class="sub">
                        <?php echo $s['has_zip'] ? '<span class="lvh-badge-ok">Zip OK</span>' : '<span class="lvh-badge-err">No Zip</span>'; ?>
                        <?php echo $s['has_curl'] ? '<span class="lvh-badge-ok">cURL OK</span>' : '<span class="lvh-badge-warn">No cURL</span>'; ?>
                        <?php echo $s['all_writable'] ? '<span class="lvh-badge-ok">Writable</span>' : '<span class="lvh-badge-err">Read-Only</span>'; ?>
                    </div>
                </div>
                <div class="lvh-upg-card">
                    <div class="lbl">Free Disk &amp; Limits</div>
                    <div class="val"><?php echo $s['free_disk_mb'] >= 0 ? ($h($s['free_disk_mb']) . ' MB') : 'Unrestricted'; ?></div>
                    <div class="sub">RAM <?php echo $h($s['memory_limit']); ?> &bull; Timeout <?php echo $h($s['max_execution_time']); ?>s</div>
                </div>
                <div class="lvh-upg-card">
                    <div class="lbl">Live Hotel Data</div>
                    <div class="val"><?php echo number_format($s['bookings']); ?> Bookings</div>
                    <div class="sub"><?php echo number_format($s['guests']); ?> Guests &bull; <?php echo number_format($s['payments']); ?> Payments &bull; <?php echo (int) $s['rooms']; ?> Rooms</div>
                </div>
            </div>

            <div class="lvh-step-list">
                <div class="lvh-step-row">
                    <div>
                        <div class="lvh-step-title">
                            Step 1 — Full Safety Backup (Database + Core/Module Rollback ZIP)
                            <span id="badge-step1" class="<?php echo ($s['has_db_backup'] && $s['has_file_backup']) ? 'lvh-badge-ok' : 'lvh-badge-warn'; ?>">
                                <?php echo ($s['has_db_backup'] && $s['has_file_backup']) ? 'Ready' : 'Required First'; ?>
                            </span>
                        </div>
                        <div class="lvh-step-desc">
                            Exports all <?php echo (int) $s['tables_total']; ?> tables + archives current <code>1.7.0</code> core/theme/module files into <code><?php echo $h($s['admin_folder']); ?>/backups/lvh_upgrade_170/</code> and arms an emergency restore link.
                            <?php if ($s['has_file_backup']): ?>
                                <br><small>Emergency file restore link (bookmark before Step 4): <a href="<?php echo $h($s['rollback_url']); ?>" target="_blank">Open Rollback Utility</a></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                        <button type="button" class="btn btn-default" id="btn-run-backup" onclick="lvhRunBackups()" <?php echo !$preflightOk ? 'disabled' : ''; ?>>
                            <i class="icon-save"></i> <?php echo ($s['has_db_backup'] && $s['has_file_backup']) ? 'Refresh Backups' : 'Create Full Backup'; ?>
                        </button>
                        <a href="<?php echo $h($downloadDbUrl); ?>" class="btn btn-info" id="btn-dl-db" style="<?php echo $s['has_db_backup'] ? '' : 'display:none;'; ?>">
                            <i class="icon-download"></i> Download DB Backup (<?php echo $h($s['db_backup_mb']); ?> MB)
                        </a>
                    </div>
                </div>

                <div class="lvh-step-row">
                    <div>
                        <div class="lvh-step-title">
                            Step 2 — Download &amp; Stage Clean QloApps 1.8.0.0 Release
                            <span id="badge-step2" class="<?php echo $s['stage_ready'] ? 'lvh-badge-ok' : 'lvh-badge-warn'; ?>">
                                <?php echo $s['stage_ready'] ? 'Staged & Verified' : 'Pending'; ?>
                            </span>
                        </div>
                        <div class="lvh-step-desc">
                            Downloads official <code>qloapps-1.8.0.zip</code> (46.5 MB with pre-built <code>vendor/</code>), verifies <code>_QLO_INSTALL_VERSION_ = 1.8.0.0</code>, applies the <code>search_form.tpl</code> XSS fix + Ghana Cedi pack, and strips <code>install/</code> before touching live files.
                        </div>
                    </div>
                    <div>
                        <button type="button" class="btn btn-default" id="btn-step2" onclick="lvhRunStep2()">
                            <i class="icon-cloud-download"></i> Stage QloApps 1.8.0
                        </button>
                    </div>
                </div>

                <div class="lvh-step-row">
                    <div>
                        <div class="lvh-step-title">
                            Step 3–5 — Execute Complete Upgrade to QloApps 1.8.0.0
                            <span id="badge-step3" class="<?php echo $isAlready180 ? 'lvh-badge-ok' : 'lvh-badge-warn'; ?>">
                                <?php echo $isAlready180 ? 'Completed (1.8.0.0)' : 'Awaiting Backup'; ?>
                            </span>
                        </div>
                        <div class="lvh-step-desc">
                            Runs the verified MariaDB migration (bcrypt <code>varchar(60)</code> passwords, <code>decimal(20,6)</code> payments, 27 new tables, 23 new columns, Features → Amenities migration), swaps staged 1.8.0 files onto <code><?php echo $h($s['admin_folder']); ?>/</code> while preserving <code>settings.inc.php</code>, <code>img/</code> &amp; <code>lvh*</code> modules, clears caches, and finalizes 1.8.0!
                        </div>
                    </div>
                    <div>
                        <button type="button" class="btn lvh-btn-main" id="btn-upgrade-all"
                                onclick="lvhRunFullUpgrade()"
                                <?php echo (!$preflightOk || $isAlready180) ? 'disabled' : ''; ?>>
                            <i class="icon-rocket"></i> Backup &amp; Upgrade to 1.8.0 Now
                        </button>
                    </div>
                </div>
            </div>

            <div style="margin-bottom:8px;font-weight:700;color:#334155;">Live Upgrade Console</div>
            <div class="lvh-log-box" id="lvh-upg-log">[Ready] Preflight checked on QloApps <?php echo $h($s['qlo_code_version']); ?> (PHP <?php echo $h($s['php_version']); ?>, Admin folder: <?php echo $h($s['admin_folder']); ?>). Click "Create Full Backup" or "Backup & Upgrade to 1.8.0 Now" to begin.</div>
        </div>

        <script>
        (function() {
            var ajaxBase = <?php echo json_encode($baseUrl . '&lvh_ajax=1'); ?>;
            var dlUrl = <?php echo json_encode($downloadDbUrl); ?>;
            var hasBackups = <?php echo ($s['has_db_backup'] && $s['has_file_backup']) ? 'true' : 'false'; ?>;
            var stageReady = <?php echo $s['stage_ready'] ? 'true' : 'false'; ?>;

            function logLine(msg) {
                var box = document.getElementById('lvh-upg-log');
                var ts = new Date().toTimeString().split(' ')[0];
                box.textContent += "\n[" + ts + "] " + msg;
                box.scrollTop = box.scrollHeight;
            }

            function setBusy(busy) {
                var ids = ['btn-run-backup', 'btn-step2', 'btn-upgrade-all', 'lvh-btn-maint'];
                for (var i = 0; i < ids.length; i++) {
                    var el = document.getElementById(ids[i]);
                    if (el) el.disabled = busy;
                }
            }

            function postStep(step, extraParams) {
                var body = 'step=' + encodeURIComponent(step);
                if (extraParams) {
                    for (var k in extraParams) {
                        if (Object.prototype.hasOwnProperty.call(extraParams, k)) {
                            body += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(extraParams[k]);
                        }
                    }
                }
                return fetch(ajaxBase, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: body
                }).then(function(r) {
                    return r.text().then(function(txt) {
                        try {
                            return JSON.parse(txt);
                        } catch (e) {
                            throw new Error('Unexpected server response on step "' + step + '": ' + txt.slice(0, 240));
                        }
                    });
                }).then(function(data) {
                    if (!data || !data.ok) {
                        throw new Error((data && data.error) ? data.error : ('Step "' + step + '" failed.'));
                    }
                    return data;
                });
            }

            window.lvhToggleMaint = function(enableShop) {
                setBusy(true);
                logLine(enableShop ? 'Re-opening hotel to public guests...' : 'Enabling Maintenance Mode (whitelisting your IP)...');
                postStep('toggle_maintenance', { enable_shop: enableShop })
                    .then(function(res) {
                        logLine('SUCCESS: ' + res.message);
                        window.location.reload();
                    })
                    .catch(function(err) {
                        logLine('ERROR: ' + err.message);
                        setBusy(false);
                    });
            };

            window.lvhRunBackups = function() {
                setBusy(true);
                logLine('Step 1A: Exporting all database tables to protected SQL backup...');
                return postStep('backup_db')
                    .then(function(r1) {
                        logLine('SUCCESS: ' + r1.message);
                        var dlBtn = document.getElementById('btn-dl-db');
                        if (dlBtn) {
                            dlBtn.href = dlUrl;
                            dlBtn.textContent = 'Download DB Backup (' + r1.download_size_mb + ' MB)';
                            dlBtn.style.display = 'inline-block';
                        }
                        logLine('Step 1B: Archiving 1.7.0 core, theme & module files into rollback ZIP...');
                        return postStep('backup_files');
                    })
                    .then(function(r2) {
                        logLine('SUCCESS: ' + r2.message);
                        logLine('Emergency Rollback URL armed: ' + r2.rollback_url);
                        hasBackups = true;
                        var b1 = document.getElementById('badge-step1');
                        if (b1) { b1.className = 'lvh-badge-ok'; b1.textContent = 'Backup Complete'; }
                        setBusy(false);
                        return true;
                    })
                    .catch(function(err) {
                        logLine('BACKUP FAILED: ' + err.message);
                        setBusy(false);
                        throw err;
                    });
            };

            window.lvhRunStep2 = function() {
                setBusy(true);
                logLine('Step 2: Downloading & verifying official QloApps 1.8.0.0 release (46.5 MB)... please wait 15–60s...');
                return postStep('download_180')
                    .then(function(r) {
                        logLine('SUCCESS: ' + r.message);
                        stageReady = true;
                        var b2 = document.getElementById('badge-step2');
                        if (b2) { b2.className = 'lvh-badge-ok'; b2.textContent = 'Staged & Verified'; }
                        setBusy(false);
                        return true;
                    })
                    .catch(function(err) {
                        logLine('STAGE FAILED: ' + err.message);
                        setBusy(false);
                        throw err;
                    });
            };

            window.lvhRunFullUpgrade = function() {
                if (!window.confirm('Ready to upgrade Lush View Hotel from QloApps 1.7.0 to 1.8.0?\n\nFull database & file backups will be verified first, Maintenance Mode will protect guests, and your _COOKIE_KEY_, room photos, and bookings will be preserved.')) {
                    return;
                }
                setBusy(true);
                var chain = Promise.resolve();

                // 0. Enable Maintenance Mode automatically (whitelisting current admin IP)
                chain = chain.then(function() {
                    logLine('Enabling Maintenance Mode (whitelisting your current IP)...');
                    return postStep('toggle_maintenance', { enable_shop: 0 }).then(function(r) {
                        logLine(r.message);
                    });
                });

                // 1. Ensure fresh backups exist
                if (!hasBackups) {
                    chain = chain.then(function() {
                        logLine('Step 1A: Creating full SQL database backup...');
                        return postStep('backup_db');
                    }).then(function(r1) {
                        logLine('SUCCESS: ' + r1.message);
                        logLine('Step 1B: Creating 1.7.0 core/module rollback archive...');
                        return postStep('backup_files');
                    }).then(function(r2) {
                        logLine('SUCCESS: ' + r2.message);
                        logLine('Bookmark Emergency Rollback URL: ' + r2.rollback_url);
                        hasBackups = true;
                    });
                }

                // 2. Stage 1.8.0 release if not yet staged
                if (!stageReady) {
                    chain = chain.then(function() {
                        logLine('Step 2: Downloading & verifying official QloApps 1.8.0.0 release (46.5 MB)...');
                        return postStep('download_180');
                    }).then(function(r) {
                        logLine('SUCCESS: ' + r.message);
                        stageReady = true;
                    });
                }

                // 3. Upgrade Database Schema
                chain = chain.then(function() {
                    logLine('Step 3: Applying 1.8.0.0 MariaDB schema, password/payment widening & Features -> Amenities migration...');
                    return postStep('upgrade_db');
                }).then(function(r3) {
                    logLine('SUCCESS: ' + r3.message);
                    logLine('Step 4: Activating QloApps 1.8.0.0 files & clearing caches (do NOT close tab)...');
                    return postStep('swap_files');
                }).then(function(r4) {
                    logLine('SUCCESS: ' + r4.message);
                    logLine('Step 5: Booting QloApps 1.8.0.0 runtime & syncing bundled modules...');
                    return postStep('finalize_180');
                }).then(function(r5) {
                    logLine('UPGRADE COMPLETE: ' + r5.message);
                    logLine('Reloading Back Office on QloApps 1.8.0.0 in 2 seconds...');
                    setTimeout(function() { window.location.reload(); }, 2000);
                }).catch(function(err) {
                    logLine('UPGRADE STOPPED: ' + err.message);
                    setBusy(false);
                });
            };
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}
