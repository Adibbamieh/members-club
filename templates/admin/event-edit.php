<?php
/**
 * Admin template: Event create/edit form + dashboard.
 *
 * Variables available: $event (null for new), $stats (null for new), $is_new
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$title                    = $event->title ?? '';
$description              = $event->description ?? '';
$venue_name               = $event->venue_name ?? '';
$venue_address            = $event->venue_address ?? '';
$event_date               = $event->event_date ?? '';
$event_time_start         = $event->event_time_start ?? '';
$event_time_end           = $event->event_time_end ?? '';
$capacity                 = $event->capacity ?? 50;
$ticket_price             = $event->ticket_price ?? '0.00';
$currency                 = $event->currency ?? 'GBP';
$cancellation_cutoff      = $event->cancellation_cutoff_hours ?? get_option( 'sws_default_cancellation_cutoff', 48 );
$waitlist_enabled         = isset( $event->waitlist_enabled ) ? (int) $event->waitlist_enabled : 1;
$status                   = $event->status ?? 'draft';
?>
<div class="wrap">
    <h1>
        <?php echo $is_new ? esc_html__( 'Add New Event', 'sws-members-club' ) : esc_html( $title ); ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=sws-events' ) ); ?>" class="page-title-action"><?php esc_html_e( '&larr; Back to Events', 'sws-members-club' ); ?></a>
    </h1>

    <div id="poststuff">
        <div id="post-body" class="metabox-holder columns-2">

            <!-- Main content: Edit form -->
            <div id="post-body-content">
                <div class="postbox">
                    <h2 class="hndle"><?php esc_html_e( 'Event Details', 'sws-members-club' ); ?></h2>
                    <div class="inside">
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <input type="hidden" name="action" value="sws_save_event">
                            <?php if ( ! $is_new ) : ?>
                                <input type="hidden" name="event_id" value="<?php echo esc_attr( $event->id ); ?>">
                            <?php endif; ?>
                            <?php wp_nonce_field( 'sws_save_event', 'sws_nonce' ); ?>

                            <table class="form-table">
                                <tr>
                                    <th><label for="event_title"><?php esc_html_e( 'Title', 'sws-members-club' ); ?></label></th>
                                    <td><input type="text" name="title" id="event_title" value="<?php echo esc_attr( $title ); ?>" class="large-text" required></td>
                                </tr>
                                <tr>
                                    <th><label for="event_description"><?php esc_html_e( 'Description', 'sws-members-club' ); ?></label></th>
                                    <td>
                                        <?php
                                        wp_editor( $description, 'event_description', array(
                                            'textarea_name' => 'description',
                                            'media_buttons' => false,
                                            'textarea_rows' => 8,
                                            'teeny'         => true,
                                        ) );
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="event_venue_name"><?php esc_html_e( 'Venue Name', 'sws-members-club' ); ?></label></th>
                                    <td><input type="text" name="venue_name" id="event_venue_name" value="<?php echo esc_attr( $venue_name ); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th><label for="event_venue_address"><?php esc_html_e( 'Venue Address', 'sws-members-club' ); ?></label></th>
                                    <td><textarea name="venue_address" id="event_venue_address" class="large-text" rows="3"><?php echo esc_textarea( $venue_address ); ?></textarea></td>
                                </tr>
                                <tr>
                                    <th><label for="event_date"><?php esc_html_e( 'Date', 'sws-members-club' ); ?></label></th>
                                    <td><input type="date" name="event_date" id="event_date" value="<?php echo esc_attr( $event_date ); ?>" required></td>
                                </tr>
                                <tr>
                                    <th><label for="event_time_start"><?php esc_html_e( 'Start Time', 'sws-members-club' ); ?></label></th>
                                    <td><input type="time" name="event_time_start" id="event_time_start" value="<?php echo esc_attr( $event_time_start ); ?>" required></td>
                                </tr>
                                <tr>
                                    <th><label for="event_time_end"><?php esc_html_e( 'End Time', 'sws-members-club' ); ?></label></th>
                                    <td><input type="time" name="event_time_end" id="event_time_end" value="<?php echo esc_attr( $event_time_end ); ?>" required></td>
                                </tr>
                                <tr>
                                    <th><label for="event_capacity"><?php esc_html_e( 'Capacity', 'sws-members-club' ); ?></label></th>
                                    <td><input type="number" name="capacity" id="event_capacity" value="<?php echo esc_attr( $capacity ); ?>" min="1" class="small-text"></td>
                                </tr>
                                <tr>
                                    <th><label for="event_ticket_price"><?php esc_html_e( 'Ticket Price (&pound;)', 'sws-members-club' ); ?></label></th>
                                    <td><input type="number" name="ticket_price" id="event_ticket_price" value="<?php echo esc_attr( $ticket_price ); ?>" step="0.01" min="0" class="small-text">
                                    <p class="description"><?php esc_html_e( 'Set to 0.00 for free events.', 'sws-members-club' ); ?></p></td>
                                </tr>
                                <tr>
                                    <th><label for="event_cancellation"><?php esc_html_e( 'Cancellation Cutoff (hours)', 'sws-members-club' ); ?></label></th>
                                    <td><input type="number" name="cancellation_cutoff_hours" id="event_cancellation" value="<?php echo esc_attr( $cancellation_cutoff ); ?>" min="0" class="small-text"></td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Waitlist', 'sws-members-club' ); ?></th>
                                    <td><label><input type="checkbox" name="waitlist_enabled" value="1" <?php checked( $waitlist_enabled, 1 ); ?>> <?php esc_html_e( 'Enable waitlist when event is full', 'sws-members-club' ); ?></label></td>
                                </tr>
                                <tr>
                                    <th><label for="event_status"><?php esc_html_e( 'Status', 'sws-members-club' ); ?></label></th>
                                    <td>
                                        <select name="status" id="event_status">
                                            <option value="draft" <?php selected( $status, 'draft' ); ?>><?php esc_html_e( 'Draft', 'sws-members-club' ); ?></option>
                                            <option value="published" <?php selected( $status, 'published' ); ?>><?php esc_html_e( 'Published', 'sws-members-club' ); ?></option>
                                            <?php if ( ! $is_new ) : ?>
                                                <option value="cancelled" <?php selected( $status, 'cancelled' ); ?>><?php esc_html_e( 'Cancelled', 'sws-members-club' ); ?></option>
                                                <option value="completed" <?php selected( $status, 'completed' ); ?>><?php esc_html_e( 'Completed', 'sws-members-club' ); ?></option>
                                            <?php endif; ?>
                                        </select>
                                    </td>
                                </tr>
                            </table>

                            <?php submit_button( $is_new ? __( 'Create Event', 'sws-members-club' ) : __( 'Update Event', 'sws-members-club' ) ); ?>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Sidebar: Dashboard stats (only for existing events) -->
            <div id="postbox-container-1" class="postbox-container">
                <?php if ( ! $is_new && $stats ) : ?>

                    <div class="postbox">
                        <h2 class="hndle"><?php esc_html_e( 'Event Dashboard', 'sws-members-club' ); ?></h2>
                        <div class="inside">
                            <ul class="sws-stats-list">
                                <li>
                                    <span class="sws-stat-label"><?php esc_html_e( 'Confirmed Bookings', 'sws-members-club' ); ?></span>
                                    <span class="sws-stat-value"><?php echo esc_html( $stats->confirmed ); ?> / <?php echo esc_html( $stats->capacity ); ?></span>
                                </li>
                                <li>
                                    <span class="sws-stat-label"><?php esc_html_e( 'Spots Remaining', 'sws-members-club' ); ?></span>
                                    <span class="sws-stat-value"><?php echo esc_html( $stats->spots_remaining ); ?></span>
                                </li>
                                <li>
                                    <span class="sws-stat-label"><?php esc_html_e( 'Waitlist', 'sws-members-club' ); ?></span>
                                    <span class="sws-stat-value"><?php echo esc_html( $stats->waitlisted ); ?></span>
                                </li>
                                <li>
                                    <span class="sws-stat-label"><?php esc_html_e( 'Cancellations', 'sws-members-club' ); ?></span>
                                    <span class="sws-stat-value"><?php echo esc_html( $stats->cancelled ); ?></span>
                                </li>
                                <li>
                                    <span class="sws-stat-label"><?php esc_html_e( 'No-shows', 'sws-members-club' ); ?></span>
                                    <span class="sws-stat-value"><?php echo esc_html( $stats->no_shows ); ?></span>
                                </li>
                                <li class="sws-stat-highlight">
                                    <span class="sws-stat-label"><?php esc_html_e( 'Revenue', 'sws-members-club' ); ?></span>
                                    <span class="sws-stat-value">&pound;<?php echo esc_html( number_format( (float) $stats->revenue, 2 ) ); ?></span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="postbox">
                        <h2 class="hndle"><?php esc_html_e( 'Quick Actions', 'sws-members-club' ); ?></h2>
                        <div class="inside">
                            <p>
                                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sws_duplicate_event&event_id=' . $event->id ), 'sws_duplicate_event_' . $event->id ) ); ?>" class="button">
                                    <?php esc_html_e( 'Duplicate Event', 'sws-members-club' ); ?>
                                </a>
                            </p>

                            <?php if ( $event->status === 'published' ) : ?>
                                <p>
                                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sws_cancel_event&event_id=' . $event->id ), 'sws_cancel_event_' . $event->id ) ); ?>" class="button sws-button-danger" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to cancel this event? All bookings will be refunded.', 'sws-members-club' ) ); ?>');">
                                        <?php esc_html_e( 'Cancel Event', 'sws-members-club' ); ?>
                                    </a>
                                </p>
                                <p>
                                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sws_complete_event&event_id=' . $event->id ), 'sws_complete_event_' . $event->id ) ); ?>" class="button" onclick="return confirm('<?php echo esc_js( __( 'Mark this event as completed?', 'sws-members-club' ) ); ?>');">
                                        <?php esc_html_e( 'Mark as Completed', 'sws-members-club' ); ?>
                                    </a>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php endif; ?>
            </div>

        </div>
    </div>
</div>
