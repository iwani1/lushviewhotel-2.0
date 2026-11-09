<?php
/**
 * Lush View Hotel - Bookora Home 3 resort webpage (/home-3) with Specialties
 * Foods dining menu and Facts & Figures counters.
 *
 * @author    Lush View Hotel
 * @copyright 2026 Lush View Hotel
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/../../classes/LvhBookoraFrontController.php';

class LvhBookoraHome3ModuleFrontController extends LvhBookoraFrontController
{
    public $lvhPage = 'home3';
    public $lvhNoHero = true;

    public function init()
    {
        parent::init();
        $this->context->smarty->assign('meta_title', 'Home 3 - Resort & Dining - Lush View Hotel');
    }
}
