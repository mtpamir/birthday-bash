<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class BirthdayBash_Email
 *
 * Handles sending birthday coupon emails.
 */
class BirthdayBash_Email extends WC_Email {

    protected static $instance = null;

    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor for BirthdayBash_Email.
     */
    protected function __construct() {
        $this->id             = 'birthday_bash_birthday_coupon';
        $this->title          = esc_html__( 'Birthday Coupon', 'birthday-bash' );
        $this->description    = esc_html__( 'This email is sent to customers on their birthday with a special coupon.', 'birthday-bash' );
        $this->template_html  = 'emails/birthday-coupon.php';
        $this->template_plain = 'emails/plain/birthday-coupon.php';
        $this->placeholders   = array(
            '{site_title}'         => $this->get_blogname(),
            '{customer_name}'      => '',
            '{coupon_code}'        => '',
            '{coupon_amount}'      => '',
            '{coupon_type_text}'   => '',
            '{coupon_expiry_date}' => '',
        );

        parent::__construct();

        $this->customer_email = true;
    }

    /**
     * Get default email heading.
     *
     * @return string
     */
    public function get_default_heading() {
        return get_option( 'birthday_bash_email_greeting', esc_html__( 'Happy Birthday, {customer_name}!', 'birthday-bash' ) );
    }

    /**
     * Get default email subject.
     *
     * @return string
     */
    public function get_default_subject() {
        return esc_html__( 'A Birthday Gift from {site_title}!', 'birthday-bash' );
    }

    /**
     * Apply placeholders to subject.
     *
     * @return string
     */
    public function get_subject() {
        $subject = $this->get_option( 'subject', $this->get_default_subject() );
        return $this->format_string( $subject );
    }

    /**
     * Apply placeholders to heading.
     *
     * @return string
     */
    public function get_heading() {
        $heading = $this->get_option( 'heading', $this->get_default_heading() );
        return $this->format_string( $heading );
    }

    /**
     * Get email headers.
     *
     * @return string
     */
    public function get_headers() {
        $headers = "Content-Type: text/html\r\n";
        return apply_filters( 'woocommerce_email_headers', $headers, $this->id, $this->object );
    }

    /**
     * Get content html.
     *
     * @return string
     */
    public function get_content_html() {
        ob_start();
        wc_get_template(
            $this->template_html,
            array(
                'email'               => $this,
                'email_heading'       => $this->get_heading(),
                'email_message'       => get_option( 'birthday_bash_email_message', '' ),
                'coupon_code'         => $this->placeholders['{coupon_code}'] ?? '',
                'coupon_amount'       => $this->placeholders['{coupon_amount}'] ?? '',
                'coupon_type_text'    => $this->placeholders['{coupon_type_text}'] ?? '',
                'customer_name'       => $this->placeholders['{customer_name}'] ?? '',
                'coupon_expiry_date'  => $this->placeholders['{coupon_expiry_date}'] ?? '',
                'logo_url'            => get_option( 'birthday_bash_email_logo', '' ),
            ),
            'birthday-bash/',
            BIRTHDAY_BASH_PLUGIN_DIR . 'templates/'
        );
        return ob_get_clean();
    }

    /**
     * Get content plain.
     *
     * @return string
     */
    public function get_content_plain() {
        ob_start();
        wc_get_template(
            $this->template_plain,
            array(
                'email'               => $this,
                'email_heading'       => $this->get_heading(),
                'email_message'       => get_option( 'birthday_bash_email_message', '' ),
                'coupon_code'         => $this->placeholders['{coupon_code}'] ?? '',
                'coupon_amount'       => $this->placeholders['{coupon_amount}'] ?? '',
                'coupon_type_text'    => $this->placeholders['{coupon_type_text}'] ?? '',
                'customer_name'       => $this->placeholders['{customer_name}'] ?? '',
                'coupon_expiry_date'  => $this->placeholders['{coupon_expiry_date}'] ?? '',
            ),
            'birthday-bash/',
            BIRTHDAY_BASH_PLUGIN_DIR . 'templates/'
        );
        return ob_get_clean();
    }
}
