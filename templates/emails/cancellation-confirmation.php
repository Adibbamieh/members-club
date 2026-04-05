<?php
/**
 * Email template: Cancellation Confirmation.
 *
 * Variables: $member_name, $event_title, $event_date, $event_time,
 *            $venue_name, $refund_amount
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<h2><?php esc_html_e( 'Booking Cancelled', 'sws-members-club' ); ?></h2>

<p><?php printf( esc_html__( 'Hi %s,', 'sws-members-club' ), esc_html( $member_name ) ); ?></p>

<p><?php printf( esc_html__( 'Your ticket for %s has been cancelled.', 'sws-members-club' ), '<strong>' . esc_html( $event_title ) . '</strong>' ); ?></p>

<dl class="email-detail">
    <dt><?php esc_html_e( 'Event', 'sws-members-club' ); ?></dt>
    <dd><?php echo esc_html( $event_title ); ?></dd>

    <dt><?php esc_html_e( 'Date', 'sws-members-club' ); ?></dt>
    <dd><?php echo esc_html( $event_date ); ?></dd>

    <?php if ( isset( $refund_amount ) && $refund_amount > 0 ) : ?>
        <dt><?php esc_html_e( 'Refund Amount', 'sws-members-club' ); ?></dt>
        <dd>&pound;<?php echo esc_html( number_format( $refund_amount, 2 ) ); ?></dd>
    <?php endif; ?>
</dl>

<?php if ( isset( $refund_amount ) && $refund_amount > 0 ) : ?>
    <p><?php esc_html_e( 'Your refund has been processed and should appear in your account within 5-10 business days.', 'sws-members-club' ); ?></p>
<?php endif; ?>
