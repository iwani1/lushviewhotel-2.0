<?php
/**
 * Lush View Hotel - Bookora working Newsletter subscription endpoint
 * (/newsletter-subscribe).
 *
 * @author    Lush View Hotel
 * @copyright 2026 Lush View Hotel
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/../../classes/LvhBookoraFrontController.php';

class LvhBookoraSubscribeModuleFrontController extends LvhBookoraFrontController
{
    public $lvhPage = 'subscribe';
    public $lvhNoHero = true;

    public function init()
    {
        parent::init();
        $this->context->smarty->assign('meta_title', 'Newsletter - Lush View Hotel');
    }

    public function postProcess()
    {
        parent::postProcess();

        if (Tools::isSubmit('submitBkNewsletter')) {
            $email = trim((string) Tools::getValue('bk_newsletter_email', ''));
            $return = trim((string) Tools::getValue('bk_return', ''));

            if ($email === '' || !Validate::isEmail($email)) {
                $this->errors[] = $this->l('Please provide a valid email address.');
                return;
            }

            LvhBookoraDataProvider::storeSubscriber($email);

            $target = $return !== '' ? $return : $this->context->link->getPageLink('index', true);
            if (!preg_match('#^https?://#i', $target)) {
                $target = $this->context->link->getPageLink('index', true);
            }
            $sep = (strpos($target, '?') !== false) ? '&' : '?';
            Tools::redirect($target . $sep . 'bk_feedback=newsletter#bk-footer');
        }
    }

    public function initContent()
    {
        // Subscription is a pure redirect endpoint — never render a page body.
        if (Tools::isSubmit('submitBkNewsletter') && count($this->errors)) {
            return;
        }
        Tools::redirect($this->context->link->getPageLink('index', true));
    }
}
