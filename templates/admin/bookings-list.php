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
    <?php if ( isset( $_GET['booked'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Member booked successfully.', 'sws-members-club' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['booking_error'] ) ) : ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html( urldecode( $_GET['booking_error'] ) ); ?></p></div>
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

    <?php if ( $event->status === 'published' ) : ?>
        <?php
        $members_model  = new SWS_Members();
        $active_members = $members_model->list_members( array( 'per_page' => 500, 'status' => 'active', 'orderby' => 'display_name', 'order' => 'ASC' ) );
        ?>
        <div class="postbox" style="margin-bottom: 20px;">
            <button type="button" class="handlediv sws-toggle-form" aria-expanded="false">
                <span class="toggle-indicator" aria-hidden="true"></span>
            </button>
            <h2 class="hndle" style="cursor: pointer;" onclick="this.parentElement.querySelector('.inside').style.display = this.parentElement.querySelector('.inside').style.display === 'none' ? 'block' : 'none'; this.parentElement.querySelector('.sws-toggle-form').setAttribute('aria-expanded', this.parentElement.querySelector('.inside').style.display !== 'none');">
                <?php esc_html_e( 'Book a Member', 'sws-members-club' ); ?>
            </h2>
            <div class="inside" style="display: none;">
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="sws_admin_book_member">
                    <input type="hidden" name="event_id" value="<?php echo esc_attr( $event->id ); ?>">
                    <?php wp_nonce_field( 'sws_admin_book_member', 'sws_nonce' ); ?>

                    <table class="form-table">
                        <tr>
                            <th><label for="sws_member_user_id"><?php esc_html_e( 'Member', 'sws-members-club' ); ?></label></th>
                            <td>
                                <select name="member_user_id" id="sws_member_user_id" required>
                                    <option value=""><?php esc_html_e( '— Select a member —', 'sws-members-club' ); ?></option>
                                    <?php foreach ( $active_members['items'] as $m ) : ?>
                                        <option value="<?php echo esc_attr( $m->user_id ); ?>">
                                            <?php echo esc_html( $m->display_name . ' (' . $m->user_email . ') — ' . $m->tier_name ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Guest (+1)', 'sws-members-club' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="include_guest" id="sws_admin_include_guest" value="1">
                                    <?php esc_html_e( 'Include a guest ticket', 'sws-members-club' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr class="sws-admin-guest-fields" style="display: none;">
                            <th><label for="sws_admin_guest_name"><?php esc_html_e( 'Guest Name', 'sws-members-club' ); ?></label></th>
                            <td><input type="text" name="guest_name" id="sws_admin_guest_name" class="regular-text"></td>
                        </tr>
                        <tr class="sws-admin-guest-fields" style="display: none;">
                            <th><label for="sws_admin_guest_email"><?php esc_html_e( 'Guest Email', 'sws-members-club' ); ?></label></th>
                            <td><input type="email" name="guest_email" id="sws_admin_guest_email" class="regular-text"></td>
                        </tr>
                    </table>

                    <p class="description" style="margin-bottom: 12px;">
                        <?php esc_html_e( 'Admin bookings are created without payment. A confirmation email with calendar invite will be sent to the member.', 'sws-members-club' ); ?>
                    </p>

                    <?php submit_button( __( 'Book Member', 'sws-members-club' ), 'primary', 'submit', false ); ?>
                </form>
            </div>
        </div>
    <?php endif; ?>

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
