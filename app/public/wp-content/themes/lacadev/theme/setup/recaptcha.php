<?php
/**
 * Google reCAPTCHA v3 Integration
 *
 * Handles invisible reCAPTCHA for Login, Register, and Comments.
 *
 * @package LaCaDev
 */

if (!defined('ABSPATH')) {
    exit;
}

class Laca_Recaptcha {

    private $site_key;
    private $secret_key;
    private $score_threshold;
    private $expected_hostname;

    public function __construct() {
        $this->site_key = getOption('recaptcha_site_key');
        $this->secret_key = getOption('recaptcha_secret_key');
        $this->score_threshold = (float) getOption('recaptcha_score') ?: 0.5;
        $this->expected_hostname = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));

        // Skip if keys are missing
        if (empty($this->site_key) || empty($this->secret_key)) {
            return;
        }

        // Frontend Scripts
        add_action('login_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);

        // Login Integration
        if (getOption('enable_recaptcha_login')) {
            add_action('login_form', [$this, 'print_hidden_field']);
            add_filter('authenticate', [$this, 'verify_login'], 30, 3);
        }

        // Register Integration
        if (getOption('enable_recaptcha_register')) {
            add_action('register_form', [$this, 'print_hidden_field']);
            add_filter('registration_errors', [$this, 'verify_registration'], 10, 3);
        }

        // Comment Integration
        if (getOption('enable_recaptcha_comment')) {
            add_action('comment_form', [$this, 'print_hidden_field']);
            add_filter('preprocess_comment', [$this, 'verify_comment']);
        }

        // Expose filter for external verification (e.g. Contact Form)
        add_filter('laca_verify_recaptcha', function($_is_valid, $token) {
            $verify = $this->verify_token($token);
            return is_wp_error($verify) ? $verify : true;
        }, 10, 2);
    }

    /**
     * Enqueue Google reCAPTCHA Script
     */
    public function enqueue_scripts() {
        wp_enqueue_script('google-recaptcha', "https://www.google.com/recaptcha/api.js?render={$this->site_key}", [], null, false);

        $site_key = esc_js($this->site_key);
        $script = "
            document.addEventListener('DOMContentLoaded', function() {
                var forms = document.querySelectorAll('#loginform, #registerform, #commentform');

                var actionMap = {
                    'loginform': 'login',
                    'registerform': 'register',
                    'commentform': 'comment'
                };

                forms.forEach(function(form) {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        var action = actionMap[form.id] || 'submit';
                        grecaptcha.ready(function() {
                            grecaptcha.execute('{$site_key}', {action: action}).then(function(token) {
                                var input = form.querySelector('.laca-recaptcha-response');
                                if (input) {
                                    input.value = token;
                                }
                                form.submit();
                            });
                        });
                    });
                });
            });
        ";

        wp_add_inline_script('google-recaptcha', $script);

        add_filter('script_loader_tag', function($tag, $handle) {
            if ($handle === 'google-recaptcha' && defined('LACA_CSP_NONCE')) {
                return str_replace('<script ', '<script nonce="' . LACA_CSP_NONCE . '" ', $tag);
            }
            return $tag;
        }, 10, 2);
    }

    /**
     * Print hidden input field + honeypot + timestamp
     */
    public function print_hidden_field() {
        echo '<input type="hidden" name="laca_recaptcha_response" class="laca-recaptcha-response" value="">';
        echo '<input type="text" name="laca_hp_email" class="laca-hp-email" value="" autocomplete="off" tabindex="-1" aria-hidden="true" style="position:absolute;left:-9999px;opacity:0;pointer-events:none;">';
        echo '<input type="hidden" name="laca_form_ts" value="' . esc_attr((string) time()) . '">';
    }

    /**
     * Verify Token Logic
     */
    private function verify_token($token, $expected_action = 'submit') {
        if (empty($token)) {
            return new WP_Error('recaptcha_error', __('Vui lòng tải lại trang để xác thực reCAPTCHA.', 'laca'));
        }

        $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
            'body' => [
                'secret'   => $this->secret_key,
                'response' => $token,
                'remoteip' => $_SERVER['REMOTE_ADDR'],
            ],
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('recaptcha_error', __('Lỗi kết nối đến máy chủ xác thực.', 'laca'));
        }

        $body   = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if (!is_array($result)) {
            return new WP_Error('recaptcha_error', __('Phản hồi xác thực không hợp lệ.', 'laca'));
        }

        if (empty($result['success']) || !$result['success']) {
            return new WP_Error('recaptcha_error', __('Xác thực reCAPTCHA thất bại. Bạn có phải là robot?', 'laca'));
        }

        if (empty($result['score']) || !is_numeric($result['score']) || (float) $result['score'] < $this->score_threshold) {
            return new WP_Error('recaptcha_low_score', __('Hệ thống nghi ngờ bạn là robot. Điểm tín nhiệm thấp.', 'laca'));
        }

        if (!empty($expected_action) && (!isset($result['action']) || $result['action'] !== $expected_action)) {
            return new WP_Error('recaptcha_action_mismatch', __('Phiên xác thực không hợp lệ (action).', 'laca'));
        }

        if (!empty($this->expected_hostname) && !empty($result['hostname'])) {
            $result_hostname = strtolower((string) $result['hostname']);
            if ($result_hostname !== $this->expected_hostname) {
                return new WP_Error('recaptcha_hostname_mismatch', __('Phiên xác thực không hợp lệ (hostname).', 'laca'));
            }
        }

        if (!empty($result['challenge_ts'])) {
            $challenge_time = strtotime((string) $result['challenge_ts']);
            if ($challenge_time !== false && (time() - $challenge_time) > 180) {
                return new WP_Error('recaptcha_timeout', __('Mã xác thực đã hết hạn, vui lòng thử lại.', 'laca'));
            }
        }

        return true;
    }

    /**
     * Validate anti-bot context fields (honeypot + timing).
     */
    private function validate_submission_context() {
        $honeypot = sanitize_text_field(wp_unslash($_POST['laca_hp_email'] ?? ''));
        if ($honeypot !== '') {
            return new WP_Error('bot_detected', __('Yêu cầu không hợp lệ.', 'laca'));
        }

        $form_ts = (int) ($_POST['laca_form_ts'] ?? 0);
        if ($form_ts <= 0) {
            return new WP_Error('invalid_form_context', __('Phiên gửi biểu mẫu không hợp lệ.', 'laca'));
        }

        $elapsed = time() - $form_ts;
        if ($elapsed < 2 || $elapsed > 7200) {
            return new WP_Error('invalid_form_timing', __('Thời gian gửi biểu mẫu không hợp lệ.', 'laca'));
        }

        return true;
    }

    /**
     * Validate Login
     */
    public function verify_login($user, $_username, $_password) {
        $context_check = $this->validate_submission_context();
        if (is_wp_error($context_check)) {
            return $context_check;
        }

        $token  = sanitize_text_field(wp_unslash($_POST['laca_recaptcha_response'] ?? ''));
        $verify = $this->verify_token($token, 'login');
        if (is_wp_error($verify)) {
            return $verify;
        }

        return $user;
    }

    /**
     * Validate Registration
     */
    public function verify_registration($errors, $_sanitized_user_login, $_user_email) {
        $context_check = $this->validate_submission_context();
        if (is_wp_error($context_check)) {
            $errors->add('recaptcha_error', $context_check->get_error_message());
            return $errors;
        }

        $token  = sanitize_text_field(wp_unslash($_POST['laca_recaptcha_response'] ?? ''));
        $verify = $this->verify_token($token, 'register');
        if (is_wp_error($verify)) {
            $errors->add('recaptcha_error', $verify->get_error_message());
        }

        return $errors;
    }

    /**
     * Validate Comment
     */
    public function verify_comment($commentdata) {
        if (!is_user_logged_in()) {
            $context_check = $this->validate_submission_context();
            if (is_wp_error($context_check)) {
                wp_die($context_check->get_error_message());
            }

            $token  = sanitize_text_field(wp_unslash($_POST['laca_recaptcha_response'] ?? ''));
            $verify = $this->verify_token($token, 'comment');
            if (is_wp_error($verify)) {
                wp_die($verify->get_error_message());
            }
        }
        return $commentdata;
    }
}



add_action('init', function() {
    new Laca_Recaptcha();
});
