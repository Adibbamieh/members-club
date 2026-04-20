<?php
/**
 * Frontend template: Booking form.
 *
 * Override by copying to: {theme}/sws-members-club/booking-form.php
 *
 * Variables: $event, $member, $events_included, $spots_remaining,
 *            $already_booked, $needs_payment, $policy_url
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="sws-booking" data-event-id="<?php echo esc_attr( $event->id ); ?>">
    <div class="sws-booking__event-details">
        <h2 class="sws-booking__title"><?php echo esc_html( $event->title ); ?></h2>

        <div class="sws-booking__meta">
            <div class="sws-booking__meta-item sws-booking__date">
                <strong><?php esc_html_e( 'Date:', 'sws-members-club' ); ?></strong>
                <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $event->event_date ) ) ); ?>
            </div>
            <div class="sws-booking__meta-item sws-booking__time">
                <strong><?php esc_html_e( 'Time:', 'sws-members-club' ); ?></strong>
                <?php echo esc_html( date_i18n( 'g:i A', strtotime( $event->event_time_start ) ) ); ?>
                &ndash;
                <?php echo esc_html( date_i18n( 'g:i A', strtotime( $event->event_time_end ) ) ); ?>
            </div>
            <?php if ( $event->venue_name ) : ?>
                <div class="sws-booking__meta-item sws-booking__venue">
                    <strong><?php esc_html_e( 'Venue:', 'sws-members-club' ); ?></strong>
                    <?php echo esc_html( $event->venue_name ); ?>
                    <?php if ( $event->venue_address ) : ?>
                        <br><span class="sws-booking__address"><?php echo esc_html( $event->venue_address ); ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ( $event->description ) : ?>
            <div class="sws-booking__description">
                <?php echo wp_kses_post( $event->description ); ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ( $already_booked ) : ?>
        <div class="sws-booking__notice sws-booking__notice--info">
            <p><?php esc_html_e( 'You already have a booking for this event.', 'sws-members-club' ); ?></p>
        </div>

    <?php elseif ( $spots_remaining <= 0 && $event->waitlist_enabled ) : ?>
        <div class="sws-booking__waitlist">
            <p class="sws-booking__notice sws-booking__notice--warning">
                <?php esc_html_e( 'This event is full. Join the waitlist to be notified if a spot opens up.', 'sws-members-club' ); ?>
            </p>
            <button type="button" class="sws-booking__waitlist-button" data-event-id="<?php echo esc_attr( $event->id ); ?>">
                <?php esc_html_e( 'Join Waitlist', 'sws-members-club' ); ?>
            </button>
        </div>

    <?php elseif ( $spots_remaining <= 0 ) : ?>
        <div class="sws-booking__notice sws-booking__notice--warning">
            <p><?php esc_html_e( 'This event is fully booked.', 'sws-members-club' ); ?></p>
        </div>

    <?php else : ?>
        <form class="sws-booking__form" id="sws-booking-form" data-event-id="<?php echo esc_attr( $event->id ); ?>" data-needs-payment="<?php echo $needs_payment ? '1' : '0'; ?>">

            <!-- Price display -->
            <div class="sws-booking__price-display">
                <?php if ( $events_included ) : ?>
                    <p class="sws-booking__price-included"><?php esc_html_e( 'Included with your membership', 'sws-members-club' ); ?></p>
                <?php elseif ( (float) $event->ticket_price > 0 ) : ?>
                    <p class="sws-booking__price-amount">
                        <?php
                        printf(
                            /* translators: %s: ticket price */
                            esc_html__( 'Price: %s per ticket', 'sws-members-club' ),
                            '&pound;' . esc_html( number_format( (float) $event->ticket_price, 2 ) )
                        );
                        ?>
                    </p>
                <?php else : ?>
                    <p class="sws-booking__price-free"><?php esc_html_e( 'Free event', 'sws-members-club' ); ?></p>
                <?php endif; ?>
            </div>

            <!-- Guest (+1) toggle -->
            <div class="sws-booking__guest-toggle">
                <label class="sws-booking__checkbox-label">
                    <input type="checkbox" id="sws-include-guest" name="include_guest" value="1">
                    <?php esc_html_e( 'Bring a guest (+1)', 'sws-members-club' ); ?>
                </label>
            </div>

            <!-- Guest fields (hidden by default) -->
            <div class="sws-booking__guest-fields" id="sws-guest-fields" style="display: none;">
                <div class="sws-booking__field">
                    <label for="sws-guest-name"><?php esc_html_e( 'Guest Name', 'sws-members-club' ); ?></label>
                    <input type="text" id="sws-guest-name" name="guest_name" class="sws-booking__input">
                </div>
                <div class="sws-booking__field">
                    <label for="sws-guest-email"><?php esc_html_e( 'Guest Email', 'sws-members-club' ); ?></label>
                    <input type="email" id="sws-guest-email" name="guest_email" class="sws-booking__input">
                </div>
            </div>

            <?php if ( $needs_payment ) : ?>
                <!-- Stripe card element -->
                <div class="sws-booking__payment">
                    <label><?php esc_html_e( 'Payment', 'sws-members-club' ); ?></label>
                    <div id="sws-card-element" class="sws-booking__card-element"></div>
                    <div id="sws-card-errors" class="sws-booking__card-errors" role="alert"></div>
                </div>
            <?php endif; ?>

            <!-- Policy checkbox -->
            <div class="sws-booking__policy">
                <label class="sws-booking__checkbox-label">
                    <input type="checkbox" id="sws-agree-policy" name="agree_policy" required>
                    <?php if ( $policy_url ) : ?>
                        <?php
                        printf(
                            /* translators: %s: link to policy page */
                            esc_html__( 'I agree to the %s', 'sws-members-club' ),
                            '<a href="' . esc_url( $policy_url ) . '" target="_blank">' . esc_html__( 'cancellation and refund policy', 'sws-members-club' ) . '</a>'
                        );
                        ?>
                    <?php else : ?>
                        <?php esc_html_e( 'I agree to the cancellation and refund policy', 'sws-members-club' ); ?>
                    <?php endif; ?>
                </label>
            </div>

            <!-- Submit -->
            <div class="sws-booking__submit">
                <button type="submit" class="sws-booking__button" id="sws-book-button">
                    <?php esc_html_e( 'Book Now', 'sws-members-club' ); ?>
                </button>
            </div>

            <div class="sws-booking__messages" id="sws-booking-messages"></div>
        </form>
    <?php endif; ?>
</div>
