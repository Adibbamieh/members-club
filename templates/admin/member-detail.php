<?php
/**
 * Admin template: Member detail/edit.
 *
 * Variables available: $member, $user, $tiers, $penalties
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
                    <h2 class="hndle"><?php esc_html_e( 'Member Details', 'sws-members-club' ); ?></h2>
                    <div class="inside">
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <input type="hidden" name="action" value="sws_save_member">
                            <input type="hidden" name="user_id" value="<?php echo esc_attr( $member->user_id ); ?>">
                            <?php wp_nonce_field( 'sws_save_member', 'sws_nonce' ); ?>

                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e( 'Email', 'sws-members-club' ); ?></th>
                                    <td><?php echo esc_html( $user->user_email ); ?></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="membership_tier_id"><?php esc_html_e( 'Membership Tier', 'sws-members-club' ); ?></label></th>
                                    <td>
                                        <select name="membership_tier_id" id="membership_tier_id">
                                            <?php foreach ( $tiers as $tier ) : ?>
                                                <option value="<?php echo esc_attr( $tier->id ); ?>" <?php selected( $member->membership_tier_id, $tier->id ); ?>>
                                                    <?php echo esc_html( $tier->name ); ?>
                                                    <?php if ( ! $tier->is_active ) echo ' (' . esc_html__( 'inactive', 'sws-members-club' ) . ')'; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="membership_status"><?php esc_html_e( 'Status', 'sws-members-club' ); ?></label></th>
                                    <td>
                                        <select name="membership_status" id="membership_status">
                                            <option value="active" <?php selected( $member->membership_status, 'active' ); ?>><?php esc_html_e( 'Active', 'sws-members-club' ); ?></option>
                                            <option value="lapsed" <?php selected( $member->membership_status, 'lapsed' ); ?>><?php esc_html_e( 'Lapsed', 'sws-members-club' ); ?></option>
                                            <option value="suspended" <?php selected( $member->membership_status, 'suspended' ); ?>><?php esc_html_e( 'Suspended', 'sws-members-club' ); ?></option>
                                            <option value="waitlist_only" <?php selected( $member->membership_status, 'waitlist_only' ); ?>><?php esc_html_e( 'Waitlist Only', 'sws-members-club' ); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="billing_cycle"><?php esc_html_e( 'Billing Cycle', 'sws-members-club' ); ?></label></th>
                                    <td>
                                        <select name="billing_cycle" id="billing_cycle">
                                            <option value="monthly" <?php selected( $member->billing_cycle, 'monthly' ); ?>><?php esc_html_e( 'Monthly', 'sws-members-club' ); ?></option>
                                            <option value="quarterly" <?php selected( $member->billing_cycle, 'quarterly' ); ?>><?php esc_html_e( 'Quarterly', 'sws-members-club' ); ?></option>
                                            <option value="annual" <?php selected( $member->billing_cycle, 'annual' ); ?>><?php esc_html_e( 'Annual', 'sws-members-club' ); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e( 'Stripe Customer', 'sws-members-club' ); ?></th>
                                    <td>
                                        <?php if ( $member->stripe_customer_id ) : ?>
                                            <code><?php echo esc_html( $member->stripe_customer_id ); ?></code>
                                        <?php else : ?>
                                            <em><?php esc_html_e( 'Not linked', 'sws-members-club' ); ?></em>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e( 'Member Since', 'sws-members-club' ); ?></th>
                                    <td><?php echo esc_html( $member->membership_start_date ? date_i18n( get_option( 'date_format' ), strtotime( $member->membership_start_date ) ) : '—' ); ?></td>
                                </tr>
                            </table>

                            <?php submit_button( __( 'Save Changes', 'sws-members-club' ) ); ?>
                        </form>
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
