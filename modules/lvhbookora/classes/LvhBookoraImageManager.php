<?php
/**
 * Lush View Hotel - Bookora Photo Library for QloApps 1.8.0.
 *
 * Named photo slots for real hotel photography. Every slot declares the exact
 * pixel size the storefront needs (derived from the Bookora Home 2 template
 * artwork); uploads are validated as real images and cover-cropped to those
 * exact dimensions so nothing on the site ever stretches or pixelates.
 *
 * @author    Lush View Hotel
 * @copyright 2026 Lush View Hotel
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class LvhBookoraImageManager
{
    /** Maximum accepted upload size in bytes (10 MB). */
    const MAX_UPLOAD_BYTES = 10485760;

    /**
     * Photo slot registry: slot => display spec.
     * Sizes match the exact display areas of the Bookora Home 2 design
     * (validated against the original theme's demo artwork dimensions).
     *
     * @return array
     */
    public static function slots()
    {
        return array(
            'hero' => array(
                'label' => 'Home 2 Hero Background',
                'w' => 1920, 'h' => 760,
                'help' => 'Full-screen dark hero behind the welcome headline (2.53:1 wide banner).',
                'default' => 'hero-home2.jpg',
            ),
            'about_main' => array(
                'label' => 'About Portrait Photo',
                'w' => 800, 'h' => 1120,
                'help' => 'Tall portrait beside the About counter (use a vertical shot of the lobby or a suite).',
                'default' => 'wellness-concierge.jpg',
            ),
            'about_second' => array(
                'label' => 'About Landscape Photo',
                'w' => 1060, 'h' => 525,
                'help' => 'Wide photo under the About text with the rotating seal (pool, lounge, or exterior).',
                'default' => 'dining-lounge.jpg',
            ),
            'service_1' => array(
                'label' => 'Customer Service Background 1 — Room Booking',
                'w' => 1920, 'h' => 1080,
                'help' => 'Full-bleed background shown behind the first Customer Services panel.',
                'default' => 'wellness-concierge.jpg',
            ),
            'service_2' => array(
                'label' => 'Customer Service Background 2 — Fitness Center',
                'w' => 1920, 'h' => 1080,
                'help' => 'Full-bleed background shown behind the second Customer Services panel.',
                'default' => 'dining-lounge.jpg',
            ),
            'service_3' => array(
                'label' => 'Customer Service Background 3 — 24/7 Room Service',
                'w' => 1920, 'h' => 1080,
                'help' => 'Full-bleed background shown behind the third Customer Services panel.',
                'default' => 'conference-banquet.jpg',
            ),
            'service_4' => array(
                'label' => 'Customer Service Background 4 — Swimming Pool Access',
                'w' => 1920, 'h' => 1080,
                'help' => 'Full-bleed background shown behind the fourth Customer Services panel.',
                'default' => 'hero-home2.jpg',
            ),
            'team_1' => array(
                'label' => 'Team Member 1 Portrait',
                'w' => 712, 'h' => 680,
                'help' => 'Head-and-shoulders portrait for the first Our Expert card.',
                'default' => 'team-1.jpg',
            ),
            'team_2' => array(
                'label' => 'Team Member 2 Portrait',
                'w' => 712, 'h' => 680,
                'help' => 'Head-and-shoulders portrait for the second Our Expert card.',
                'default' => 'team-2.jpg',
            ),
            'team_3' => array(
                'label' => 'Team Member 3 Portrait',
                'w' => 712, 'h' => 680,
                'help' => 'Head-and-shoulders portrait for the third Our Expert card.',
                'default' => 'team-3.jpg',
            ),
            'team_4' => array(
                'label' => 'Team Member 4 Portrait',
                'w' => 712, 'h' => 680,
                'help' => 'Head-and-shoulders portrait for the fourth Our Expert card.',
                'default' => 'team-4.jpg',
            ),
            'testimonial_bg' => array(
                'label' => 'Testimonial Section Background',
                'w' => 1920, 'h' => 900,
                'help' => 'Soft full-width backdrop behind the guest reviews slider.',
                'default' => 'hero-home2.jpg',
            ),
        );
    }

    /**
     * Absolute directory where uploaded photos live.
     *
     * @return string
     */
    public static function uploadDir()
    {
        $dir = _PS_MODULE_DIR_ . 'lvhbookora/views/img/uploads/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
            @file_put_contents($dir . 'index.php', "<?php\nheader('HTTP/1.1 403 Forbidden');\nexit;\n");
        }
        return $dir;
    }

    /**
     * Full disk path of a slot's current photo (uploaded file if present).
     *
     * @param string $slot
     * @return string
     */
    public static function filePath($slot)
    {
        return self::uploadDir() . preg_replace('/[^a-z0-9_]/', '', $slot) . '.jpg';
    }

    /**
     * Whether the slot currently uses an uploaded photo.
     *
     * @param string $slot
     * @return bool
     */
    public static function hasUpload($slot)
    {
        $f = self::filePath($slot);
        return is_file($f) && filesize($f) > 0;
    }

    /**
     * Public URL for a slot: uploaded photo when present, otherwise the module
     * default. Cache-busting query keeps replaced photos fresh.
     *
     * @param Context $context
     * @param string $slot
     * @return string
     */
    public static function url(Context $context, $slot)
    {
        $spec = isset(self::slots()[$slot]) ? self::slots()[$slot] : null;
        if (self::hasUpload($slot)) {
            return $context->link->getMediaLink(
                _MODULE_DIR_ . 'lvhbookora/views/img/uploads/' . basename(self::filePath($slot))
            ) . '?v=' . (int) filemtime(self::filePath($slot));
        }
        if ($spec !== null) {
            return $context->link->getMediaLink(_MODULE_DIR_ . 'lvhbookora/views/img/' . $spec['default']);
        }
        return '';
    }

    /**
     * Process one uploaded file into its slot (validate + exact cover-crop).
     *
     * @param string $slot
     * @param array $file One $_FILES entry
     * @return string Empty string on success, error message otherwise
     */
    public static function processUpload($slot, $file)
    {
        $slots = self::slots();
        if (!isset($slots[$slot])) {
            return 'Unknown photo slot.';
        }
        $spec = $slots[$slot];

        if (empty($file) || !isset($file['error']) || is_array($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return ($file && isset($file['error']) && $file['error'] === UPLOAD_ERR_NO_FILE) ? '' : 'Upload failed (server rejected the file).';
        }
        if ((int) $file['size'] > self::MAX_UPLOAD_BYTES) {
            return 'File is too large (max 10 MB).';
        }

        $tmp = (string) $file['tmp_name'];
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return 'Invalid upload.';
        }

        // Real-image check (content, not extension)
        if (method_exists('ImageManager', 'isRealImage') && !ImageManager::isRealImage($tmp)) {
            return 'That file is not a valid image.';
        }
        $info = @getimagesize($tmp);
        if (!is_array($info) || empty($info[0]) || empty($info[1])) {
            return 'That file is not a valid image.';
        }

        $srcW = (int) $info[0];
        $srcH = (int) $info[1];
        if ($srcW < $spec['w'] / 2 || $srcH < $spec['h'] / 2) {
            return sprintf(
                'Image is too small for "%s" — need at least %d x %d px (uploaded %d x %d px).',
                $spec['label'],
                (int) ceil($spec['w'] / 2),
                (int) ceil($spec['h'] / 2),
                $srcW,
                $srcH
            );
        }

        $dst = self::filePath($slot);
        if (!self::coverCrop($tmp, $dst, (int) $spec['w'], (int) $spec['h'])) {
            return 'Server could not process the image (GD library error).';
        }
        return '';
    }

    /**
     * Delete an uploaded photo (slot reverts to its default artwork).
     *
     * @param string $slot
     * @return bool
     */
    public static function delete($slot)
    {
        $f = self::filePath($slot);
        return is_file($f) ? @unlink($f) : true;
    }

    /**
     * Cover-crop resize to EXACT dimensions, center-weighted, with EXIF
     * orientation handling. Always writes optimized JPEG.
     *
     * @param string $src
     * @param string $dst
     * @param int $w
     * @param int $h
     * @return bool
     */
    public static function coverCrop($src, $dst, $w, $h)
    {
        if (!function_exists('imagecreatetruecolor')) {
            return false;
        }

        $info = @getimagesize($src);
        if (!is_array($info)) {
            return false;
        }
        $type = (int) $info[2];

        switch ($type) {
            case IMAGETYPE_JPEG:
                $img = @imagecreatefromjpeg($src);
                break;
            case IMAGETYPE_PNG:
                $img = @imagecreatefrompng($src);
                break;
            case IMAGETYPE_GIF:
                $img = @imagecreatefromgif($src);
                break;
            case IMAGETYPE_WEBP:
                if (!function_exists('imagecreatefromwebp')) {
                    return false;
                }
                $img = @imagecreatefromwebp($src);
                break;
            default:
                return false;
        }
        if (!$img) {
            return false;
        }

        // EXIF auto-orient (camera photos)
        if (function_exists('exif_read_data') && ($type === IMAGETYPE_JPEG)) {
            $exif = @exif_read_data($src);
            if (!empty($exif['Orientation'])) {
                $img = self::orient($img, (int) $exif['Orientation']);
            }
        }

        $srcW = imagesx($img);
        $srcH = imagesy($img);
        if (!$srcW || !$srcH) {
            imagedestroy($img);
            return false;
        }

        // Cover-crop: scale so target fits inside source, then center-crop
        $scale = max($w / $srcW, $h / $srcH);
        $newW = (int) round($srcW * $scale);
        $newH = (int) round($srcH * $scale);
        $offX = (int) round(($newW - $w) / 2);
        $offY = (int) round(($newH - $h) / 2);

        $resized = imagecreatetruecolor($newW, $newH);
        imagecopyresampled($resized, $img, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);
        imagedestroy($img);

        $canvas = imagecreatetruecolor($w, $h);
        imagecopy($canvas, $resized, 0, 0, $offX, $offY, $w, $h);
        imagedestroy($resized);

        $ok = imagejpeg($canvas, $dst, 88);
        imagedestroy($canvas);
        return $ok && is_file($dst) && filesize($dst) > 0;
    }

    /**
     * Apply EXIF orientation to a GD image.
     *
     * @param resource $img
     * @param int $orientation
     * @return resource
     */
    protected static function orient($img, $orientation)
    {
        switch ($orientation) {
            case 3:
                return imagerotate($img, 180, 0);
            case 6:
                return imagerotate($img, -90, 0);
            case 8:
                return imagerotate($img, 90, 0);
            default:
                return $img;
        }
    }
}
