<?php
/**
 * Lush View Hotel - Bookora single blog article webpage (/blog/{slug}) with
 * working comment form.
 *
 * @author    Lush View Hotel
 * @copyright 2026 Lush View Hotel
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/../../classes/LvhBookoraFrontController.php';

class LvhBookoraArticleModuleFrontController extends LvhBookoraFrontController
{
    public $lvhPage = 'article';

    public function init()
    {
        parent::init();
        $this->context->smarty->assign('meta_title', 'Hotel Article - Lush View Hotel');
    }

    public function postProcess()
    {
        parent::postProcess();

        if (Tools::isSubmit('submitBkComment')) {
            $name = trim((string) Tools::getValue('bk_comment_name', ''));
            $email = trim((string) Tools::getValue('bk_comment_email', ''));
            $comment = trim((string) Tools::getValue('bk_comment_message', ''));
            $slug = (string) Tools::getValue('slug', '');

            if ($name === '' || $comment === '') {
                $this->errors[] = $this->l('Please provide your name and comment.');
                return;
            }
            if ($email !== '' && !Validate::isEmail($email)) {
                $this->errors[] = $this->l('Please provide a valid email address.');
                return;
            }

            LvhBookoraDataProvider::storeFeedback('comment', array(
                'slug' => $slug,
                'name' => $name,
                'email' => $email,
                'message' => Tools::substr($comment, 0, 2000),
            ));

            $this->lvhRedirectWithFeedback('comment');
        }
    }
}
