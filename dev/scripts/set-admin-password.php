<?php
/**
 * Set a known password for a back-office employee (or a customer) in the LOCAL
 * development database.
 *
 * WHY THIS SCRIPT EXISTS
 * ----------------------
 * Passwords imported from the production dump cannot be used to log in locally.
 * QloApps' legacy scheme is md5(_COOKIE_KEY_ . $password), and _COOKIE_KEY_ is a
 * random per-installation salt held in config/settings.inc.php on the live
 * server. That file was never exported, so your local install generated a
 * DIFFERENT key. The stored hashes are therefore unverifiable here - not broken,
 * just salted with a key nobody has.
 *
 * This script writes a fresh hash using YOUR local key, so you can get in.
 *
 * USAGE (run from the repository root, inside the container)
 * ---------------------------------------------------------
 *   docker compose exec app php dev/scripts/set-admin-password.php --list
 *   docker compose exec app php dev/scripts/set-admin-password.php you@example.com 'NewPassword123'
 *   docker compose exec app php dev/scripts/set-admin-password.php you@example.com 'NewPassword123' --customer
 *
 * It modifies whichever database config/settings.inc.php currently points at
 * (see dev/scripts/use-db.sh) and prints that name before changing anything.
 *
 * This is a local development tool. It has no business running against
 * production, and it deliberately refuses to if the database host is not local.
 */

if (PHP_SAPI !== 'cli') {
    exit("This script must be run from the command line.\n");
}

$root = dirname(__DIR__, 2);
$settings = $root . '/config/settings.inc.php';

if (!is_file($settings)) {
    exit("error: $settings not found.\n       Install the application first - see docs/project/05-local-development-setup.md\n");
}

// config/settings.inc.php is nothing but define() calls, so requiring it is safe
// and gives us the credentials and the cookie key without parsing anything.
require_once $settings;

$args = array_values(array_filter($argv, function ($a) { return $a !== $argv[0]; }));
$asCustomer = in_array('--customer', $args, true);
$args = array_values(array_filter($args, function ($a) { return $a !== '--customer'; }));

$dbName = defined('_DB_NAME_') ? _DB_NAME_ : '(unknown)';
$dbServer = defined('_DB_SERVER_') ? _DB_SERVER_ : '(unknown)';
$prefix = defined('_DB_PREFIX_') ? _DB_PREFIX_ : 'qlo_';
$table = $prefix . ($asCustomer ? 'customer' : 'employee');

echo "Target database : $dbName on host '$dbServer'\n";
echo "Target table    : $table\n\n";

// Refuse to run against anything that is not this machine or the compose network.
$allowed = ['localhost', '127.0.0.1', 'db', '::1'];
if (!in_array(strtolower($dbServer), $allowed, true)) {
    exit("error: refusing to run - database host '$dbServer' is not local.\n"
       . "       This tool is for the local development database only.\n");
}

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb3', $dbServer, $dbName);
try {
    $pdo = new PDO($dsn, _DB_USER_, _DB_PASSWD_, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    exit("error: could not connect to the database: " . $e->getMessage() . "\n"
       . "       Is the container running?  docker compose up -d\n");
}

// --list: show the accounts that exist, so you know which email to reset.
if (in_array('--list', $args, true)) {
    $idCol = $asCustomer ? 'id_customer' : 'id_employee';
    $rows = $pdo->query(
        "SELECT `$idCol` AS id, `email`, `active` FROM `$table` ORDER BY `$idCol`"
    )->fetchAll(PDO::FETCH_ASSOC);
    printf("%-6s %-8s %s\n", 'ID', 'ACTIVE', 'EMAIL');
    foreach ($rows as $r) {
        printf("%-6s %-8s %s\n", $r['id'], $r['active'], $r['email']);
    }
    printf("\n%d account(s) in %s.%s\n", count($rows), $dbName, $table);
    if ($asCustomer) {
        echo "\nNOTE: the first employee account is what you want for the back office.\n";
        echo "      Re-run without --customer to list those instead.\n";
    }
    exit(0);
}

if (count($args) < 2) {
    exit("usage: php dev/scripts/set-admin-password.php <email> <new-password> [--customer]\n"
       . "       php dev/scripts/set-admin-password.php --list\n");
}

list($email, $password) = $args;

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit("error: '$email' is not a valid email address.\n");
}
if (strlen($password) < 8) {
    exit("error: password must be at least 8 characters (QloApps rejects shorter ones).\n");
}

// Find the account.
$stmt = $pdo->prepare("SELECT * FROM `$table` WHERE `email` = :email LIMIT 1");
$stmt->execute([':email' => $email]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$account) {
    exit("error: no " . ($asCustomer ? 'customer' : 'employee') . " with email '$email' in $dbName.\n"
       . "       Run with --list to see the accounts that exist.\n");
}

// Decide the hash scheme from the ACTUAL column width, not from an assumption.
// This is the crux: varchar(32) can hold only the legacy MD5 digest, while
// bcrypt needs 60 characters. Writing bcrypt into a 32-char column either fails
// silently (STRICT sql_mode) or truncates and locks the account out.
$col = $pdo->query(
    "SELECT CHARACTER_MAXIMUM_LENGTH AS len
       FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = " . $pdo->quote($dbName) . "
        AND TABLE_NAME = " . $pdo->quote($table) . "
        AND COLUMN_NAME = 'passwd'"
)->fetch(PDO::FETCH_ASSOC);

$width = $col ? (int) $col['len'] : 0;
$useBcrypt = $width >= 60;

if ($useBcrypt) {
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $scheme = 'bcrypt (60 chars)';
} else {
    $hash = md5(_COOKIE_KEY_ . $password);
    $scheme = 'legacy md5 with your local _COOKIE_KEY_ (32 chars)';
    echo "WARNING: $table.passwd is varchar($width), too narrow for bcrypt.\n";
    echo "         Falling back to the legacy MD5 scheme so the write is not truncated.\n";
    echo "         Apply dev/migrations/001_widen_password_columns.up.sql to enable bcrypt.\n\n";
}

$idCol = $asCustomer ? 'id_customer' : 'id_employee';
$sets = '`passwd` = :passwd';
$params = [':passwd' => $hash, ':id' => $account[$idCol]];

// Employees track when their password was last generated; leave it stale and the
// back office may immediately prompt for another change.
if (!$asCustomer) {
    $sets .= ', `last_passwd_gen` = NOW()';
}
// An inactive account cannot log in regardless of the password.
if (!$asCustomer && (int) $account['active'] !== 1) {
    $sets .= ', `active` = 1';
    echo "NOTE: this employee was inactive; it is being re-activated so you can log in.\n\n";
}

$sql = "UPDATE `$table` SET $sets WHERE `$idCol` = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

// Read it back and verify, rather than trusting the UPDATE.
$stmt = $pdo->prepare("SELECT `passwd` FROM `$table` WHERE `$idCol` = :id");
$stmt->execute([':id' => $account[$idCol]]);
$stored = (string) $stmt->fetchColumn();

$ok = $useBcrypt
    ? password_verify($password, $stored)
    : hash_equals($stored, md5(_COOKIE_KEY_ . $password));

echo "Account      : $email (id {$account[$idCol]})\n";
echo "Scheme used  : $scheme\n";
echo "Stored length: " . strlen($stored) . " characters\n";
echo "Verified     : " . ($ok ? "YES - the hash validates against the password you supplied" : "NO - something is wrong, do not rely on this") . "\n\n";

if (!$ok) {
    exit(1);
}

$adminPath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
echo "You can now log in at:  http://localhost:8080/admin-dev/\n";
echo "  email   : $email\n";
echo "  password: the one you supplied\n";
if (!$asCustomer) {
    echo "\nIf the back office still refuses the login, clear the caches:\n";
    echo "  rm -f cache/class_index.php\n";
    echo "  rm -rf cache/smarty/compile/* cache/smarty/cache/*\n";
}
