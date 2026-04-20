<?php
/**
 * Frontend template: My Tickets (member portal).
 *
 * Override by copying to: {theme}/sws-members-club/my-tickets.php
 *
 * Variables: $upcoming, $past, $member, $events_included
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="sws-my-tickets">
    <!-- Tab navigation -->
    <div class="sws-my-tickets__tabs">
        <button class="sws-my-tickets__tab sws-my-tickets__tab--active" data-tab="upcoming">
            <?php esc_html_e( 'Upcoming Events', 'sws-members-club' ); ?>
            <span class="sws-my-tickets__tab-count"><?php echo esc_html( count( $upcoming ) ); ?></span>
        </button>
        <button class="sws-my-tickets__tab" data-tab="past">
            <?php esc_html_e( 'Past Events', 'sws-members-club' ); ?>
            <span class="sws-my-tickets__tab-count"><?php echo esc_html( count( $past ) ); ?></span>
        </button>
    </div>

    <!-- Upcoming tab -->
    <div class="sws-my-tickets__panel sws-my-tickets__panel--active" data-panel="upcoming">
        <?php if ( empty( $upcoming ) ) : ?>
            <p class="sws-my-tickets__empty"><?php esc_html_e( 'You have no upcoming event tickets.', 'sws-members-club' ); ?></p>
        <?php else : ?>
            <div class="sws-my-tickets__list">
                <?php foreach ( $upcoming as $booking ) :
                    $bookings_model = new SWS_Bookings();
                    $can_cancel     = $bookings_model->is_within_cancellation_window( $booking );
                    $gcal_url       = SWS_Calendar::google_calendar_url( $booking );
                ?>
                    <div class="sws-ticket-card" data-booking-id="<?php echo esc_attr( $booking->id ); ?>">
                        <div class="sws-ticket-card__header">
                            <h3 class="sws-ticket-card__title"><?php echo esc_html( $booking->event_title ); ?></h3>
                            <?php if ( $booking->is_guest_ticket ) : ?>
                                <span class="sws-ticket-card__badge sws-ticket-card__badge--guest"><?php esc_html_e( 'Guest Ticket', 'sws-members-club' ); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="sws-ticket-card__details">
                            <div class="sws-ticket-card__date">
                                <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $booking->event_date ) ) ); ?>
                            </div>
                            <div class="sws-ticket-card__time">
                                <?php echo esc_html( date_i18n( 'g:i A', strtotime( $booking->event_time_start ) ) ); ?>
                                &ndash;
                                <?php echo esc_html( date_i18n( 'g:i A', strtotime( $booking->event_time_end ) ) ); ?>
                            </div>
                            <?php if ( $booking->venue_name ) : ?>
                                <div class="sws-ticket-card__venue"><?php echo esc_html( $booking->venue_name ); ?></div>
                            <?php endif; ?>
                            <?php if ( $booking->guest_name ) : ?>
                                <div class="sws-ticket-card__guest">
                                    <strong><?php esc_html_e( 'Guest:', 'sws-members-club' ); ?></strong>
                                    <?php echo esc_html( $booking->guest_name ); ?>
                                </div>
                            <?php endif; ?>
                            <div class="sws-ticket-card__price">
                                <?php if ( (float) $booking->amount_paid > 0 ) : ?>
                                    &pound;<?php echo esc_html( number_format( (float) $booking->amount_paid, 2 ) ); ?>
                                <?php elseif ( $events_included ) : ?>
                                    <?php esc_html_e( 'Included with membership', 'sws-members-club' ); ?>
                                <?php else : ?>
                                    <?php esc_html_e( 'Free', 'sws-members-club' ); ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="sws-ticket-card__actions">
                            <!-- Calendar dropdown -->
                            <div class="sws-ticket-card__calendar-dropdown">
                                <button type="button" class="sws-ticket-card__calendar-button">
                                    <?php esc_html_e( 'Add to Calendar', 'sws-members-club' ); ?>
                                </button>
                                <div class="sws-ticket-card__calendar-menu">
                                    <a href="<?php echo esc_url( $gcal_url ); ?>" target="_blank" rel="noopener">
                                        <?php esc_html_e( 'Google Calendar', 'sws-members-club' ); ?>
                                    </a>
                                    <a href="<?php echo esc_url( rest_url( 'sws/v1/calendar/' . $booking->id . '.ics' ) ); ?>" download>
                                        <?php esc_html_e( 'Outlook / Apple Calendar (.ics)', 'sws-members-club' ); ?>
                                    </a>
                                </div>
                            </div>

                            <!-- Cancel button -->
                            <?php if ( $can_cancel ) : ?>
                                <button type="button"
                                        class="sws-ticket-card__cancel-button"
                                        data-booking-id="<?php echo esc_attr( $booking->id ); ?>"
                                        data-event-title="<?php echo esc_attr( $booking->event_title ); ?>"
                                        data-event-date="<?php echo esc_attr( date_i18n( get_option( 'date_format' ), strtotime( $booking->event_date ) ) ); ?>"
                                        data-amount="<?php echo esc_attr( $booking->amount_paid ); ?>">
                                    <?php esc_html_e( 'Cancel Ticket', 'sws-members-club' ); ?>
                                </button>
                            <?php else : ?>
                                <span class="sws-ticket-card__cutoff-notice">
                                    <?php esc_html_e( 'Cancellation window has closed', 'sws-members-club' ); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Past tab -->
    <div class="sws-my-tickets__panel" data-panel="past" style="display: none;">
        <?php if ( empty( $past ) ) : ?>
            <p class="sws-my-tickets__empty"><?php esc_html_e( 'No past events.', 'sws-members-club' ); ?></p>
        <?php else : ?>
            <div class="sws-my-tickets__list">
                <?php foreach ( $past as $booking ) : ?>
                    <div class="sws-ticket-card sws-ticket-card--past">
                        <div class="sws-ticket-card__header">
                            <h3 class="sws-ticket-card__title"><?php echo esc_html( $booking->event_title ); ?></h3>
                        </div>
                        <div class="sws-ticket-card__details">
                            <div class="sws-ticket-card__date">
                                <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $booking->event_date ) ) ); ?>
                            </div>
                            <?php if ( $booking->venue_name ) : ?>
                                <div class="sws-ticket-card__venue"><?php echo esc_html( $booking->venue_name ); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
