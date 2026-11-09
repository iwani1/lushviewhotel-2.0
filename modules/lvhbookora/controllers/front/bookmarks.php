<?php
/**
 * Lush View Hotel - Bookora Bookmarks webpage (/bookmarks) showing saved rooms.
 *
 * @author    Lush View Hotel
 * @copyright 2026 Lush View Hotel
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/../../classes/LvhBookoraFrontController.php';

class LvhBookoraBookmarksModuleFrontController extends LvhBookoraFrontController
{
    public $lvhPage = 'bookmarks';

    public function init()
    {
        parent::init();
        $this->context->smarty->assign('meta_title', 'Bookmarks - Lush View Hotel');
    }
}
