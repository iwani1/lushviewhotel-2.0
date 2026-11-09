<?php
/**
 * Lush View Hotel - Bookora Base Front Controller for QloApps 1.8.0.
 *
 * Shared plumbing for every standalone Bookora webpage: assigns the common
 * Bookora dataset plus page-specific data and renders the page template
 * inside the standard theme layout (Bookora Header 2 + Footer 2).
 *
 * @author    Lush View Hotel
 * @copyright 2026 Lush View Hotel
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/LvhBookoraRouteMap.php';
require_once dirname(__FILE__) . '/LvhBookoraDataProvider.php';

abstract class LvhBookoraFrontController extends ModuleFrontController
{
    /** @var string Page key consumed by LvhBookoraDataProvider::getPageData() */
    public $lvhPage = '';

    /** @var bool When true no generic breadcrumb hero is injected (page draws its own) */
    public $lvhNoHero = false;

    /** @var string Optional hero title override (blog article headline) */
    public $lvhArticleTitle = '';

    /** @var string Template file inside modules/lvhbookora/views/templates/front/ */
    protected $lvhTemplate = '';

    public function initContent()
    {
        parent::initContent();

        $common = LvhBookoraDataProvider::getCommonData(
            $this->context,
            LvhBookoraDataProvider::getConfig()
        );
        $data = LvhBookoraDataProvider::getPageData(
            $this->context,
            $common,
            $this->lvhPage,
            (string) Tools::getValue('slug', '')
        );

        $this->context->smarty->assign(array('bk' => $common));
        $this->context->smarty->assign($data);

        if ($this->lvhPage === 'article' && !empty($data['bk_page']['article']['title'])) {
            $this->lvhArticleTitle = (string) $data['bk_page']['article']['title'];
        }

        $template = $this->lvhTemplate !== '' ? $this->lvhTemplate : $this->lvhPage . '.tpl';
        $this->setTemplate($template);
    }

    /**
     * Redirect back to the current module page with a feedback flag.
     *
     * @param string $flag
     */
    protected function lvhRedirectWithFeedback($flag)
    {
        $url = $this->context->link->getModuleLink('lvhbookora', $this->lvhPage !== 'article' ? $this->lvhPage : 'blog');
        if ($this->lvhPage === 'article') {
            $slug = (string) Tools::getValue('slug', '');
            if ($slug !== '') {
                $url = $this->context->link->getModuleLink('lvhbookora', 'article', array('slug' => $slug));
            }
        }
        $sep = (strpos($url, '?') !== false) ? '&' : '?';
        Tools::redirect($url . $sep . 'bk_feedback=' . urlencode($flag));
    }
}
