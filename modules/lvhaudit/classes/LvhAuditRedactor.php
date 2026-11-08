<?php
/**
 * Lush View Hotel - Strict Secret & PII Redaction Helper for System Audits.
 *
 * Ensures no credentials, cryptographic keys, payment gateway secrets, bank
 * details, guest PII, or employee PII ever leave the live server in an audit
 * export.
 *
 * @author    Lush View Hotel
 * @copyright 2026 Lush View Hotel
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class LvhAuditRedactor
{
    /**
     * Configuration keys that are safe and useful in cleartext even though
     * their name contains a generic token such as KEY or USER.
     *
     * @var array
     */
    protected static $safeKeyAllowlist = array(
        'PS_SHOP_NAME',
        'PS_SHOP_DOMAIN',
        'PS_SHOP_DOMAIN_SSL',
        'PS_SHOP_ENABLE',
        'PS_SSL_ENABLED',
        'PS_SSL_ENABLED_EVERYWHERE',
        'PS_MAIL_METHOD',
        'PS_MAIL_TYPE',
        'PS_MAIL_SERVER_PORT',
        'PS_MAIL_PASSWD_SET',
        'PS_SMTP_PORT',
        'PS_SMTP_ENCRYPTION',
        'PS_TIMEZONE',
        'PS_LANG_DEFAULT',
        'PS_CURRENCY_DEFAULT',
        'PS_COUNTRY_DEFAULT',
        'PS_TAX',
        'PS_TAX_DISPLAY',
        'PS_INVOICE',
        'PS_INVOICE_PREFIX',
        'PS_GUEST_CHECKOUT_ENABLED',
        'PS_ONE_PHONE_AT_LEAST',
        'PS_REGISTRATION_PROCESS_TYPE',
        'WK_HTL_ESTABLISHMENT_YEAR',
        'WK_HTL_CHAIN_NAME',
        'LVH_BRAND_TAGLINE',
        'LVH_BRAND_THEME_COLOR',
    );

    /**
     * Redact a Configuration key/value pair while preserving whether a value is
     * populated and its approximate length.
     *
     * @param string $key
     * @param mixed  $value
     * @return string
     */
    public static function redactConfig($key, $value)
    {
        if ($value === null || $value === false || $value === '') {
            return '';
        }

        $str = (string) $value;
        $len = strlen($str);
        $upperKey = strtoupper((string) $key);

        if (!in_array($upperKey, self::$safeKeyAllowlist, true)) {
            if (preg_match(
                '/(PASS|PWD|SECRET|KEY|TOKEN|SALT|HASH|CRYPT|COOKIE|RIJNDAEL|AUTH|PRIVATE|WEBHOOK|API|SMTP|USER|ACCOUNT|CLIENT_ID|MERCHANT|SIGNATURE|CERT|LICENSE|IBAN|SWIFT|BANK_WIRE|CHEQUE_ADDRESS|CHEQUE_NAME)/i',
                $upperKey
            )) {
                return '[REDACTED (set, len=' . $len . ')]';
            }
        }

        $clean = self::scrubText($str);
        if (strlen($clean) > 240) {
            $clean = substr($clean, 0, 240) . '... [truncated, total_len=' . $len . ']';
        }

        return $clean;
    }

    /**
     * Scrub emails, IP addresses, URL tokens, hashes, and phone numbers from
     * free-form text (logs, error messages, configuration strings).
     *
     * @param string $text
     * @return string
     */
    public static function scrubText($text)
    {
        if ($text === null || $text === '') {
            return '';
        }

        $out = (string) $text;

        // Bcrypthashes and JWT tokens
        $out = preg_replace('/\$2[aby]\$\d{2}\$[\/\.A-Za-z0-9]{50,}/', '[BCRYPT_REDACTED]', $out);
        $out = preg_replace('/\beyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\b/', '[JWT_REDACTED]', $out);

        // URL query tokens/keys/passwords
        $out = preg_replace(
            '/([?&](?:token|admin_token|ws_key|key|api_key|apikey|secret|pass|passwd|password)=)[^&\s\'"]+/i',
            '$1[REDACTED]',
            $out
        );

        // Email addresses
        $out = preg_replace(
            '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/',
            '[EMAIL_REDACTED]',
            $out
        );

        // IPv4 addresses (preserve localhost 127.0.0.1 for diagnostics)
        $out = preg_replace_callback(
            '/\b(?:\d{1,3}\.){3}\d{1,3}\b/',
            array('LvhAuditRedactor', 'redactIpMatch'),
            $out
        );

        // Hex tokens / MD5 / SHA hashes (24+ hex chars)
        $out = preg_replace('/\b[0-9a-fA-F]{24,}\b/', '[HEX_TOKEN_REDACTED]', $out);

        // Phone-number sequences (9-15 digits, preserving YYYY-MM-DD timestamps)
        $out = preg_replace_callback(
            '/(?<!\d)(?:\+\d{1,3}[\s\-]?)?(?:\(?\d{2,4}\)?[\s\-]?){2,3}\d{2,4}(?!\d)/',
            array('LvhAuditRedactor', 'redactPhoneMatch'),
            $out
        );

        return $out;
    }

    /**
     * Callback for phone-number scrubbing that preserves ISO dates/timestamps.
     *
     * @param array $matches
     * @return string
     */
    public static function redactPhoneMatch($matches)
    {
        $val = $matches[0];
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', trim($val))) {
            return $val;
        }
        $digits = preg_replace('/\D/', '', $val);
        $len = strlen($digits);
        if ($len < 9 || $len > 15) {
            return $val;
        }
        return '[PHONE_REDACTED]';
    }

    /**
     * Callback for IPv4 scrubbing that preserves 127.0.0.1 and version numbers.
     *
     * @param array $matches
     * @return string
     */
    public static function redactIpMatch($matches)
    {
        $ip = $matches[0];
        if ($ip === '127.0.0.1' || $ip === '0.0.0.0') {
            return $ip;
        }
        $parts = explode('.', $ip);
        foreach ($parts as $p) {
            if ((int) $p > 255) {
                return $ip;
            }
        }
        // Version-like strings such as 1.7.0.0 or 1.6.1.23 have first octet <= 2
        if ((int) $parts[0] <= 2 && (int) $parts[1] <= 20 && (int) $parts[2] <= 30) {
            return $ip;
        }
        return '[IP_REDACTED]';
    }

    /**
     * Scrub hardcoded secret literals if any custom PHP file assigns a literal
     * secret string directly in source code.
     *
     * @param string $source
     * @return string
     */
    public static function scrubPhpSource($source)
    {
        if ($source === null || $source === '') {
            return '';
        }

        // Redact define('...PASS...|...SECRET...|...KEY...', 'literal')
        $out = preg_replace(
            '/(define\s*\(\s*[\'"][^\'"]*(?:PASS|PWD|SECRET|PRIVATE_KEY|API_KEY|COOKIE_KEY|RIJNDAEL)[^\'"]*[\'"]\s*,\s*)[\'"][^\'"]+[\'"](\s*\))/i',
            '$1\'[REDACTED_LITERAL]\'$2',
            (string) $source
        );

        // Redact $api_secret / $secret_key / $password = 'literal'
        $out = preg_replace(
            '/(\$(?:[a-zA-Z0-9_]*(?:secret|passwd|password|api_key|apikey|private_key|auth_token|access_token)[a-zA-Z0-9_]*)\s*=\s*)[\'"][^\'"]{6,}[\'"]/i',
            '$1\'[REDACTED_LITERAL]\'',
            $out
        );

        return $out;
    }
}
