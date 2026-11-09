<?php
/**
 * Lush View Hotel - Bookora Home 1 webpage (/home-1).
 *
 * @author    Lush View Hotel
 * @copyright 2026 Lush View Hotel
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/../../classes/LvhBookoraFrontController.php';

class LvhBookoraHome1ModuleFrontController extends LvhBookoraFrontController
{
    public $lvhPage = 'home1';
    public $lvhNoHero = true;

    public function init()
    {
        parent::init();
        $this->context->smarty->assign('meta_title', 'Home 1 - Lush View Hotel');
    }
}
