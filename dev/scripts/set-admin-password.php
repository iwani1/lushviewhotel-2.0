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

// array_slice() drops the script name here, in the global scope. Do NOT try to
// compare against $argv[0] inside the closure below: $argv is not a
// superglobal, so it is undefined within callback scope and each element
// raises "Undefined variable $argv" / "array offset on null".
$args = array_slice($argv, 1);
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

// The charset name goes to mysqlnd, not to the server, and mysqlnd's charset
// table does not contain the server-side alias "utf8mb3": asking for it fails
// the handshake with SQLSTATE[HY000] [2019] Unknown character set before any
// query runs. "utf8" is the name mysqlnd knows, and on MariaDB it IS utf8mb3,
// which is what every table in this schema uses. Honour _DB_CHARSET_ from
// settings.inc.php when present, but fold the alias back down.
$charset = (defined('_DB_CHARSET_') && _DB_CHARSET_ !== '') ? _DB_CHARSET_ : 'utf8';
if (strtolower($charset) === 'utf8mb3') {
    $charset = 'utf8';
}
$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $dbServer, $dbName, $charset);
try {
    $pdo = new PDO($dsn, _DB_USER_, _DB_PASSWD_, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    exit("error: could not connect to the database: " . $e->getMessage() . "\n"
       . "       Two common causes: the containers are not running\n"
       . "         (podman-compose up -d   or   docker compose up -d),\n"
       . "       or settings.inc.php points at a database that does not exist yet\n"
       . "         (dev/scripts/use-db.sh fresh   puts you back on the clean one).\n");
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
    exit("usage: set-admin-password.php EMAIL NEW-PASSWORD [--customer]\n"
       . "       set-admin-password.php --list\n"
       . "\n"
       . "example, from the repo root on the host:\n"
       . "  podman-compose exec app php dev/scripts/set-admin-password.php --list\n"
       . "  podman-compose exec app php dev/scripts/set-admin-password.php you@example.com 'LocalDev-2026!'\n"
       . "\n"
       . "note: substitute your REAL address and password. Do not paste literal\n"
       . "      <angle-bracket placeholders> - a leading '<' is a shell redirect\n"
       . "      and fails with 'No such file or directory'.\n");
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
    echo "\nIf the back office still refuses the login, clear the caches FROM INSIDE\n";
    echo "the container - under rootless Podman the host user cannot delete the\n";
    echo "subtrees Apache created (they belong to a subuid), so a host-side rm\n";
    echo "fails half-way through:\n";
    echo "  podman-compose exec app sh -c 'rm -f /var/www/html/cache/class_index.php; \\\n";
    echo "    find /var/www/html/cache/smarty/compile /var/www/html/cache/smarty/cache \\\n";
    echo "      -mindepth 1 -maxdepth 1 ! -name index.php -exec rm -rf {} +'\n";
    echo "Equivalent from the host, via the container user namespace:\n";
    echo "  podman unshare sh -c 'rm -f cache/class_index.php; find cache/smarty/compile \\\n";
    echo "    cache/smarty/cache -mindepth 1 -maxdepth 1 ! -name index.php -exec rm -rf {} +'\n";
}
