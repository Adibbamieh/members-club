<?php
/**
 * Admin template: Members list.
 *
 * Variables available: $members, $total, $tiers, $total_pages,
 *                      $current_page, $search, $filter_tier, $filter_status
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'Members', 'sws-members-club' ); ?></h1>
    <span class="title-count"><?php echo esc_html( $total ); ?></span>
    <hr class="wp-header-end">

    <?php if ( isset( $_GET['updated'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Member updated.', 'sws-members-club' ); ?></p></div>
    <?php endif; ?>

    <form method="get" class="sws-filters">
        <input type="hidden" name="page" value="sws-members">

        <p class="search-box">
            <label class="screen-reader-text" for="sws-search"><?php esc_html_e( 'Search members', 'sws-members-club' ); ?></label>
            <input type="search" id="sws-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search by name or email...', 'sws-members-club' ); ?>">
        </p>

        <select name="tier_id">
            <option value=""><?php esc_html_e( 'All tiers', 'sws-members-club' ); ?></option>
            <?php foreach ( $tiers as $tier ) : ?>
                <option value="<?php echo esc_attr( $tier->id ); ?>" <?php selected( $filter_tier, $tier->id ); ?>><?php echo esc_html( $tier->name ); ?></option>
            <?php endforeach; ?>
        </select>

        <select name="status">
            <option value=""><?php esc_html_e( 'All statuses', 'sws-members-club' ); ?></option>
            <option value="active" <?php selected( $filter_status, 'active' ); ?>><?php esc_html_e( 'Active', 'sws-members-club' ); ?></option>
            <option value="lapsed" <?php selected( $filter_status, 'lapsed' ); ?>><?php esc_html_e( 'Lapsed', 'sws-members-club' ); ?></option>
            <option value="suspended" <?php selected( $filter_status, 'suspended' ); ?>><?php esc_html_e( 'Suspended', 'sws-members-club' ); ?></option>
            <option value="waitlist_only" <?php selected( $filter_status, 'waitlist_only' ); ?>><?php esc_html_e( 'Waitlist Only', 'sws-members-club' ); ?></option>
        </select>

        <?php submit_button( __( 'Filter', 'sws-members-club' ), 'secondary', 'filter', false ); ?>
    </form>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col"><?php esc_html_e( 'Name', 'sws-members-club' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Email', 'sws-members-club' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Tier', 'sws-members-club' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Status', 'sws-members-club' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Billing', 'sws-members-club' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Strikes', 'sws-members-club' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Joined', 'sws-members-club' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $members ) ) : ?>
                <tr>
                    <td colspan="7"><?php esc_html_e( 'No members found.', 'sws-members-club' ); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ( $members as $member ) : ?>
                    <tr>
                        <td>
                            <strong>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=sws-members&action=view&user_id=' . $member->user_id ) ); ?>">
                                    <?php echo esc_html( $member->display_name ); ?>
                                </a>
                            </strong>
                        </td>
                        <td><?php echo esc_html( $member->user_email ); ?></td>
                        <td><?php echo esc_html( $member->tier_name ); ?></td>
                        <td>
                            <span class="sws-status sws-status--<?php echo esc_attr( $member->membership_status ); ?>">
                                <?php echo esc_html( ucfirst( str_replace( '_', ' ', $member->membership_status ) ) ); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html( ucfirst( $member->billing_cycle ) ); ?></td>
                        <td><?php echo esc_html( $member->penalty_strikes ); ?></td>
                        <td><?php echo esc_html( $member->membership_start_date ? date_i18n( get_option( 'date_format' ), strtotime( $member->membership_start_date ) ) : '—' ); ?></td>
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
