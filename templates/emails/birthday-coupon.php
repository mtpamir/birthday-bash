<?php
/**
 * Birthday coupon email template.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/birthday-coupon.php.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email );

if ( ! empty( $logo_url ) ) {
    // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage -- Image is for an email template, direct URL is necessary.
    echo '<p style="text-align:center;"><img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( get_bloginfo( 'name', 'display' ) ) . '" style="max-width:200px; height:auto;"/></p>';
}
?>

<p><?php echo esc_html( $email_heading ); ?></p>

<p>
    <?php
    echo wp_kses_post(
        str_replace(
            array( '{customer_name}', '{coupon_code}', '{coupon_amount}', '{coupon_type_text}', '{coupon_expiry_date}' ),
            array( $customer_name, $coupon_code, $coupon_amount, $coupon_type_text, $coupon_expiry_date ),
            $email_message
        )
    );
    ?>
</p>

<?php if ( ! empty( $coupon_code ) ) : ?>
    <p style="text-align:center; margin-top: 25px;">
        <strong style="font-size: 24px; background-color: #eee; padding: 10px 20px; border-radius: 5px; display: inline-block;">
            <?php echo esc_html( $coupon_code ); ?>
        </strong>
    </p>
    <?php if ( $coupon_expiry_date !== esc_html__( 'Never', 'birthday-bash' ) ) : ?>
        <p style="text-align:center; font-size: 14px; color: #777;">
            <?php
            /* translators: %s: coupon expiry date */
            printf( esc_html__( 'Expires on: %s', 'birthday-bash' ), esc_html( $coupon_expiry_date ) );
            ?>
        </p>
    <?php endif; ?>
<?php endif; ?>

<?php
if ( get_option( 'birthday_bash_unsubscribe_option', 1 ) ) {
    $unsubscribe_link = add_query_arg(
        array(
            'birthday_bash_unsubscribe' => 1,
            'user_id' => $email->object->ID,
            'nonce' => wp_create_nonce( 'birthday_bash_unsubscribe_' . $email->object->ID ),
        ),
        wc_get_page_permalink( 'myaccount' )
    );
    ?>
    <p style="text-align:center; font-size:12px; margin-top:30px;"><?php
        /* translators: %s: unsubscribe link */
        // phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment -- False positive: inner string without placeholder does not require comment.
        printf( esc_html__( 'If you no longer wish to receive these emails, you can %s.', 'birthday-bash' ),
            '<a href="' . esc_url( $unsubscribe_link ) . '">' . esc_html__( 'unsubscribe here', 'birthday-bash' ) . '</a>');
    ?></p>
    <?php
}

/**
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );