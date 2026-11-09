<?php
/**
 * Lush View Hotel - Bookora About Us webpage (/about).
 *
 * @author    Lush View Hotel
 * @copyright 2026 Lush View Hotel
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/../../classes/LvhBookoraFrontController.php';

class LvhBookoraAboutModuleFrontController extends LvhBookoraFrontController
{
    public $lvhPage = 'about';

    public function init()
    {
        parent::init();
        $this->context->smarty->assign('meta_title', 'About Us - Lush View Hotel');
    }
}
