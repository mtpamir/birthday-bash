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
            '{site_title}'      => $this->get_blogname(),
            '{customer_name}'   => '',
            '{coupon_code}'     => '',
            '{coupon_amount}'   => '',
            '{coupon_type_text}'=> '',
            '{coupon_expiry_date}' => '',
        );

        // Call parent constructor
        parent::__construct();

        // Set email properties
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
     * Send the birthday coupon email.
     *
     * @param int $user_id The user ID.
     * @param int $coupon_id The coupon ID.
     * @param string $coupon_code The coupon code.
     * @return bool
     */
    public function send_birthday_coupon_email( $user_id, $coupon_id, $coupon_code ) {
        $user_info = get_userdata( $user_id );
        if ( ! $user_info ) {
            return false;
        }

        $coupon = new WC_Coupon( $coupon_id );

        $this->object    = $user_info; // For compatibility with WC_Email
        $this->recipient = $user_info->user_email;

        $coupon_amount_text = BirthdayBash_Helper::get_coupon_amount_text( $coupon );
        $coupon_expiry_date = $coupon->get_date_expires() ? date_i18n( wc_date_format(), $coupon->get_date_expires()->getOffsetTimestamp() ) : esc_html__( 'Never', 'birthday-bash' );

        $this->placeholders['{customer_name}']    = $user_info->display_name;
        $this->placeholders['{coupon_code}']      = $coupon_code;
        $this->placeholders['{coupon_amount}']    = $coupon->get_amount();
        $this->placeholders['{coupon_type_text}'] = $coupon_amount_text; // e.g., "10% discount", "$5 fixed discount"
        $this->placeholders['{coupon_expiry_date}'] = $coupon_expiry_date;

        return $this->trigger( $this->recipient, $this->get_default_subject(), $this->get_default_heading(), $user_info, $coupon );
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
                'email'         => $this,
                'email_heading' => $this->get_heading(),
                'email_message' => get_option( 'birthday_bash_email_message', '' ),
                'coupon_code'   => $this->placeholders['{coupon_code}'],
                'coupon_amount' => $this->placeholders['{coupon_amount}'],
                'coupon_type_text' => $this->placeholders['{coupon_type_text}'],
                'customer_name' => $this->placeholders['{customer_name}'],
                'coupon_expiry_date' => $this->placeholders['{coupon_expiry_date}'],
                'logo_url'      => get_option( 'birthday_bash_email_logo', '' ),
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
                'email'         => $this,
                'email_heading' => $this->get_heading(),
                'email_message' => get_option( 'birthday_bash_email_message', '' ),
                'coupon_code'   => $this->placeholders['{coupon_code}'],
                'coupon_amount' => $this->placeholders['{coupon_amount}'],
                'coupon_type_text' => $this->placeholders['{coupon_type_text}'],
                'customer_name' => $this->placeholders['{customer_name}'],
                'coupon_expiry_date' => $this->placeholders['{coupon_expiry_date}'],
            ),
            'birthday-bash/',
            BIRTHDAY_BASH_PLUGIN_DIR . 'templates/'
        );
        return ob_get_clean();
    }
}
// REMOVED:
// add_filter( 'woocommerce_email_classes', 'birthday_bash_add_email_class' );
// function birthday_bash_add_email_class( $emails ) {
//     $emails['BirthdayBash_Birthday_Coupon'] = BirthdayBash_Email::get_instance();
//     return $emails;
// }