<?php
/**
 * Admin template: CSV Import.
 *
 * Variables available: $import_result (from transient, may be null)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Import Members', 'sws-members-club' ); ?></h1>

    <?php if ( $import_result ) : ?>
        <div class="notice <?php echo empty( $import_result['errors'] ) ? 'notice-success' : 'notice-warning'; ?> is-dismissible">
            <p>
                <?php
                printf(
                    /* translators: %1$d: imported count, %2$d: skipped count */
                    esc_html__( 'Import complete. %1$d imported, %2$d skipped.', 'sws-members-club' ),
                    $import_result['imported'],
                    $import_result['skipped']
                );
                ?>
            </p>
            <?php if ( ! empty( $import_result['errors'] ) ) : ?>
                <details>
                    <summary><?php esc_html_e( 'View errors', 'sws-members-club' ); ?></summary>
                    <ul>
                        <?php foreach ( $import_result['errors'] as $error ) : ?>
                            <li><?php echo esc_html( $error ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="card" style="max-width: 600px;">
        <h2><?php esc_html_e( 'Upload CSV File', 'sws-members-club' ); ?></h2>

        <p><?php esc_html_e( 'Upload a CSV file with member data. The file must include a header row.', 'sws-members-club' ); ?></p>

        <h4><?php esc_html_e( 'Required columns:', 'sws-members-club' ); ?></h4>
        <ul>
            <li><code>email</code> — <?php esc_html_e( 'Member email address (required)', 'sws-members-club' ); ?></li>
        </ul>

        <h4><?php esc_html_e( 'Optional columns:', 'sws-members-club' ); ?></h4>
        <ul>
            <li><code>name</code> — <?php esc_html_e( 'Full name', 'sws-members-club' ); ?></li>
            <li><code>stripe_customer_id</code> — <?php esc_html_e( 'Stripe customer ID (e.g. cus_xxx)', 'sws-members-club' ); ?></li>
            <li><code>billing_cycle</code> — <?php esc_html_e( 'monthly, quarterly, or annual', 'sws-members-club' ); ?></li>
            <li><code>tier_slug</code> — <?php esc_html_e( 'Membership tier slug (e.g. standard, premium)', 'sws-members-club' ); ?></li>
            <li><code>start_date</code> — <?php esc_html_e( 'Membership start date (YYYY-MM-DD)', 'sws-members-club' ); ?></li>
        </ul>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
            <input type="hidden" name="action" value="sws_import_members">
            <?php wp_nonce_field( 'sws_import_members', 'sws_nonce' ); ?>

            <p>
                <input type="file" name="csv_file" accept=".csv" required>
            </p>

            <?php submit_button( __( 'Import Members', 'sws-members-club' ) ); ?>
        </form>
    </div>
</div>
