<?php
/**
 * Email template: Penalty Strike Notice.
 *
 * Variables: $member_name, $strike_count, $max_strikes, $reason, $is_restricted
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<?php if ( $is_restricted ) : ?>
    <h2><?php esc_html_e( 'Booking Privileges Restricted', 'sws-members-club' ); ?></h2>
<?php else : ?>
    <h2><?php esc_html_e( 'Penalty Strike Received', 'sws-members-club' ); ?></h2>
<?php endif; ?>

<p><?php printf( esc_html__( 'Hi %s,', 'sws-members-club' ), esc_html( $member_name ) ); ?></p>

<p>
    <?php
    printf(
        /* translators: %s: reason (e.g. "No show" or "Late cancellation") */
        esc_html__( 'You have received a penalty strike for: %s.', 'sws-members-club' ),
        '<strong>' . esc_html( ucfirst( str_replace( '_', ' ', $reason ) ) ) . '</strong>'
    );
    ?>
</p>

<div class="email-detail">
    <p style="text-align: center; font-size: 24px; margin: 12px 0;">
        <strong><?php echo esc_html( $strike_count ); ?></strong> / <?php echo esc_html( $max_strikes ); ?>
        <?php esc_html_e( 'strikes', 'sws-members-club' ); ?>
    </p>
</div>

<?php if ( $is_restricted ) : ?>
    <p><?php esc_html_e( 'As you have reached the maximum number of penalty strikes, your booking privileges have been restricted. You can still browse events and join waitlists, but you will not be able to book directly until your strikes are reviewed.', 'sws-members-club' ); ?></p>
    <p><?php esc_html_e( 'If you believe this is an error, please contact us.', 'sws-members-club' ); ?></p>
<?php else : ?>
    <p>
        <?php
        printf(
            /* translators: %d: remaining strikes before restriction */
            esc_html__( 'Please note that reaching %d strikes will result in your booking privileges being restricted to waitlist-only.', 'sws-members-club' ),
            $max_strikes
        );
        ?>
    </p>
<?php endif; ?>
