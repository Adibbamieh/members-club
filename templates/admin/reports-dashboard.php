<?php
/**
 * Admin template: Reporting dashboard.
 *
 * Variables: $active_report, $overview, $tiers, (and report-specific data)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Reports', 'sws-members-club' ); ?></h1>

    <h2 class="nav-tab-wrapper">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=sws-reports&report=overview' ) ); ?>" class="nav-tab <?php echo $active_report === 'overview' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Overview', 'sws-members-club' ); ?></a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=sws-reports&report=attendance' ) ); ?>" class="nav-tab <?php echo $active_report === 'attendance' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Attendance', 'sws-members-club' ); ?></a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=sws-reports&report=cancellations' ) ); ?>" class="nav-tab <?php echo $active_report === 'cancellations' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Cancellations', 'sws-members-club' ); ?></a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=sws-reports&report=waitlist' ) ); ?>" class="nav-tab <?php echo $active_report === 'waitlist' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Waitlist', 'sws-members-club' ); ?></a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=sws-reports&report=revenue' ) ); ?>" class="nav-tab <?php echo $active_report === 'revenue' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Revenue', 'sws-members-club' ); ?></a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=sws-reports&report=engagement' ) ); ?>" class="nav-tab <?php echo $active_report === 'engagement' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Engagement', 'sws-members-club' ); ?></a>
    </h2>

    <?php if ( $active_report === 'overview' ) : ?>

        <div class="sws-report-cards">
            <div class="sws-report-card">
                <div class="sws-report-card__value"><?php echo esc_html( $overview->total_active ); ?></div>
                <div class="sws-report-card__label"><?php esc_html_e( 'Active Members', 'sws-members-club' ); ?></div>
                <?php if ( ! empty( $overview->members_by_tier ) ) : ?>
                    <div class="sws-report-card__detail">
                        <?php foreach ( $overview->members_by_tier as $tier ) : ?>
                            <?php echo esc_html( $tier->tier_name ); ?>: <?php echo esc_html( $tier->count ); ?><br>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="sws-report-card">
                <div class="sws-report-card__value"><?php echo esc_html( $overview->events_this_month ); ?></div>
                <div class="sws-report-card__label"><?php esc_html_e( 'Events This Month', 'sws-members-club' ); ?></div>
            </div>
            <div class="sws-report-card">
                <div class="sws-report-card__value">&pound;<?php echo esc_html( number_format( $overview->revenue_this_month, 2 ) ); ?></div>
                <div class="sws-report-card__label"><?php esc_html_e( 'Revenue This Month', 'sws-members-club' ); ?></div>
            </div>
            <div class="sws-report-card">
                <div class="sws-report-card__value"><?php echo esc_html( $overview->avg_attendance ?? 0 ); ?>%</div>
                <div class="sws-report-card__label"><?php esc_html_e( 'Avg. Attendance Rate', 'sws-members-club' ); ?></div>
            </div>
        </div>

    <?php elseif ( $active_report === 'attendance' ) : ?>

        <p>
            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sws_export_csv&report=attendance' ), 'sws_export_csv' ) ); ?>" class="button"><?php esc_html_e( 'Export CSV', 'sws-members-club' ); ?></a>

            <select onchange="location = this.value;" style="margin-left: 8px;">
                <option value="<?php echo esc_url( admin_url( 'admin.php?page=sws-reports&report=attendance' ) ); ?>"><?php esc_html_e( 'All tiers', 'sws-members-club' ); ?></option>
                <?php foreach ( $tiers as $tier ) : ?>
                    <option value="<?php echo esc_url( admin_url( 'admin.php?page=sws-reports&report=attendance&tier_id=' . $tier->id ) ); ?>" <?php selected( isset( $_GET['tier_id'] ) && (int) $_GET['tier_id'] === (int) $tier->id ); ?>>
                        <?php echo esc_html( $tier->name ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Event', 'sws-members-club' ); ?></th>
                    <th><?php esc_html_e( 'Date', 'sws-members-club' ); ?></th>
                    <th><?php esc_html_e( 'Capacity', 'sws-members-club' ); ?></th>
                    <th><?php esc_html_e( 'Bookings', 'sws-members-club' ); ?></th>
                    <th><?php esc_html_e( 'Cancellations', 'sws-members-club' ); ?></th>
                    <th><?php esc_html_e( 'No-Shows', 'sws-members-club' ); ?></th>
                    <th><?php esc_html_e( 'Attendance Rate', 'sws-members-club' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $attendance_data as $row ) : ?>
                    <tr>
                        <td><?php echo esc_html( $row->title ); ?></td>
                        <td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $row->event_date ) ) ); ?></td>
                        <td><?php echo esc_html( $row->capacity ); ?></td>
                        <td><?php echo esc_html( $row->bookings ); ?></td>
                        <td><?php echo esc_html( $row->cancellations ); ?></td>
                        <td><?php echo esc_html( $row->no_shows ); ?></td>
                        <td><?php echo esc_html( $row->attendance_rate ); ?>%</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    <?php elseif ( $active_report === 'cancellations' ) : ?>

        <p><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sws_export_csv&report=cancellations' ), 'sws_export_csv' ) ); ?>" class="button"><?php esc_html_e( 'Export CSV', 'sws-members-club' ); ?></a></p>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Month', 'sws-members-club' ); ?></th>
                    <th><?php esc_html_e( 'Total Cancellations', 'sws-members-club' ); ?></th>
                    <th><?php esc_html_e( 'Refund Total', 'sws-members-club' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $cancellation_data as $row ) : ?>
                    <tr>
                        <td><?php echo esc_html( $row->month ); ?></td>
                        <td><?php echo esc_html( $row->total_cancellations ); ?></td>
                        <td>&pound;<?php echo esc_html( number_format( (float) $row->refund_total, 2 ) ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    <?php elseif ( $active_report === 'waitlist' ) : ?>

        <div class="sws-report-cards">
            <div class="sws-report-card">
                <div class="sws-report-card__value"><?php echo esc_html( $waitlist_data->total_offered ); ?></div>
                <div class="sws-report-card__label"><?php esc_html_e( 'Total Offered', 'sws-members-club' ); ?></div>
            </div>
            <div class="sws-report-card">
                <div class="sws-report-card__value"><?php echo esc_html( $waitlist_data->total_claimed ); ?></div>
                <div class="sws-report-card__label"><?php esc_html_e( 'Total Claimed', 'sws-members-club' ); ?></div>
            </div>
            <div class="sws-report-card">
                <div class="sws-report-card__value"><?php echo esc_html( $waitlist_data->conversion_rate ); ?>%</div>
                <div class="sws-report-card__label"><?php esc_html_e( 'Conversion Rate', 'sws-members-club' ); ?></div>
            </div>
        </div>

    <?php elseif ( $active_report === 'revenue' ) : ?>

        <p><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sws_export_csv&report=revenue' ), 'sws_export_csv' ) ); ?>" class="button"><?php esc_html_e( 'Export CSV', 'sws-members-club' ); ?></a></p>

        <h3><?php esc_html_e( 'Revenue by Month', 'sws-members-club' ); ?></h3>
        <table class="wp-list-table widefat fixed striped">
            <thead><tr><th><?php esc_html_e( 'Month', 'sws-members-club' ); ?></th><th><?php esc_html_e( 'Revenue', 'sws-members-club' ); ?></th></tr></thead>
            <tbody>
                <?php foreach ( $revenue_data['by_month'] as $row ) : ?>
                    <tr><td><?php echo esc_html( $row->month ); ?></td><td>&pound;<?php echo esc_html( number_format( (float) $row->revenue, 2 ) ); ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h3><?php esc_html_e( 'Revenue by Tier', 'sws-members-club' ); ?></h3>
        <table class="wp-list-table widefat fixed striped">
            <thead><tr><th><?php esc_html_e( 'Tier', 'sws-members-club' ); ?></th><th><?php esc_html_e( 'Tickets', 'sws-members-club' ); ?></th><th><?php esc_html_e( 'Revenue', 'sws-members-club' ); ?></th></tr></thead>
            <tbody>
                <?php foreach ( $revenue_data['by_tier'] as $row ) : ?>
                    <tr><td><?php echo esc_html( $row->tier_name ); ?></td><td><?php echo esc_html( $row->ticket_count ); ?></td><td>&pound;<?php echo esc_html( number_format( (float) $row->revenue, 2 ) ); ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h3><?php esc_html_e( 'Top Events by Revenue', 'sws-members-club' ); ?></h3>
        <table class="wp-list-table widefat fixed striped">
            <thead><tr><th><?php esc_html_e( 'Event', 'sws-members-club' ); ?></th><th><?php esc_html_e( 'Date', 'sws-members-club' ); ?></th><th><?php esc_html_e( 'Tickets', 'sws-members-club' ); ?></th><th><?php esc_html_e( 'Revenue', 'sws-members-club' ); ?></th></tr></thead>
            <tbody>
                <?php foreach ( $revenue_data['by_event'] as $row ) : ?>
                    <tr><td><?php echo esc_html( $row->title ); ?></td><td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $row->event_date ) ) ); ?></td><td><?php echo esc_html( $row->tickets ); ?></td><td>&pound;<?php echo esc_html( number_format( (float) $row->revenue, 2 ) ); ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    <?php elseif ( $active_report === 'engagement' ) : ?>

        <p><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sws_export_csv&report=engagement' ), 'sws_export_csv' ) ); ?>" class="button"><?php esc_html_e( 'Export CSV', 'sws-members-club' ); ?></a></p>

        <h3><?php esc_html_e( 'Most Active Members', 'sws-members-club' ); ?></h3>
        <table class="wp-list-table widefat fixed striped">
            <thead><tr><th><?php esc_html_e( 'Name', 'sws-members-club' ); ?></th><th><?php esc_html_e( 'Email', 'sws-members-club' ); ?></th><th><?php esc_html_e( 'Tier', 'sws-members-club' ); ?></th><th><?php esc_html_e( 'Bookings', 'sws-members-club' ); ?></th></tr></thead>
            <tbody>
                <?php foreach ( $engagement_data['most_active'] as $row ) : ?>
                    <tr><td><?php echo esc_html( $row->display_name ); ?></td><td><?php echo esc_html( $row->user_email ); ?></td><td><?php echo esc_html( $row->tier_name ); ?></td><td><?php echo esc_html( $row->booking_count ); ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h3><?php esc_html_e( 'Members with Strikes', 'sws-members-club' ); ?></h3>
        <table class="wp-list-table widefat fixed striped">
            <thead><tr><th><?php esc_html_e( 'Name', 'sws-members-club' ); ?></th><th><?php esc_html_e( 'Email', 'sws-members-club' ); ?></th><th><?php esc_html_e( 'Tier', 'sws-members-club' ); ?></th><th><?php esc_html_e( 'Strikes', 'sws-members-club' ); ?></th><th><?php esc_html_e( 'Status', 'sws-members-club' ); ?></th></tr></thead>
            <tbody>
                <?php if ( empty( $engagement_data['with_strikes'] ) ) : ?>
                    <tr><td colspan="5"><?php esc_html_e( 'No members with strikes.', 'sws-members-club' ); ?></td></tr>
                <?php else : ?>
                    <?php foreach ( $engagement_data['with_strikes'] as $row ) : ?>
                        <tr><td><?php echo esc_html( $row->display_name ); ?></td><td><?php echo esc_html( $row->user_email ); ?></td><td><?php echo esc_html( $row->tier_name ); ?></td><td><?php echo esc_html( $row->penalty_strikes ); ?></td><td><span class="sws-status sws-status--<?php echo esc_attr( $row->membership_status ); ?>"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $row->membership_status ) ) ); ?></span></td></tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <h3><?php esc_html_e( 'Lapsed Members', 'sws-members-club' ); ?></h3>
        <table class="wp-list-table widefat fixed striped">
            <thead><tr><th><?php esc_html_e( 'Name', 'sws-members-club' ); ?></th><th><?php esc_html_e( 'Email', 'sws-members-club' ); ?></th><th><?php esc_html_e( 'Tier', 'sws-members-club' ); ?></th></tr></thead>
            <tbody>
                <?php if ( empty( $engagement_data['lapsed'] ) ) : ?>
                    <tr><td colspan="3"><?php esc_html_e( 'No lapsed members.', 'sws-members-club' ); ?></td></tr>
                <?php else : ?>
                    <?php foreach ( $engagement_data['lapsed'] as $row ) : ?>
                        <tr><td><?php echo esc_html( $row->display_name ); ?></td><td><?php echo esc_html( $row->user_email ); ?></td><td><?php echo esc_html( $row->tier_name ); ?></td></tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

    <?php endif; ?>
</div>
