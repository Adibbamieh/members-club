<?php
/**
 * Email template: Waitlist Spot Available.
 *
 * Variables: $member_name, $event_title, $event_date, $event_time,
 *            $venue_name, $venue_address, $claim_url, $hours
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<h2><?php esc_html_e( 'A Spot Has Opened Up!', 'sws-members-club' ); ?></h2>

<p><?php printf( esc_html__( 'Hi %s,', 'sws-members-club' ), esc_html( $member_name ) ); ?></p>

<p><?php printf( esc_html__( 'Great news! A spot has opened up for %s.', 'sws-members-club' ), '<strong>' . esc_html( $event_title ) . '</strong>' ); ?></p>

<dl class="email-detail">
    <dt><?php esc_html_e( 'Event', 'sws-members-club' ); ?></dt>
    <dd><?php echo esc_html( $event_title ); ?></dd>

    <dt><?php esc_html_e( 'Date', 'sws-members-club' ); ?></dt>
    <dd><?php echo esc_html( $event_date ); ?></dd>

    <dt><?php esc_html_e( 'Time', 'sws-members-club' ); ?></dt>
    <dd><?php echo esc_html( $event_time ); ?></dd>

    <?php if ( $venue_name ) : ?>
        <dt><?php esc_html_e( 'Venue', 'sws-members-club' ); ?></dt>
        <dd><?php echo esc_html( $venue_name ); ?></dd>
    <?php endif; ?>
</dl>

<p>
    <?php
    printf(
        /* translators: %d: number of hours */
        esc_html__( 'You have %d hours to claim this spot before it is offered to the next person on the waitlist.', 'sws-members-club' ),
        $hours
    );
    ?>
</p>

<p style="text-align: center; margin: 24px 0;">
    <a href="<?php echo esc_url( $claim_url ); ?>" class="email-cta">
        <?php esc_html_e( 'Claim Your Spot', 'sws-members-club' ); ?>
    </a>
</p>

<p><small><?php esc_html_e( 'If you no longer wish to attend, simply ignore this email and the spot will be offered to the next person.', 'sws-members-club' ); ?></small></p>
