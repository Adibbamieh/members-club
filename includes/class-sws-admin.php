<?php
/**
 * Admin pages, settings, and UI.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SWS_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menus' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_post_sws_import_members', array( $this, 'handle_csv_import' ) );
        add_action( 'admin_post_sws_save_member', array( $this, 'handle_save_member' ) );
        add_action( 'admin_post_sws_save_tier', array( $this, 'handle_save_tier' ) );
        add_action( 'admin_post_sws_reset_strikes', array( $this, 'handle_reset_strikes' ) );
    }

    /**
     * Register admin menu pages.
     */
    public function register_menus() {
        add_menu_page(
            __( 'SWS Members Club', 'sws-members-club' ),
            __( 'SWS Club', 'sws-members-club' ),
            'manage_options',
            'sws-members',
            array( $this, 'render_members_page' ),
            'dashicons-groups',
            30
        );

        add_submenu_page(
            'sws-members',
            __( 'Members', 'sws-members-club' ),
            __( 'Members', 'sws-members-club' ),
            'manage_options',
            'sws-members',
            array( $this, 'render_members_page' )
        );

        add_submenu_page(
            'sws-members',
            __( 'Import Members', 'sws-members-club' ),
            __( 'Import', 'sws-members-club' ),
            'manage_options',
            'sws-import',
            array( $this, 'render_import_page' )
        );

        add_submenu_page(
            'sws-members',
            __( 'Settings', 'sws-members-club' ),
            __( 'Settings', 'sws-members-club' ),
            'manage_options',
            'sws-settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register plugin settings.
     */
    public function register_settings() {
        // Stripe.
        register_setting( 'sws_settings', 'sws_stripe_publishable_key', 'sanitize_text_field' );
        register_setting( 'sws_settings', 'sws_stripe_secret_key', 'sanitize_text_field' );
        register_setting( 'sws_settings', 'sws_stripe_test_mode', 'absint' );
        register_setting( 'sws_settings', 'sws_stripe_test_publishable_key', 'sanitize_text_field' );
        register_setting( 'sws_settings', 'sws_stripe_test_secret_key', 'sanitize_text_field' );

        // Brand.
        register_setting( 'sws_settings', 'sws_club_logo', 'esc_url_raw' );
        register_setting( 'sws_settings', 'sws_primary_colour', 'sanitize_hex_color' );
        register_setting( 'sws_settings', 'sws_secondary_colour', 'sanitize_hex_color' );

        // Defaults.
        register_setting( 'sws_settings', 'sws_default_cancellation_cutoff', 'absint' );
        register_setting( 'sws_settings', 'sws_waitlist_claim_hours', 'absint' );
        register_setting( 'sws_settings', 'sws_reminder_intervals', 'sanitize_text_field' );
        register_setting( 'sws_settings', 'sws_policy_page_url', 'esc_url_raw' );

        // Email.
        register_setting( 'sws_settings', 'sws_email_from_name', 'sanitize_text_field' );
        register_setting( 'sws_settings', 'sws_email_from_address', 'sanitize_email' );

        // Penalties.
        register_setting( 'sws_settings', 'sws_penalty_max_strikes', 'absint' );
    }

    /**
     * Enqueue admin CSS/JS.
     */
    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'sws-' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'sws-admin',
            SWS_PLUGIN_URL . 'admin/css/sws-admin.css',
            array(),
            SWS_PLUGIN_VERSION
        );

        wp_enqueue_script(
            'sws-admin',
            SWS_PLUGIN_URL . 'admin/js/sws-admin.js',
            array( 'jquery' ),
            SWS_PLUGIN_VERSION,
            true
        );
    }

    // -------------------------------------------------------------------------
    // Members list page
    // -------------------------------------------------------------------------

    public function render_members_page() {
        // Check for member detail view.
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'view' && isset( $_GET['user_id'] ) ) {
            $this->render_member_detail( (int) $_GET['user_id'] );
            return;
        }

        $members_model = new SWS_Members();
        $tiers_model   = new SWS_Tiers();

        $current_page = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
        $search       = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $filter_tier  = isset( $_GET['tier_id'] ) ? (int) $_GET['tier_id'] : 0;
        $filter_status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';

        $result = $members_model->list_members( array(
            'per_page' => 20,
            'page'     => $current_page,
            'search'   => $search,
            'tier_id'  => $filter_tier,
            'status'   => $filter_status,
        ) );

        $members    = $result['items'];
        $total      = $result['total'];
        $tiers      = $tiers_model->get_all();
        $total_pages = ceil( $total / 20 );

        include SWS_PLUGIN_DIR . 'templates/admin/members-list.php';
    }

    /**
     * Render single member detail/edit view.
     */
    private function render_member_detail( $user_id ) {
        $members_model = new SWS_Members();
        $tiers_model   = new SWS_Tiers();

        $member = $members_model->get_by_user_id( $user_id );
        if ( ! $member ) {
            echo '<div class="wrap"><h1>' . esc_html__( 'Member not found.', 'sws-members-club' ) . '</h1></div>';
            return;
        }

        $user  = get_userdata( $user_id );
        $tiers = $tiers_model->get_all();

        // Get penalty history.
        global $wpdb;
        $penalties = $wpdb->get_results( $wpdb->prepare(
            "SELECT p.*, e.title AS event_title
             FROM " . SWS_Database::table( 'penalties' ) . " p
             LEFT JOIN " . SWS_Database::table( 'events' ) . " e ON p.event_id = e.id
             WHERE p.member_user_id = %d
             ORDER BY p.created_at DESC",
            $user_id
        ) );

        include SWS_PLUGIN_DIR . 'templates/admin/member-detail.php';
    }

    // -------------------------------------------------------------------------
    // Import page
    // -------------------------------------------------------------------------

    public function render_import_page() {
        $import_result = get_transient( 'sws_import_result' );
        if ( $import_result ) {
            delete_transient( 'sws_import_result' );
        }

        include SWS_PLUGIN_DIR . 'templates/admin/import.php';
    }

    /**
     * Handle CSV import form submission.
     */
    public function handle_csv_import() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized.', 'sws-members-club' ) );
        }

        check_admin_referer( 'sws_import_members', 'sws_nonce' );

        if ( ! isset( $_FILES['csv_file'] ) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK ) {
            set_transient( 'sws_import_result', array(
                'imported' => 0,
                'skipped'  => 0,
                'errors'   => array( __( 'No file uploaded or upload error.', 'sws-members-club' ) ),
            ), 60 );
            wp_safe_redirect( admin_url( 'admin.php?page=sws-import' ) );
            exit;
        }

        $file = $_FILES['csv_file'];

        // Validate file type.
        $ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        if ( $ext !== 'csv' ) {
            set_transient( 'sws_import_result', array(
                'imported' => 0,
                'skipped'  => 0,
                'errors'   => array( __( 'Please upload a CSV file.', 'sws-members-club' ) ),
            ), 60 );
            wp_safe_redirect( admin_url( 'admin.php?page=sws-import' ) );
            exit;
        }

        $members = new SWS_Members();
        $result  = $members->import_csv( $file['tmp_name'] );

        set_transient( 'sws_import_result', $result, 60 );
        wp_safe_redirect( admin_url( 'admin.php?page=sws-import' ) );
        exit;
    }

    // -------------------------------------------------------------------------
    // Member save handler
    // -------------------------------------------------------------------------

    public function handle_save_member() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized.', 'sws-members-club' ) );
        }

        check_admin_referer( 'sws_save_member', 'sws_nonce' );

        $user_id = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
        if ( ! $user_id ) {
            wp_die( __( 'Invalid member.', 'sws-members-club' ) );
        }

        $members = new SWS_Members();
        $members->update( $user_id, array(
            'membership_tier_id' => isset( $_POST['membership_tier_id'] ) ? (int) $_POST['membership_tier_id'] : 0,
            'membership_status'  => isset( $_POST['membership_status'] ) ? sanitize_text_field( $_POST['membership_status'] ) : 'active',
            'billing_cycle'      => isset( $_POST['billing_cycle'] ) ? sanitize_text_field( $_POST['billing_cycle'] ) : 'monthly',
        ) );

        wp_safe_redirect( admin_url( 'admin.php?page=sws-members&action=view&user_id=' . $user_id . '&updated=1' ) );
        exit;
    }

    // -------------------------------------------------------------------------
    // Reset strikes handler
    // -------------------------------------------------------------------------

    public function handle_reset_strikes() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized.', 'sws-members-club' ) );
        }

        check_admin_referer( 'sws_reset_strikes', 'sws_nonce' );

        $user_id    = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
        $new_status = isset( $_POST['new_status'] ) ? sanitize_text_field( $_POST['new_status'] ) : '';

        if ( $user_id ) {
            $members = new SWS_Members();
            $members->reset_strikes( $user_id, $new_status );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=sws-members&action=view&user_id=' . $user_id . '&strikes_reset=1' ) );
        exit;
    }

    // -------------------------------------------------------------------------
    // Tier save handler
    // -------------------------------------------------------------------------

    public function handle_save_tier() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized.', 'sws-members-club' ) );
        }

        check_admin_referer( 'sws_save_tier', 'sws_nonce' );

        $tiers   = new SWS_Tiers();
        $tier_id = isset( $_POST['tier_id'] ) ? (int) $_POST['tier_id'] : 0;

        $data = array(
            'name'            => sanitize_text_field( $_POST['name'] ?? '' ),
            'slug'            => sanitize_title( $_POST['slug'] ?? '' ),
            'events_included' => isset( $_POST['events_included'] ) ? 1 : 0,
            'monthly_price'   => (float) ( $_POST['monthly_price'] ?? 0 ),
            'quarterly_price' => (float) ( $_POST['quarterly_price'] ?? 0 ),
            'annual_price'    => (float) ( $_POST['annual_price'] ?? 0 ),
            'sort_order'      => (int) ( $_POST['sort_order'] ?? 0 ),
            'is_active'       => isset( $_POST['is_active'] ) ? 1 : 0,
        );

        if ( $tier_id ) {
            $tiers->update( $tier_id, $data );
        } else {
            $tiers->create( $data );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=sws-settings&tab=tiers&saved=1' ) );
        exit;
    }

    // -------------------------------------------------------------------------
    // Settings page
    // -------------------------------------------------------------------------

    public function render_settings_page() {
        $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';
        $tiers_model = new SWS_Tiers();
        $tiers = $tiers_model->get_all();

        include SWS_PLUGIN_DIR . 'templates/admin/settings.php';
    }
}
