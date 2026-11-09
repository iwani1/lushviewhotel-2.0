<?php
/**
 * Lush View Hotel - Bookora Testimonials webpage (/testimonial) with working
 * guest review form.
 *
 * @author    Lush View Hotel
 * @copyright 2026 Lush View Hotel
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/../../classes/LvhBookoraFrontController.php';

class LvhBookoraTestimonialModuleFrontController extends LvhBookoraFrontController
{
    public $lvhPage = 'testimonial';

    public function init()
    {
        parent::init();
        $this->context->smarty->assign('meta_title', 'Guest Testimonials - Lush View Hotel');
    }

    public function postProcess()
    {
        parent::postProcess();

        if (Tools::isSubmit('submitBkReview')) {
            $name = trim((string) Tools::getValue('bk_review_name', ''));
            $email = trim((string) Tools::getValue('bk_review_email', ''));
            $rating = (int) Tools::getValue('bk_review_rating', 5);
            $message = trim((string) Tools::getValue('bk_review_message', ''));

            if ($name === '' || $message === '') {
                $this->errors[] = $this->l('Please provide your name and review message.');
                return;
            }
            if ($email !== '' && !Validate::isEmail($email)) {
                $this->errors[] = $this->l('Please provide a valid email address.');
                return;
            }

            LvhBookoraDataProvider::storeFeedback('review', array(
                'name' => $name,
                'email' => $email,
                'rating' => max(1, min(5, $rating)),
                'message' => Tools::substr($message, 0, 2000),
            ));

            $this->lvhRedirectWithFeedback('review');
        }
    }
}
