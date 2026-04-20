<?php
/**
 * Admin template: Settings page.
 *
 * Variables available: $active_tab, $tiers
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
    <h1><?php esc_html_e( 'SWS Members Club Settings', 'sws-members-club' ); ?></h1>

    <?php if ( isset( $_GET['saved'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'sws-members-club' ); ?></p></div>
    <?php endif; ?>

    <h2 class="nav-tab-wrapper">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=sws-settings&tab=general' ) ); ?>" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'General', 'sws-members-club' ); ?></a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=sws-settings&tab=stripe' ) ); ?>" class="nav-tab <?php echo $active_tab === 'stripe' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Stripe', 'sws-members-club' ); ?></a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=sws-settings&tab=tiers' ) ); ?>" class="nav-tab <?php echo $active_tab === 'tiers' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Membership Tiers', 'sws-members-club' ); ?></a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=sws-settings&tab=email' ) ); ?>" class="nav-tab <?php echo $active_tab === 'email' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Email', 'sws-members-club' ); ?></a>
    </h2>

    <?php if ( $active_tab === 'general' ) : ?>

        <form method="post" action="options.php">
            <?php settings_fields( 'sws_settings' ); ?>

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="sws_default_cancellation_cutoff"><?php esc_html_e( 'Default Cancellation Cutoff (hours)', 'sws-members-club' ); ?></label></th>
                    <td><input type="number" name="sws_default_cancellation_cutoff" id="sws_default_cancellation_cutoff" value="<?php echo esc_attr( get_option( 'sws_default_cancellation_cutoff', 48 ) ); ?>" min="0" class="small-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="sws_waitlist_claim_hours"><?php esc_html_e( 'Waitlist Claim Window (hours)', 'sws-members-club' ); ?></label></th>
                    <td><input type="number" name="sws_waitlist_claim_hours" id="sws_waitlist_claim_hours" value="<?php echo esc_attr( get_option( 'sws_waitlist_claim_hours', 12 ) ); ?>" min="1" class="small-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="sws_reminder_intervals"><?php esc_html_e( 'Reminder Intervals (hours, comma-separated)', 'sws-members-club' ); ?></label></th>
                    <td><input type="text" name="sws_reminder_intervals" id="sws_reminder_intervals" value="<?php echo esc_attr( get_option( 'sws_reminder_intervals', '48,24,2' ) ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="sws_penalty_max_strikes"><?php esc_html_e( 'Max Penalty Strikes', 'sws-members-club' ); ?></label></th>
                    <td><input type="number" name="sws_penalty_max_strikes" id="sws_penalty_max_strikes" value="<?php echo esc_attr( get_option( 'sws_penalty_max_strikes', 3 ) ); ?>" min="1" class="small-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="sws_policy_page_url"><?php esc_html_e( 'Policy Page URL', 'sws-members-club' ); ?></label></th>
                    <td><input type="url" name="sws_policy_page_url" id="sws_policy_page_url" value="<?php echo esc_attr( get_option( 'sws_policy_page_url', '' ) ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="sws_club_logo"><?php esc_html_e( 'Club Logo URL', 'sws-members-club' ); ?></label></th>
                    <td><input type="url" name="sws_club_logo" id="sws_club_logo" value="<?php echo esc_attr( get_option( 'sws_club_logo', '' ) ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="sws_primary_colour"><?php esc_html_e( 'Primary Colour', 'sws-members-club' ); ?></label></th>
                    <td><input type="color" name="sws_primary_colour" id="sws_primary_colour" value="<?php echo esc_attr( get_option( 'sws_primary_colour', '#000000' ) ); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="sws_secondary_colour"><?php esc_html_e( 'Secondary Colour', 'sws-members-club' ); ?></label></th>
                    <td><input type="color" name="sws_secondary_colour" id="sws_secondary_colour" value="<?php echo esc_attr( get_option( 'sws_secondary_colour', '#333333' ) ); ?>"></td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>

    <?php elseif ( $active_tab === 'stripe' ) : ?>

        <form method="post" action="options.php">
            <?php settings_fields( 'sws_settings' ); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="sws_stripe_test_mode"><?php esc_html_e( 'Test Mode', 'sws-members-club' ); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="sws_stripe_test_mode" id="sws_stripe_test_mode" value="1" <?php checked( get_option( 'sws_stripe_test_mode', 1 ) ); ?>>
                            <?php esc_html_e( 'Enable Stripe test mode', 'sws-members-club' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="sws_stripe_publishable_key"><?php esc_html_e( 'Live Publishable Key', 'sws-members-club' ); ?></label></th>
                    <td><input type="text" name="sws_stripe_publishable_key" id="sws_stripe_publishable_key" value="<?php echo esc_attr( get_option( 'sws_stripe_publishable_key', '' ) ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="sws_stripe_secret_key"><?php esc_html_e( 'Live Secret Key', 'sws-members-club' ); ?></label></th>
                    <td><input type="password" name="sws_stripe_secret_key" id="sws_stripe_secret_key" value="<?php echo esc_attr( get_option( 'sws_stripe_secret_key', '' ) ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="sws_stripe_test_publishable_key"><?php esc_html_e( 'Test Publishable Key', 'sws-members-club' ); ?></label></th>
                    <td><input type="text" name="sws_stripe_test_publishable_key" id="sws_stripe_test_publishable_key" value="<?php echo esc_attr( get_option( 'sws_stripe_test_publishable_key', '' ) ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="sws_stripe_test_secret_key"><?php esc_html_e( 'Test Secret Key', 'sws-members-club' ); ?></label></th>
                    <td><input type="password" name="sws_stripe_test_secret_key" id="sws_stripe_test_secret_key" value="<?php echo esc_attr( get_option( 'sws_stripe_test_secret_key', '' ) ); ?>" class="regular-text"></td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>

    <?php elseif ( $active_tab === 'tiers' ) : ?>

        <h3><?php esc_html_e( 'Membership Tiers', 'sws-members-club' ); ?></h3>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Name', 'sws-members-club' ); ?></th>
                    <th><?php esc_html_e( 'Slug', 'sws-members-club' ); ?></th>
                    <th><?php esc_html_e( 'Events Included', 'sws-members-club' ); ?></th>
                    <th><?php esc_html_e( 'Monthly', 'sws-members-club' ); ?></th>
                    <th><?php esc_html_e( 'Quarterly', 'sws-members-club' ); ?></th>
                    <th><?php esc_html_e( 'Annual', 'sws-members-club' ); ?></th>
                    <th><?php esc_html_e( 'Order', 'sws-members-club' ); ?></th>
                    <th><?php esc_html_e( 'Active', 'sws-members-club' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $tiers as $tier ) : ?>
                    <tr>
                        <td><strong><?php echo esc_html( $tier->name ); ?></strong></td>
                        <td><code><?php echo esc_html( $tier->slug ); ?></code></td>
                        <td><?php echo $tier->events_included ? esc_html__( 'Yes', 'sws-members-club' ) : esc_html__( 'No', 'sws-members-club' ); ?></td>
                        <td>&pound;<?php echo esc_html( number_format( (float) $tier->monthly_price, 2 ) ); ?></td>
                        <td>&pound;<?php echo esc_html( number_format( (float) $tier->quarterly_price, 2 ) ); ?></td>
                        <td>&pound;<?php echo esc_html( number_format( (float) $tier->annual_price, 2 ) ); ?></td>
                        <td><?php echo esc_html( $tier->sort_order ); ?></td>
                        <td><?php echo $tier->is_active ? esc_html__( 'Yes', 'sws-members-club' ) : esc_html__( 'No', 'sws-members-club' ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <hr>
        <h3><?php esc_html_e( 'Add / Edit Tier', 'sws-members-club' ); ?></h3>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="sws_save_tier">
            <input type="hidden" name="tier_id" value="">
            <?php wp_nonce_field( 'sws_save_tier', 'sws_nonce' ); ?>

            <table class="form-table">
                <tr>
                    <th><label for="tier_name"><?php esc_html_e( 'Name', 'sws-members-club' ); ?></label></th>
                    <td><input type="text" name="name" id="tier_name" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="tier_slug"><?php esc_html_e( 'Slug', 'sws-members-club' ); ?></label></th>
                    <td><input type="text" name="slug" id="tier_slug" class="regular-text"></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Events Included', 'sws-members-club' ); ?></th>
                    <td><label><input type="checkbox" name="events_included" value="1"> <?php esc_html_e( 'Members on this tier attend all events free of charge', 'sws-members-club' ); ?></label></td>
                </tr>
                <tr>
                    <th><label for="tier_monthly"><?php esc_html_e( 'Monthly Price', 'sws-members-club' ); ?></label></th>
                    <td><input type="number" name="monthly_price" id="tier_monthly" step="0.01" min="0" value="0.00" class="small-text"></td>
                </tr>
                <tr>
                    <th><label for="tier_quarterly"><?php esc_html_e( 'Quarterly Price', 'sws-members-club' ); ?></label></th>
                    <td><input type="number" name="quarterly_price" id="tier_quarterly" step="0.01" min="0" value="0.00" class="small-text"></td>
                </tr>
                <tr>
                    <th><label for="tier_annual"><?php esc_html_e( 'Annual Price', 'sws-members-club' ); ?></label></th>
                    <td><input type="number" name="annual_price" id="tier_annual" step="0.01" min="0" value="0.00" class="small-text"></td>
                </tr>
                <tr>
                    <th><label for="tier_order"><?php esc_html_e( 'Sort Order', 'sws-members-club' ); ?></label></th>
                    <td><input type="number" name="sort_order" id="tier_order" min="0" value="0" class="small-text"></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Active', 'sws-members-club' ); ?></th>
                    <td><label><input type="checkbox" name="is_active" value="1" checked> <?php esc_html_e( 'Tier is active and available for new members', 'sws-members-club' ); ?></label></td>
                </tr>
            </table>

            <?php submit_button( __( 'Save Tier', 'sws-members-club' ) ); ?>
        </form>

    <?php elseif ( $active_tab === 'email' ) : ?>

        <form method="post" action="options.php">
            <?php settings_fields( 'sws_settings' ); ?>

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="sws_email_from_name"><?php esc_html_e( 'Sender Name', 'sws-members-club' ); ?></label></th>
                    <td><input type="text" name="sws_email_from_name" id="sws_email_from_name" value="<?php echo esc_attr( get_option( 'sws_email_from_name', get_bloginfo( 'name' ) ) ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="sws_email_from_address"><?php esc_html_e( 'Sender Email', 'sws-members-club' ); ?></label></th>
                    <td><input type="email" name="sws_email_from_address" id="sws_email_from_address" value="<?php echo esc_attr( get_option( 'sws_email_from_address', get_bloginfo( 'admin_email' ) ) ); ?>" class="regular-text"></td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>

    <?php endif; ?>
</div>
