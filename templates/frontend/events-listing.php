<?php
/**
 * Frontend template: Events listing.
 *
 * Override by copying to: {theme}/sws-members-club/events-listing.php
 *
 * Variables: $events, $member, $events_included
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Find the page that has the [sws_booking] shortcode.
global $wpdb;
$booking_page_id = $wpdb->get_var(
    "SELECT ID FROM {$wpdb->posts}
     WHERE post_status = 'publish' AND post_type = 'page'
     AND post_content LIKE '%[sws_booking]%'
     LIMIT 1"
);
$booking_base_url = $booking_page_id ? get_permalink( $booking_page_id ) : get_permalink();
?>
<div class="sws-events">
    <?php if ( empty( $events ) ) : ?>
        <p class="sws-events__empty"><?php esc_html_e( 'No upcoming events at this time.', 'sws-members-club' ); ?></p>
    <?php else : ?>
        <div class="sws-events__grid">
            <?php foreach ( $events as $event ) : ?>
                <div class="sws-event-card" data-event-id="<?php echo esc_attr( $event->id ); ?>">
                    <div class="sws-event-card__header">
                        <h3 class="sws-event-card__title"><?php echo esc_html( $event->title ); ?></h3>
                        <span class="sws-event-card__date">
                            <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $event->event_date ) ) ); ?>
                        </span>
                    </div>

                    <div class="sws-event-card__details">
                        <div class="sws-event-card__time">
                            <?php echo esc_html( date_i18n( 'g:i A', strtotime( $event->event_time_start ) ) ); ?>
                            &ndash;
                            <?php echo esc_html( date_i18n( 'g:i A', strtotime( $event->event_time_end ) ) ); ?>
                        </div>

                        <?php if ( $event->venue_name ) : ?>
                            <div class="sws-event-card__venue"><?php echo esc_html( $event->venue_name ); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="sws-event-card__footer">
                        <div class="sws-event-card__price">
                            <?php if ( $events_included ) : ?>
                                <span class="sws-event-card__price-included"><?php esc_html_e( 'Included with your membership', 'sws-members-club' ); ?></span>
                            <?php elseif ( (float) $event->ticket_price > 0 ) : ?>
                                <span class="sws-event-card__price-amount">&pound;<?php echo esc_html( number_format( (float) $event->ticket_price, 2 ) ); ?></span>
                            <?php else : ?>
                                <span class="sws-event-card__price-free"><?php esc_html_e( 'Free', 'sws-members-club' ); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="sws-event-card__availability">
                            <?php if ( $event->is_full ) : ?>
                                <span class="sws-event-card__availability-full">
                                    <?php if ( $event->waitlist_enabled ) : ?>
                                        <?php esc_html_e( 'Full — Waitlist available', 'sws-members-club' ); ?>
                                    <?php else : ?>
                                        <?php esc_html_e( 'Fully booked', 'sws-members-club' ); ?>
                                    <?php endif; ?>
                                </span>
                            <?php else : ?>
                                <span class="sws-event-card__availability-open">
                                    <?php
                                    printf(
                                        /* translators: %d: number of spots remaining */
                                        esc_html( _n( '%d spot remaining', '%d spots remaining', $event->spots_remaining, 'sws-members-club' ) ),
                                        $event->spots_remaining
                                    );
                                    ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <a href="<?php echo esc_url( add_query_arg( 'event_id', $event->id, $booking_base_url ) ); ?>" class="sws-event-card__button">
                            <?php if ( $event->is_full && $event->waitlist_enabled ) : ?>
                                <?php esc_html_e( 'Join Waitlist', 'sws-members-club' ); ?>
                            <?php else : ?>
                                <?php esc_html_e( 'Book Now', 'sws-members-club' ); ?>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
