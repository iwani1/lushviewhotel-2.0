<?php
/**
 * Lush View Hotel - Bookora Projects / Gallery webpage (/project).
 *
 * @author    Lush View Hotel
 * @copyright 2026 Lush View Hotel
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/../../classes/LvhBookoraFrontController.php';

class LvhBookoraProjectModuleFrontController extends LvhBookoraFrontController
{
    public $lvhPage = 'project';

    public function init()
    {
        parent::init();
        $this->context->smarty->assign('meta_title', 'Our Projects - Lush View Hotel');
    }
}
