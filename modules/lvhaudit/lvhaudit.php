<?php
/**
 * Lush View Hotel - Live System, Schema, Code & Operations Audit Module.
 *
 * Designed to be uploaded via QloApps Back Office ("Modules and Services ->
 * Add a new module") on a live 1.7.0 or 1.8.0 installation, run once to
 * export a sanitized diagnostic & operations bundle, and optionally
 * uninstalled afterward with zero footprint.
 *
 * Guarantees:
 *   - Zero database writes (no CREATE/ALTER/INSERT/UPDATE/DELETE beyond
 *     standard Module::install() registration).
 *   - Zero front-office or back-office hooks registered.
 *   - Never reads config/settings.inc.php or exports credentials/keys.
 *   - Strictly aggregates operational metrics; zero guest or staff PII.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 * @author    Lush View Hotel
 * @copyright 2026 Lush View Hotel
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/classes/LvhAuditBaseline.php';
require_once dirname(__FILE__) . '/classes/LvhAuditRedactor.php';
require_once dirname(__FILE__) . '/classes/LvhAuditCollector.php';
require_once dirname(__FILE__) . '/classes/LvhAuditReportFormatter.php';

class LvhAudit extends Module
{
    public function __construct()
    {
        $this->name = 'lvhaudit';
        $this->tab = 'administration';
        $this->version = '1.1.0';
        $this->author = 'Lush View Hotel';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('LVH System & Operations Audit');
        $this->description = $this->l('Read-only diagnostic and operations audit exporter (privacy-safe; zero secrets or guest PII).');
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Register module in qlo_module only — no hooks, no tables, no overrides.
     *
     * @return bool
     */
    public function install()
    {
        return parent::install();
    }

    /**
     * Cleanly unregister from qlo_module with zero leftover state.
     *
     * @return bool
     */
    public function uninstall()
    {
        return parent::uninstall();
    }

    /**
     * Render the Configure screen or stream the requested audit archive.
     *
     * @return string
     */
    public function getContent()
    {
        $auditError = '';

        if (Tools::isSubmit('submitLvhAuditDownloadZip') || Tools::isSubmit('submitLvhAuditDownloadJson')) {
            $includeCustom = (bool) Tools::getValue('lvh_include_custom_files');
            $wantZip = Tools::isSubmit('submitLvhAuditDownloadZip') && class_exists('ZipArchive');

            $collector = new LvhAuditCollector();
            $report = $collector->collectAll();
            $customFiles = $includeCustom ? $collector->collectCustomFilesForBundle() : array();

            if ($wantZip) {
                $sent = $this->streamZipBundle($report, $customFiles);
                if (!$sent) {
                    $auditError = $this->l('Could not create temporary ZIP archive on this server; please use the JSON download button instead.');
                }
            } else {
                $this->streamJsonReport($report, $customFiles);
            }
        }

        $collector = new LvhAuditCollector();
        $previewReport = $collector->collectAll();
        $kpis = LvhAuditReportFormatter::extractDashboardKpis($previewReport);

        $formAction = AdminController::$currentIndex
            . '&configure=' . urlencode($this->name)
            . '&tab_module=' . urlencode($this->tab)
            . '&module_name=' . urlencode($this->name)
            . '&token=' . Tools::getAdminTokenLite('AdminModules');

        $this->context->smarty->assign(array(
            'lvh_kpis' => $kpis,
            'lvh_health' => isset($previewReport['post_upgrade_180_health']) ? $previewReport['post_upgrade_180_health'] : array(),
            'lvh_form_action' => $formAction,
            'lvh_audit_error' => $auditError,
        ));

        return $this->display(__FILE__, 'views/templates/admin/configure.tpl');
    }

    /**
     * Build a temporary ZIP archive containing audit-report.json,
     * audit-summary.md, and any safe custom/modified source files, stream it
     * to the browser, unlink the temp file, and exit.
     *
     * @param array $report
     * @param array $customFiles
     * @return bool false only if ZipArchive could not create a temp file
     */
    protected function streamZipBundle($report, $customFiles)
    {
        $tmpDir = (defined('_PS_CACHE_DIR_') && is_dir(_PS_CACHE_DIR_) && is_writable(_PS_CACHE_DIR_))
            ? rtrim(_PS_CACHE_DIR_, '/\\')
            : rtrim(sys_get_temp_dir(), '/\\');

        $tmpFile = $tmpDir . '/lvhaudit_' . uniqid('', true) . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        $jsonFlags = defined('JSON_PRETTY_PRINT')
            ? (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : 0;
        $jsonPayload = json_encode($report, $jsonFlags);
        if ($jsonPayload === false) {
            $jsonPayload = $this->safeJsonFallback($report);
        }

        $markdown = LvhAuditReportFormatter::toMarkdown($report, $customFiles);

        $zip->addFromString('audit-report.json', $jsonPayload);
        $zip->addFromString('audit-summary.md', $markdown);

        foreach ($customFiles as $entryPath => $contents) {
            $zip->addFromString($entryPath, $contents);
        }

        $zip->close();

        if (!is_file($tmpFile)) {
            return false;
        }

        $downloadName = 'lushview-live-audit-' . gmdate('Ymd-His') . '.zip';
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Length: ' . (int) filesize($tmpFile));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        readfile($tmpFile);
        @unlink($tmpFile);
        exit;
    }

    /**
     * Stream a standalone JSON report (embedding custom text files if requested)
     * and exit.
     *
     * @param array $report
     * @param array $customFiles
     */
    protected function streamJsonReport($report, $customFiles)
    {
        if (!empty($customFiles)) {
            $encodedFiles = array();
            foreach ($customFiles as $path => $raw) {
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                if (in_array($ext, array('png', 'gif', 'jpg', 'jpeg'), true)) {
                    $encodedFiles[$path] = array('encoding' => 'base64', 'data' => base64_encode($raw));
                } else {
                    $encodedFiles[$path] = array('encoding' => 'utf8', 'data' => $this->ensureUtf8($raw));
                }
            }
            $report['bundled_custom_files'] = $encodedFiles;
        }

        $jsonFlags = defined('JSON_PRETTY_PRINT')
            ? (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : 0;
        $jsonPayload = json_encode($report, $jsonFlags);
        if ($jsonPayload === false) {
            $jsonPayload = $this->safeJsonFallback($report);
        }

        $downloadName = 'lushview-live-audit-' . gmdate('Ymd-His') . '.json';
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Length: ' . strlen($jsonPayload));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $jsonPayload;
        exit;
    }

    /**
     * Re-encode non-UTF8 strings recursively if json_encode encounters legacy
     * Latin-1 bytes in database descriptions or file contents.
     *
     * @param mixed $data
     * @return string
     */
    protected function safeJsonFallback($data)
    {
        $clean = $this->sanitizeUtf8Recursive($data);
        $flags = defined('JSON_PRETTY_PRINT') ? (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : 0;
        $res = json_encode($clean, $flags);
        return ($res !== false) ? $res : '{"error":"json_encode_failed"}';
    }

    /**
     * Recursively sanitize strings to valid UTF-8.
     *
     * @param mixed $val
     * @return mixed
     */
    protected function sanitizeUtf8Recursive($val)
    {
        if (is_array($val)) {
            $out = array();
            foreach ($val as $k => $v) {
                $out[$this->ensureUtf8((string) $k)] = $this->sanitizeUtf8Recursive($v);
            }
            return $out;
        }
        if (is_string($val)) {
            return $this->ensureUtf8($val);
        }
        return $val;
    }

    /**
     * Ensure a byte string is valid UTF-8 without requiring ext-mbstring.
     *
     * @param string $str
     * @return string
     */
    protected function ensureUtf8($str)
    {
        if ($str === '' || preg_match('//u', $str)) {
            return $str;
        }
        if (function_exists('iconv')) {
            $conv = @iconv('UTF-8', 'UTF-8//IGNORE', $str);
            if (is_string($conv)) {
                return $conv;
            }
        }
        return preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '?', $str);
    }
}
