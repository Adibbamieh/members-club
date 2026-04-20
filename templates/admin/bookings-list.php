<?php
/**
 * Admin template: Bookings list for an event (with no-show marking).
 *
 * Variables: $event, $bookings, $stats
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
    <h1>
        <?php printf( esc_html__( 'Bookings: %s', 'sws-members-club' ), esc_html( $event->title ) ); ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=sws-events&action=edit&event_id=' . $event->id ) ); ?>" class="page-title-action"><?php esc_html_e( '&larr; Back to Event', 'sws-members-club' ); ?></a>
    </h1>

    <?php if ( isset( $_GET['noshow_marked'] ) ) : ?>
        <div class="notice notice-warning is-dismissible"><p><?php esc_html_e( 'No-show recorded and penalty strike added.', 'sws-members-club' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['refunded'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Booking refunded.', 'sws-members-club' ); ?></p></div>
    <?php endif; ?>

    <!-- Event summary -->
    <div class="sws-event-summary">
        <span><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $event->event_date ) ) ); ?></span>
        &bull;
        <span><?php echo esc_html( date_i18n( 'H:i', strtotime( $event->event_time_start ) ) . ' – ' . date_i18n( 'H:i', strtotime( $event->event_time_end ) ) ); ?></span>
        &bull;
        <span><?php echo esc_html( $event->venue_name ); ?></span>
        &bull;
        <strong><?php echo esc_html( $stats->confirmed ); ?> / <?php echo esc_html( $event->capacity ); ?></strong> <?php esc_html_e( 'confirmed', 'sws-members-club' ); ?>
        <?php if ( $stats->waitlisted > 0 ) : ?>
            &bull; <strong><?php echo esc_html( $stats->waitlisted ); ?></strong> <?php esc_html_e( 'waitlisted', 'sws-members-club' ); ?>
        <?php endif; ?>
        &bull;
        <strong>&pound;<?php echo esc_html( number_format( (float) $stats->revenue, 2 ) ); ?></strong> <?php esc_html_e( 'revenue', 'sws-members-club' ); ?>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Member', 'sws-members-club' ); ?></th>
                <th><?php esc_html_e( 'Email', 'sws-members-club' ); ?></th>
                <th><?php esc_html_e( 'Type', 'sws-members-club' ); ?></th>
                <th><?php esc_html_e( 'Guest', 'sws-members-club' ); ?></th>
                <th><?php esc_html_e( 'Amount', 'sws-members-club' ); ?></th>
                <th><?php esc_html_e( 'Status', 'sws-members-club' ); ?></th>
                <th><?php esc_html_e( 'Booked', 'sws-members-club' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'sws-members-club' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $bookings ) ) : ?>
                <tr><td colspan="8"><?php esc_html_e( 'No bookings yet.', 'sws-members-club' ); ?></td></tr>
            <?php else : ?>
                <?php foreach ( $bookings as $booking ) : ?>
                    <tr>
                        <td>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=sws-members&action=view&user_id=' . $booking->member_user_id ) ); ?>">
                                <?php echo esc_html( $booking->display_name ); ?>
                            </a>
                        </td>
                        <td><?php echo esc_html( $booking->user_email ); ?></td>
                        <td><?php echo $booking->is_guest_ticket ? esc_html__( 'Guest', 'sws-members-club' ) : esc_html__( 'Member', 'sws-members-club' ); ?></td>
                        <td><?php echo $booking->guest_name ? esc_html( $booking->guest_name ) : '—'; ?></td>
                        <td>
                            <?php if ( (float) $booking->amount_paid > 0 ) : ?>
                                &pound;<?php echo esc_html( number_format( (float) $booking->amount_paid, 2 ) ); ?>
                            <?php else : ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="sws-status sws-status--<?php echo esc_attr( $booking->status ); ?>">
                                <?php echo esc_html( ucfirst( str_replace( '_', ' ', $booking->status ) ) ); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html( date_i18n( 'M j, g:i A', strtotime( $booking->booked_at ) ) ); ?></td>
                        <td>
                            <?php if ( $booking->status === 'confirmed' ) : ?>
                                <?php if ( $event->status === 'completed' ) : ?>
                                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sws_mark_noshow&booking_id=' . $booking->id ), 'sws_mark_noshow_' . $booking->id ) ); ?>" class="sws-action-link sws-action-link--danger" onclick="return confirm('<?php echo esc_js( __( 'Mark as no-show? This will add a penalty strike.', 'sws-members-club' ) ); ?>');">
                                        <?php esc_html_e( 'No-Show', 'sws-members-club' ); ?>
                                    </a>
                                <?php endif; ?>
                                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sws_admin_refund&booking_id=' . $booking->id ), 'sws_admin_refund_' . $booking->id ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Refund this booking?', 'sws-members-club' ) ); ?>');">
                                    <?php esc_html_e( 'Refund', 'sws-members-club' ); ?>
                                </a>
                            <?php elseif ( $booking->status === 'waitlisted' ) : ?>
                                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sws_admin_remove_waitlist&booking_id=' . $booking->id ), 'sws_admin_remove_waitlist_' . $booking->id ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Remove from waitlist?', 'sws-members-club' ) ); ?>');">
                                    <?php esc_html_e( 'Remove', 'sws-members-club' ); ?>
                                </a>
                            <?php else : ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
