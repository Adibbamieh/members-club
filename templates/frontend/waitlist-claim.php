<?php
/**
 * Frontend template: Waitlist claim page.
 *
 * Override by copying to: {theme}/sws-members-club/waitlist-claim.php
 *
 * Variables: $token
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="sws-waitlist-claim" data-token="<?php echo esc_attr( $token ); ?>">
    <div class="sws-waitlist-claim__loading">
        <p><?php esc_html_e( 'Loading your waitlist offer...', 'sws-members-club' ); ?></p>
    </div>
    <div class="sws-waitlist-claim__content" style="display: none;">
        <!-- Populated by JS -->
    </div>
    <div class="sws-waitlist-claim__messages" id="sws-waitlist-messages"></div>
</div>
