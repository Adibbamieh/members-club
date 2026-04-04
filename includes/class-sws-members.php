<?php
/**
 * Member CRUD, CSV import, and Stripe subscription sync.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SWS_Members {

    /**
     * @var string Table name.
     */
    private $table;

    public function __construct() {
        $this->table = SWS_Database::table( 'members' );
    }

    /**
     * Get a member record by WordPress user ID.
     *
     * @param int $user_id WordPress user ID.
     * @return object|null
     */
    public function get_by_user_id( $user_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT m.*, t.name AS tier_name, t.slug AS tier_slug, t.events_included
             FROM {$this->table} m
             LEFT JOIN " . SWS_Database::table( 'membership_tiers' ) . " t ON m.membership_tier_id = t.id
             WHERE m.user_id = %d",
            $user_id
        ) );
    }

    /**
     * Get a member record by ID.
     *
     * @param int $id Member row ID.
     * @return object|null
     */
    public function get( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT m.*, t.name AS tier_name, t.slug AS tier_slug, t.events_included
             FROM {$this->table} m
             LEFT JOIN " . SWS_Database::table( 'membership_tiers' ) . " t ON m.membership_tier_id = t.id
             WHERE m.id = %d",
            $id
        ) );
    }

    /**
     * List members with pagination, search, and filtering.
     *
     * @param array $args Query arguments.
     * @return array { items: array, total: int }
     */
    public function list_members( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'per_page' => 20,
            'page'     => 1,
            'search'   => '',
            'tier_id'  => 0,
            'status'   => '',
            'orderby'  => 'created_at',
            'order'    => 'DESC',
        );

        $args = wp_parse_args( $args, $defaults );

        $tiers_table = SWS_Database::table( 'membership_tiers' );
        $base_query  = "FROM {$this->table} m
                        LEFT JOIN {$tiers_table} t ON m.membership_tier_id = t.id
                        LEFT JOIN {$wpdb->users} u ON m.user_id = u.ID";

        $where   = array( '1=1' );
        $prepare = array();

        if ( ! empty( $args['search'] ) ) {
            $like      = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[]   = '(u.display_name LIKE %s OR u.user_email LIKE %s)';
            $prepare[] = $like;
            $prepare[] = $like;
        }

        if ( $args['tier_id'] > 0 ) {
            $where[]   = 'm.membership_tier_id = %d';
            $prepare[] = (int) $args['tier_id'];
        }

        if ( ! empty( $args['status'] ) ) {
            $where[]   = 'm.membership_status = %s';
            $prepare[] = sanitize_text_field( $args['status'] );
        }

        $where_clause = implode( ' AND ', $where );

        // Allowed orderby columns.
        $allowed_orderby = array( 'created_at', 'display_name', 'user_email', 'membership_status', 'tier_name' );
        $orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
        if ( $orderby === 'display_name' || $orderby === 'user_email' ) {
            $orderby = 'u.' . $orderby;
        } elseif ( $orderby === 'tier_name' ) {
            $orderby = 't.name';
        } else {
            $orderby = 'm.' . $orderby;
        }
        $order = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        // Count.
        $count_sql = "SELECT COUNT(*) {$base_query} WHERE {$where_clause}";
        if ( ! empty( $prepare ) ) {
            $count_sql = $wpdb->prepare( $count_sql, ...$prepare );
        }
        $total = (int) $wpdb->get_var( $count_sql );

        // Fetch.
        $offset = ( max( 1, (int) $args['page'] ) - 1 ) * (int) $args['per_page'];
        $select_sql = "SELECT m.*, t.name AS tier_name, t.slug AS tier_slug, t.events_included,
                              u.display_name, u.user_email
                       {$base_query}
                       WHERE {$where_clause}
                       ORDER BY {$orderby} {$order}
                       LIMIT %d OFFSET %d";

        $select_prepare = array_merge( $prepare, array( (int) $args['per_page'], $offset ) );
        $items = $wpdb->get_results( $wpdb->prepare( $select_sql, ...$select_prepare ) );

        return array(
            'items' => $items,
            'total' => $total,
        );
    }

    /**
     * Create a member record (and optionally the WordPress user).
     *
     * @param array $data Member data.
     * @return int|WP_Error Member row ID on success.
     */
    public function create( $data ) {
        global $wpdb;

        $user_id = isset( $data['user_id'] ) ? (int) $data['user_id'] : 0;

        // If no user_id, create the WordPress user.
        if ( ! $user_id ) {
            if ( empty( $data['email'] ) ) {
                return new \WP_Error( 'missing_email', __( 'Email is required.', 'sws-members-club' ) );
            }

            $email = sanitize_email( $data['email'] );
            $existing = get_user_by( 'email', $email );

            if ( $existing ) {
                $user_id = $existing->ID;
            } else {
                $username = sanitize_user( strstr( $email, '@', true ), true );
                if ( username_exists( $username ) ) {
                    $username = $username . '_' . wp_rand( 100, 999 );
                }

                $name_parts = array();
                if ( ! empty( $data['name'] ) ) {
                    $name_parts = explode( ' ', sanitize_text_field( $data['name'] ), 2 );
                }

                $user_id = wp_insert_user( array(
                    'user_login'   => $username,
                    'user_email'   => $email,
                    'user_pass'    => wp_generate_password( 24 ),
                    'display_name' => ! empty( $data['name'] ) ? sanitize_text_field( $data['name'] ) : $username,
                    'first_name'   => $name_parts[0] ?? '',
                    'last_name'    => $name_parts[1] ?? '',
                    'role'         => 'sws_member',
                ) );

                if ( is_wp_error( $user_id ) ) {
                    return $user_id;
                }
            }
        }

        // Ensure the user has the sws_member role.
        $user = get_userdata( $user_id );
        if ( $user && ! in_array( 'sws_member', $user->roles, true ) ) {
            $user->add_role( 'sws_member' );
        }

        // Check if member record already exists.
        $existing_member = $this->get_by_user_id( $user_id );
        if ( $existing_member ) {
            return new \WP_Error( 'member_exists', __( 'A member record already exists for this user.', 'sws-members-club' ) );
        }

        // Resolve tier.
        $tier_id = 0;
        if ( ! empty( $data['membership_tier_id'] ) ) {
            $tier_id = (int) $data['membership_tier_id'];
        } elseif ( ! empty( $data['tier_slug'] ) ) {
            $tiers = new SWS_Tiers();
            $tier  = $tiers->get_by_slug( sanitize_text_field( $data['tier_slug'] ) );
            $tier_id = $tier ? (int) $tier->id : 0;
        }

        if ( ! $tier_id ) {
            // Default to first active tier.
            $tiers     = new SWS_Tiers();
            $all_tiers = $tiers->get_all( true );
            $tier_id   = ! empty( $all_tiers ) ? (int) $all_tiers[0]->id : 0;
        }

        $billing_cycles = array( 'monthly', 'quarterly', 'annual' );
        $billing_cycle  = isset( $data['billing_cycle'] ) && in_array( $data['billing_cycle'], $billing_cycles, true )
            ? $data['billing_cycle']
            : 'monthly';

        $statuses = array( 'active', 'lapsed', 'suspended', 'waitlist_only' );
        $status   = isset( $data['membership_status'] ) && in_array( $data['membership_status'], $statuses, true )
            ? $data['membership_status']
            : 'active';

        $result = $wpdb->insert( $this->table, array(
            'user_id'                => $user_id,
            'membership_tier_id'     => $tier_id,
            'membership_status'      => $status,
            'stripe_customer_id'     => ! empty( $data['stripe_customer_id'] ) ? sanitize_text_field( $data['stripe_customer_id'] ) : null,
            'stripe_subscription_id' => ! empty( $data['stripe_subscription_id'] ) ? sanitize_text_field( $data['stripe_subscription_id'] ) : null,
            'billing_cycle'          => $billing_cycle,
            'membership_start_date'  => ! empty( $data['membership_start_date'] ) ? sanitize_text_field( $data['membership_start_date'] ) : current_time( 'Y-m-d' ),
            'membership_end_date'    => ! empty( $data['membership_end_date'] ) ? sanitize_text_field( $data['membership_end_date'] ) : null,
            'penalty_strikes'        => 0,
        ) );

        if ( ! $result ) {
            return new \WP_Error( 'db_error', __( 'Failed to create member record.', 'sws-members-club' ) );
        }

        $member_id = $wpdb->insert_id;

        /**
         * Fires after a member is created.
         *
         * @param int   $member_id Member row ID.
         * @param int   $user_id   WordPress user ID.
         * @param array $data      Original data passed.
         */
        do_action( 'sws_member_created', $member_id, $user_id, $data );

        return $member_id;
    }

    /**
     * Update a member record.
     *
     * @param int   $user_id WordPress user ID.
     * @param array $data    Fields to update.
     * @return bool
     */
    public function update( $user_id, $data ) {
        global $wpdb;

        $allowed = array(
            'membership_tier_id', 'membership_status', 'stripe_customer_id',
            'stripe_subscription_id', 'billing_cycle', 'membership_start_date',
            'membership_end_date', 'penalty_strikes',
        );

        $update = array();
        foreach ( $allowed as $field ) {
            if ( ! array_key_exists( $field, $data ) ) {
                continue;
            }
            $update[ $field ] = $data[ $field ];
        }

        if ( empty( $update ) ) {
            return false;
        }

        $result = $wpdb->update( $this->table, $update, array( 'user_id' => (int) $user_id ) );

        if ( $result !== false ) {
            do_action( 'sws_member_updated', $user_id, $update );
        }

        return $result !== false;
    }

    /**
     * Import members from a CSV file.
     *
     * Expected columns: name, email, stripe_customer_id, billing_cycle, tier_slug, start_date
     *
     * @param string $file_path Path to uploaded CSV.
     * @return array { imported: int, skipped: int, errors: array }
     */
    public function import_csv( $file_path ) {
        $result = array(
            'imported' => 0,
            'skipped'  => 0,
            'errors'   => array(),
        );

        if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
            $result['errors'][] = __( 'CSV file not found or not readable.', 'sws-members-club' );
            return $result;
        }

        $handle = fopen( $file_path, 'r' );
        if ( ! $handle ) {
            $result['errors'][] = __( 'Failed to open CSV file.', 'sws-members-club' );
            return $result;
        }

        // Read header row.
        $header = fgetcsv( $handle );
        if ( ! $header ) {
            fclose( $handle );
            $result['errors'][] = __( 'CSV file is empty.', 'sws-members-club' );
            return $result;
        }

        // Normalise headers.
        $header = array_map( function ( $h ) {
            return strtolower( trim( $h ) );
        }, $header );

        $required = array( 'email' );
        foreach ( $required as $col ) {
            if ( ! in_array( $col, $header, true ) ) {
                fclose( $handle );
                $result['errors'][] = sprintf(
                    /* translators: %s: column name */
                    __( 'Missing required column: %s', 'sws-members-club' ),
                    $col
                );
                return $result;
            }
        }

        $row_num = 1;
        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            $row_num++;

            if ( count( $row ) !== count( $header ) ) {
                $result['errors'][] = sprintf(
                    /* translators: %d: row number */
                    __( 'Row %d: column count mismatch.', 'sws-members-club' ),
                    $row_num
                );
                $result['skipped']++;
                continue;
            }

            $data = array_combine( $header, $row );

            $email = sanitize_email( trim( $data['email'] ?? '' ) );
            if ( ! is_email( $email ) ) {
                $result['errors'][] = sprintf(
                    /* translators: %d: row number, %s: email */
                    __( 'Row %1$d: invalid email "%2$s".', 'sws-members-club' ),
                    $row_num,
                    $data['email'] ?? ''
                );
                $result['skipped']++;
                continue;
            }

            // Check if member already exists by email.
            $existing_user = get_user_by( 'email', $email );
            if ( $existing_user ) {
                $existing_member = $this->get_by_user_id( $existing_user->ID );
                if ( $existing_member ) {
                    $result['errors'][] = sprintf(
                        /* translators: %d: row number, %s: email */
                        __( 'Row %1$d: member with email "%2$s" already exists, skipped.', 'sws-members-club' ),
                        $row_num,
                        $email
                    );
                    $result['skipped']++;
                    continue;
                }
            }

            $member_data = array(
                'name'                  => $data['name'] ?? '',
                'email'                 => $email,
                'stripe_customer_id'    => $data['stripe_customer_id'] ?? '',
                'billing_cycle'         => $data['billing_cycle'] ?? 'monthly',
                'tier_slug'             => $data['tier_slug'] ?? $data['membership_tier'] ?? '',
                'membership_start_date' => $data['start_date'] ?? $data['membership_start_date'] ?? '',
            );

            $member_id = $this->create( $member_data );

            if ( is_wp_error( $member_id ) ) {
                $result['errors'][] = sprintf(
                    /* translators: %d: row number, %s: error message */
                    __( 'Row %1$d: %2$s', 'sws-members-club' ),
                    $row_num,
                    $member_id->get_error_message()
                );
                $result['skipped']++;
            } else {
                $result['imported']++;
            }
        }

        fclose( $handle );
        return $result;
    }

    /**
     * Sync all members' Stripe subscription statuses.
     */
    public function sync_all_stripe_subscriptions() {
        global $wpdb;

        $members = $wpdb->get_results(
            "SELECT * FROM {$this->table}
             WHERE stripe_subscription_id IS NOT NULL
             AND stripe_subscription_id != ''
             AND membership_status IN ('active', 'lapsed')"
        );

        if ( empty( $members ) ) {
            return;
        }

        $stripe_secret = get_option( 'sws_stripe_secret_key', '' );
        if ( empty( $stripe_secret ) ) {
            return;
        }

        foreach ( $members as $member ) {
            $this->sync_single_stripe_subscription( $member, $stripe_secret );
        }
    }

    /**
     * Sync a single member's Stripe subscription.
     *
     * @param object $member     Member record.
     * @param string $secret_key Stripe secret key.
     */
    private function sync_single_stripe_subscription( $member, $secret_key ) {
        $response = wp_remote_get( 'https://api.stripe.com/v1/subscriptions/' . $member->stripe_subscription_id, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $secret_key,
            ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) {
            return;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body ) || isset( $body['error'] ) ) {
            return;
        }

        $stripe_status = $body['status'] ?? '';
        $active_statuses = array( 'active', 'trialing' );

        if ( in_array( $stripe_status, $active_statuses, true ) && $member->membership_status !== 'active' ) {
            $this->update( $member->user_id, array( 'membership_status' => 'active' ) );
        } elseif ( ! in_array( $stripe_status, $active_statuses, true ) && $member->membership_status === 'active' ) {
            $this->update( $member->user_id, array( 'membership_status' => 'lapsed' ) );

            do_action( 'sws_member_lapsed', $member->user_id, $stripe_status );
        }
    }

    /**
     * Add a penalty strike to a member.
     *
     * @param int $user_id WordPress user ID.
     * @return int New strike count.
     */
    public function add_strike( $user_id ) {
        global $wpdb;

        $member = $this->get_by_user_id( $user_id );
        if ( ! $member ) {
            return 0;
        }

        $new_count = (int) $member->penalty_strikes + 1;
        $max_strikes = (int) get_option( 'sws_penalty_max_strikes', 3 );

        $update = array( 'penalty_strikes' => $new_count );

        if ( $new_count >= $max_strikes ) {
            $update['membership_status'] = 'waitlist_only';
        }

        $this->update( $user_id, $update );

        do_action( 'sws_member_strike_added', $user_id, $new_count, $max_strikes );

        return $new_count;
    }

    /**
     * Reset a member's penalty strikes.
     *
     * @param int    $user_id WordPress user ID.
     * @param string $new_status Optional new status to set (e.g. 'active').
     * @return bool
     */
    public function reset_strikes( $user_id, $new_status = '' ) {
        $update = array( 'penalty_strikes' => 0 );

        if ( ! empty( $new_status ) ) {
            $update['membership_status'] = sanitize_text_field( $new_status );
        }

        return $this->update( $user_id, $update );
    }
}
