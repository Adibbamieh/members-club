<?php
/**
 * Admin template: Member detail/edit.
 *
 * Variables available: $member, $membership, $user, $tiers, $penalties
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
    <h1>
        <?php echo esc_html( $user->display_name ); ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=sws-members' ) ); ?>" class="page-title-action"><?php esc_html_e( '&larr; Back to Members', 'sws-members-club' ); ?></a>
    </h1>

    <?php if ( isset( $_GET['updated'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Member updated.', 'sws-members-club' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['strikes_reset'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Penalty strikes reset.', 'sws-members-club' ); ?></p></div>
    <?php endif; ?>

    <div id="poststuff">
        <div id="post-body" class="metabox-holder columns-2">

            <!-- Main content -->
            <div id="post-body-content">
                <div class="postbox">
                    <h2 class="hndle"><?php esc_html_e( 'Membership', 'sws-members-club' ); ?></h2>
                    <div class="inside">
                        <p class="description" style="margin: 8px 0 12px;">
                            <?php esc_html_e( 'Membership status, tier and billing are managed in WooCommerce Subscriptions. This page is read-only for those fields.', 'sws-members-club' ); ?>
                        </p>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Email', 'sws-members-club' ); ?></th>
                                <td><?php echo esc_html( $user->user_email ); ?></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Membership Status', 'sws-members-club' ); ?></th>
                                <td>
                                    <?php if ( $membership->is_active ) : ?>
                                        <span class="sws-status sws-status--active"><?php esc_html_e( 'Active', 'sws-members-club' ); ?></span>
                                    <?php else : ?>
                                        <span class="sws-status sws-status--lapsed"><?php esc_html_e( 'No active subscription', 'sws-members-club' ); ?></span>
                                    <?php endif; ?>
                                    <?php if ( $membership->restricted ) : ?>
                                        <span class="sws-status sws-status--waitlist_only"><?php esc_html_e( 'Waitlist-only (penalty)', 'sws-members-club' ); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Membership Tier', 'sws-members-club' ); ?></th>
                                <td>
                                    <?php if ( $membership->tier_name ) : ?>
                                        <?php echo esc_html( $membership->tier_name ); ?>
                                        <?php if ( $membership->events_included ) : ?>
                                            <em>(<?php esc_html_e( 'events included', 'sws-members-club' ); ?>)</em>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <em><?php esc_html_e( 'Not resolved from WooCommerce', 'sws-members-club' ); ?></em>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'WooCommerce', 'sws-members-club' ); ?></th>
                                <td>
                                    <?php if ( class_exists( 'SWS_Woo' ) && SWS_Woo::is_available() ) : ?>
                                        <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=shop_subscription' ) ); ?>"><?php esc_html_e( 'View subscriptions', 'sws-members-club' ); ?></a>
                                    <?php else : ?>
                                        <em><?php esc_html_e( 'WooCommerce Subscriptions not detected (using legacy local status).', 'sws-members-club' ); ?></em>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div id="postbox-container-1" class="postbox-container">
                <!-- Penalty Strikes -->
                <div class="postbox">
                    <h2 class="hndle"><?php esc_html_e( 'Penalty Strikes', 'sws-members-club' ); ?></h2>
                    <div class="inside">
                        <p class="sws-strike-count">
                            <strong><?php echo esc_html( $member->penalty_strikes ); ?></strong>
                            / <?php echo esc_html( get_option( 'sws_penalty_max_strikes', 3 ) ); ?>
                        </p>

                        <?php if ( $member->penalty_strikes > 0 ) : ?>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                <input type="hidden" name="action" value="sws_reset_strikes">
                                <input type="hidden" name="user_id" value="<?php echo esc_attr( $member->user_id ); ?>">
                                <?php wp_nonce_field( 'sws_reset_strikes', 'sws_nonce' ); ?>

                                <?php if ( $member->membership_status === 'waitlist_only' ) : ?>
                                    <p>
                                        <label>
                                            <input type="checkbox" name="new_status" value="active">
                                            <?php esc_html_e( 'Also restore to Active status', 'sws-members-club' ); ?>
                                        </label>
                                    </p>
                                <?php endif; ?>

                                <?php submit_button( __( 'Reset Strikes', 'sws-members-club' ), 'secondary', 'submit', false ); ?>
                            </form>
                        <?php endif; ?>

                        <?php if ( ! empty( $penalties ) ) : ?>
                            <h4><?php esc_html_e( 'Strike History', 'sws-members-club' ); ?></h4>
                            <ul class="sws-penalty-history">
                                <?php foreach ( $penalties as $penalty ) : ?>
                                    <li>
                                        <strong><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $penalty->created_at ) ) ); ?></strong>
                                        — <?php echo esc_html( ucfirst( str_replace( '_', ' ', $penalty->reason ) ) ); ?>
                                        <?php if ( $penalty->event_title ) : ?>
                                            (<?php echo esc_html( $penalty->event_title ); ?>)
                                        <?php endif; ?>
                                        <?php if ( $penalty->admin_note ) : ?>
                                            <br><small><?php echo esc_html( $penalty->admin_note ); ?></small>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
