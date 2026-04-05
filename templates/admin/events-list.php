<?php
/**
 * Admin template: Events list.
 *
 * Variables available: $events, $total, $total_pages, $current_page, $search, $filter_status
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php esc_html_e( 'Events', 'sws-members-club' ); ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=sws-events&action=new' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'sws-members-club' ); ?></a>
    </h1>
    <span class="title-count"><?php echo esc_html( $total ); ?></span>
    <hr class="wp-header-end">

    <?php if ( isset( $_GET['created'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Event created.', 'sws-members-club' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['updated'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Event updated.', 'sws-members-club' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['duplicated'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Event duplicated as draft.', 'sws-members-club' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['cancelled'] ) ) : ?>
        <div class="notice notice-warning is-dismissible"><p><?php esc_html_e( 'Event cancelled. All bookings refunded and attendees notified.', 'sws-members-club' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['completed'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Event marked as completed.', 'sws-members-club' ); ?></p></div>
    <?php endif; ?>

    <form method="get" class="sws-filters">
        <input type="hidden" name="page" value="sws-events">

        <p class="search-box">
            <label class="screen-reader-text" for="sws-search"><?php esc_html_e( 'Search events', 'sws-members-club' ); ?></label>
            <input type="search" id="sws-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search by title or venue...', 'sws-members-club' ); ?>">
        </p>

        <select name="status">
            <option value=""><?php esc_html_e( 'All statuses', 'sws-members-club' ); ?></option>
            <option value="draft" <?php selected( $filter_status, 'draft' ); ?>><?php esc_html_e( 'Draft', 'sws-members-club' ); ?></option>
            <option value="published" <?php selected( $filter_status, 'published' ); ?>><?php esc_html_e( 'Published', 'sws-members-club' ); ?></option>
            <option value="cancelled" <?php selected( $filter_status, 'cancelled' ); ?>><?php esc_html_e( 'Cancelled', 'sws-members-club' ); ?></option>
            <option value="completed" <?php selected( $filter_status, 'completed' ); ?>><?php esc_html_e( 'Completed', 'sws-members-club' ); ?></option>
        </select>

        <?php submit_button( __( 'Filter', 'sws-members-club' ), 'secondary', 'filter', false ); ?>
    </form>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col"><?php esc_html_e( 'Title', 'sws-members-club' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Date', 'sws-members-club' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Time', 'sws-members-club' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Venue', 'sws-members-club' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Price', 'sws-members-club' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Capacity', 'sws-members-club' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Bookings', 'sws-members-club' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Status', 'sws-members-club' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Actions', 'sws-members-club' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $events ) ) : ?>
                <tr>
                    <td colspan="9"><?php esc_html_e( 'No events found.', 'sws-members-club' ); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ( $events as $event ) :
                    $events_model = new SWS_Events();
                    $stats = $events_model->get_stats( $event->id );
                ?>
                    <tr>
                        <td>
                            <strong>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=sws-events&action=edit&event_id=' . $event->id ) ); ?>">
                                    <?php echo esc_html( $event->title ); ?>
                                </a>
                            </strong>
                        </td>
                        <td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $event->event_date ) ) ); ?></td>
                        <td><?php echo esc_html( date_i18n( 'H:i', strtotime( $event->event_time_start ) ) . ' – ' . date_i18n( 'H:i', strtotime( $event->event_time_end ) ) ); ?></td>
                        <td><?php echo esc_html( $event->venue_name ?: '—' ); ?></td>
                        <td>
                            <?php if ( (float) $event->ticket_price > 0 ) : ?>
                                &pound;<?php echo esc_html( number_format( (float) $event->ticket_price, 2 ) ); ?>
                            <?php else : ?>
                                <?php esc_html_e( 'Free', 'sws-members-club' ); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( $stats->confirmed . ' / ' . $event->capacity ); ?></td>
                        <td>
                            <?php if ( $stats->waitlisted > 0 ) : ?>
                                <?php echo esc_html( $stats->confirmed ); ?>
                                <small>(+<?php echo esc_html( $stats->waitlisted ); ?> <?php esc_html_e( 'waitlist', 'sws-members-club' ); ?>)</small>
                            <?php else : ?>
                                <?php echo esc_html( $stats->confirmed ); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="sws-status sws-status--<?php echo esc_attr( $event->status ); ?>">
                                <?php echo esc_html( ucfirst( $event->status ) ); ?>
                            </span>
                        </td>
                        <td>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=sws-events&action=edit&event_id=' . $event->id ) ); ?>"><?php esc_html_e( 'Edit', 'sws-members-club' ); ?></a>
                            <?php if ( $event->status !== 'cancelled' && $event->status !== 'completed' ) : ?>
                                | <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sws_duplicate_event&event_id=' . $event->id ), 'sws_duplicate_event_' . $event->id ) ); ?>"><?php esc_html_e( 'Duplicate', 'sws-members-club' ); ?></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ( $total_pages > 1 ) : ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                echo paginate_links( array(
                    'base'      => add_query_arg( 'paged', '%#%' ),
                    'format'    => '',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'total'     => $total_pages,
                    'current'   => $current_page,
                ) );
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>
