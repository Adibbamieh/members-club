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
        add_action( 'admin_post_sws_save_event', array( $this, 'handle_save_event' ) );
        add_action( 'admin_post_sws_duplicate_event', array( $this, 'handle_duplicate_event' ) );
        add_action( 'admin_post_sws_cancel_event', array( $this, 'handle_cancel_event' ) );
        add_action( 'admin_post_sws_complete_event', array( $this, 'handle_complete_event' ) );
        add_action( 'admin_post_sws_mark_noshow', array( $this, 'handle_mark_noshow' ) );
        add_action( 'admin_post_sws_admin_refund', array( $this, 'handle_admin_refund' ) );
        add_action( 'admin_post_sws_admin_remove_waitlist', array( $this, 'handle_admin_remove_waitlist' ) );
        add_action( 'admin_post_sws_export_csv', array( $this, 'handle_export_csv' ) );
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
            __( 'Events', 'sws-members-club' ),
            __( 'Events', 'sws-members-club' ),
            'manage_options',
            'sws-events',
            array( $this, 'render_events_page' )
        );

        add_submenu_page(
            'sws-members',
            __( 'Bookings', 'sws-members-club' ),
            __( 'Bookings', 'sws-members-club' ),
            'manage_options',
            'sws-bookings',
            array( $this, 'render_bookings_page' )
        );

        add_submenu_page(
            'sws-members',
            __( 'Reports', 'sws-members-club' ),
            __( 'Reports', 'sws-members-club' ),
            'manage_options',
            'sws-reports',
            array( $this, 'render_reports_page' )
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
    // Events pages
    // -------------------------------------------------------------------------

    public function render_events_page() {
        $action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';

        if ( in_array( $action, array( 'new', 'edit' ), true ) ) {
            $this->render_event_edit();
            return;
        }

        $events_model = new SWS_Events();

        $current_page  = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
        $search        = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $filter_status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';

        $result = $events_model->list_events( array(
            'per_page' => 20,
            'page'     => $current_page,
            'search'   => $search,
            'status'   => $filter_status,
        ) );

        $events      = $result['items'];
        $total       = $result['total'];
        $total_pages = ceil( $total / 20 );

        include SWS_PLUGIN_DIR . 'templates/admin/events-list.php';
    }

    private function render_event_edit() {
        $events_model = new SWS_Events();

        $event_id = isset( $_GET['event_id'] ) ? (int) $_GET['event_id'] : 0;
        $is_new   = ! $event_id;
        $event    = $event_id ? $events_model->get( $event_id ) : null;
        $stats    = $event ? $events_model->get_stats( $event_id ) : null;

        if ( $event_id && ! $event ) {
            echo '<div class="wrap"><h1>' . esc_html__( 'Event not found.', 'sws-members-club' ) . '</h1></div>';
            return;
        }

        include SWS_PLUGIN_DIR . 'templates/admin/event-edit.php';
    }

    public function handle_save_event() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized.', 'sws-members-club' ) );
        }

        check_admin_referer( 'sws_save_event', 'sws_nonce' );

        $events   = new SWS_Events();
        $event_id = isset( $_POST['event_id'] ) ? (int) $_POST['event_id'] : 0;

        $data = array(
            'title'                     => $_POST['title'] ?? '',
            'description'               => $_POST['description'] ?? '',
            'venue_name'                => $_POST['venue_name'] ?? '',
            'venue_address'             => $_POST['venue_address'] ?? '',
            'event_date'                => $_POST['event_date'] ?? '',
            'event_time_start'          => $_POST['event_time_start'] ?? '',
            'event_time_end'            => $_POST['event_time_end'] ?? '',
            'capacity'                  => $_POST['capacity'] ?? 50,
            'ticket_price'              => $_POST['ticket_price'] ?? 0,
            'cancellation_cutoff_hours' => $_POST['cancellation_cutoff_hours'] ?? 48,
            'waitlist_enabled'          => isset( $_POST['waitlist_enabled'] ) ? 1 : 0,
            'status'                    => $_POST['status'] ?? 'draft',
        );

        if ( $event_id ) {
            $events->update( $event_id, $data );
            wp_safe_redirect( admin_url( 'admin.php?page=sws-events&action=edit&event_id=' . $event_id . '&updated=1' ) );
        } else {
            $new_id = $events->create( $data );
            if ( $new_id ) {
                wp_safe_redirect( admin_url( 'admin.php?page=sws-events&action=edit&event_id=' . $new_id . '&created=1' ) );
            } else {
                wp_safe_redirect( admin_url( 'admin.php?page=sws-events&error=1' ) );
            }
        }
        exit;
    }

    public function handle_duplicate_event() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized.', 'sws-members-club' ) );
        }

        $event_id = isset( $_GET['event_id'] ) ? (int) $_GET['event_id'] : 0;
        check_admin_referer( 'sws_duplicate_event_' . $event_id );

        $events = new SWS_Events();
        $new_id = $events->duplicate( $event_id );

        if ( $new_id ) {
            wp_safe_redirect( admin_url( 'admin.php?page=sws-events&action=edit&event_id=' . $new_id . '&duplicated=1' ) );
        } else {
            wp_safe_redirect( admin_url( 'admin.php?page=sws-events&error=1' ) );
        }
        exit;
    }

    public function handle_cancel_event() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized.', 'sws-members-club' ) );
        }

        $event_id = isset( $_GET['event_id'] ) ? (int) $_GET['event_id'] : 0;
        check_admin_referer( 'sws_cancel_event_' . $event_id );

        $events = new SWS_Events();
        $events->cancel( $event_id );

        wp_safe_redirect( admin_url( 'admin.php?page=sws-events&cancelled=1' ) );
        exit;
    }

    public function handle_complete_event() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized.', 'sws-members-club' ) );
        }

        $event_id = isset( $_GET['event_id'] ) ? (int) $_GET['event_id'] : 0;
        check_admin_referer( 'sws_complete_event_' . $event_id );

        $events = new SWS_Events();
        $events->complete( $event_id );

        wp_safe_redirect( admin_url( 'admin.php?page=sws-events&completed=1' ) );
        exit;
    }

    // -------------------------------------------------------------------------
    // Bookings page (per-event)
    // -------------------------------------------------------------------------

    public function render_bookings_page() {
        $event_id = isset( $_GET['event_id'] ) ? (int) $_GET['event_id'] : 0;

        if ( ! $event_id ) {
            // Show event picker.
            $events_model = new SWS_Events();
            $result = $events_model->list_events( array( 'per_page' => 50, 'orderby' => 'event_date', 'order' => 'DESC' ) );
            echo '<div class="wrap"><h1>' . esc_html__( 'Bookings', 'sws-members-club' ) . '</h1>';
            echo '<p>' . esc_html__( 'Select an event to view its bookings:', 'sws-members-club' ) . '</p>';
            echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th>' . esc_html__( 'Event', 'sws-members-club' ) . '</th><th>' . esc_html__( 'Date', 'sws-members-club' ) . '</th><th>' . esc_html__( 'Status', 'sws-members-club' ) . '</th></tr></thead><tbody>';
            foreach ( $result['items'] as $event ) {
                echo '<tr><td><a href="' . esc_url( admin_url( 'admin.php?page=sws-bookings&event_id=' . $event->id ) ) . '">' . esc_html( $event->title ) . '</a></td>';
                echo '<td>' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $event->event_date ) ) ) . '</td>';
                echo '<td><span class="sws-status sws-status--' . esc_attr( $event->status ) . '">' . esc_html( ucfirst( $event->status ) ) . '</span></td></tr>';
            }
            echo '</tbody></table></div>';
            return;
        }

        $events_model   = new SWS_Events();
        $bookings_model = new SWS_Bookings();

        $event    = $events_model->get( $event_id );
        $bookings = $bookings_model->get_event_bookings( $event_id );
        $stats    = $events_model->get_stats( $event_id );

        if ( ! $event ) {
            echo '<div class="wrap"><h1>' . esc_html__( 'Event not found.', 'sws-members-club' ) . '</h1></div>';
            return;
        }

        include SWS_PLUGIN_DIR . 'templates/admin/bookings-list.php';
    }

    public function handle_mark_noshow() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized.', 'sws-members-club' ) );
        }

        $booking_id = isset( $_GET['booking_id'] ) ? (int) $_GET['booking_id'] : 0;
        check_admin_referer( 'sws_mark_noshow_' . $booking_id );

        global $wpdb;
        $bookings_table = SWS_Database::table( 'bookings' );
        $booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$bookings_table} WHERE id = %d", $booking_id ) );

        if ( $booking && $booking->status === 'confirmed' ) {
            $wpdb->update( $bookings_table, array( 'status' => 'no_show' ), array( 'id' => $booking_id ) );

            $penalties = new SWS_Penalties();
            $penalties->add_strike( $booking->member_user_id, $booking->event_id, 'no_show' );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=sws-bookings&event_id=' . $booking->event_id . '&noshow_marked=1' ) );
        exit;
    }

    public function handle_admin_refund() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized.', 'sws-members-club' ) );
        }

        $booking_id = isset( $_GET['booking_id'] ) ? (int) $_GET['booking_id'] : 0;
        check_admin_referer( 'sws_admin_refund_' . $booking_id );

        $bookings = new SWS_Bookings();
        $booking  = $bookings->get( $booking_id );
        $bookings->admin_refund( $booking_id );

        $event_id = $booking ? $booking->event_id : 0;
        wp_safe_redirect( admin_url( 'admin.php?page=sws-bookings&event_id=' . $event_id . '&refunded=1' ) );
        exit;
    }

    public function handle_admin_remove_waitlist() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized.', 'sws-members-club' ) );
        }

        $booking_id = isset( $_GET['booking_id'] ) ? (int) $_GET['booking_id'] : 0;
        check_admin_referer( 'sws_admin_remove_waitlist_' . $booking_id );

        global $wpdb;
        $bookings_table = SWS_Database::table( 'bookings' );
        $booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$bookings_table} WHERE id = %d", $booking_id ) );

        if ( $booking && $booking->status === 'waitlisted' ) {
            $wpdb->update( $bookings_table, array(
                'status'       => 'cancelled',
                'cancelled_at' => current_time( 'mysql' ),
            ), array( 'id' => $booking_id ) );
        }

        $event_id = $booking ? $booking->event_id : 0;
        wp_safe_redirect( admin_url( 'admin.php?page=sws-bookings&event_id=' . $event_id ) );
        exit;
    }

    // -------------------------------------------------------------------------
    // Reports page
    // -------------------------------------------------------------------------

    public function render_reports_page() {
        $reports       = new SWS_Reports();
        $tiers_model   = new SWS_Tiers();
        $active_report = isset( $_GET['report'] ) ? sanitize_text_field( $_GET['report'] ) : 'overview';
        $tiers         = $tiers_model->get_all();

        $overview          = null;
        $attendance_data   = array();
        $cancellation_data = array();
        $waitlist_data     = null;
        $revenue_data      = array();
        $engagement_data   = array();

        switch ( $active_report ) {
            case 'overview':
                $overview = $reports->get_overview();
                break;
            case 'attendance':
                $tier_filter     = isset( $_GET['tier_id'] ) ? sanitize_text_field( $_GET['tier_id'] ) : '';
                $attendance_data = $reports->get_attendance_report( $tier_filter );
                break;
            case 'cancellations':
                $cancellation_data = $reports->get_cancellation_report();
                break;
            case 'waitlist':
                $waitlist_data = $reports->get_waitlist_report();
                break;
            case 'revenue':
                $revenue_data = $reports->get_revenue_report();
                break;
            case 'engagement':
                $engagement_data = $reports->get_engagement_report();
                break;
        }

        include SWS_PLUGIN_DIR . 'templates/admin/reports-dashboard.php';
    }

    public function handle_export_csv() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized.', 'sws-members-club' ) );
        }

        check_admin_referer( 'sws_export_csv' );

        $report_type = isset( $_GET['report'] ) ? sanitize_text_field( $_GET['report'] ) : '';
        if ( ! $report_type ) {
            wp_die( __( 'No report specified.', 'sws-members-club' ) );
        }

        $reports = new SWS_Reports();
        $reports->export_csv( $report_type );
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
