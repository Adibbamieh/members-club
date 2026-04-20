<?php
/**
 * Membership tier CRUD and logic.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SWS_Tiers {

    /**
     * @var string Table name.
     */
    private $table;

    public function __construct() {
        $this->table = SWS_Database::table( 'membership_tiers' );
    }

    /**
     * Seed default tiers if none exist.
     */
    public function seed_defaults() {
        global $wpdb;

        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" );
        if ( $count > 0 ) {
            return;
        }

        $wpdb->insert( $this->table, array(
            'name'            => 'Standard',
            'slug'            => 'standard',
            'events_included' => 0,
            'monthly_price'   => 0.00,
            'quarterly_price' => 0.00,
            'annual_price'    => 0.00,
            'sort_order'      => 1,
            'is_active'       => 1,
        ) );

        $wpdb->insert( $this->table, array(
            'name'            => 'Premium',
            'slug'            => 'premium',
            'events_included' => 1,
            'monthly_price'   => 0.00,
            'quarterly_price' => 0.00,
            'annual_price'    => 0.00,
            'sort_order'      => 2,
            'is_active'       => 1,
        ) );
    }

    /**
     * Get all tiers, ordered by sort_order.
     *
     * @param bool $active_only Only return active tiers.
     * @return array
     */
    public function get_all( $active_only = false ) {
        global $wpdb;

        $where = $active_only ? 'WHERE is_active = 1' : '';
        return $wpdb->get_results( "SELECT * FROM {$this->table} {$where} ORDER BY sort_order ASC" );
    }

    /**
     * Get a single tier by ID.
     *
     * @param int $id Tier ID.
     * @return object|null
     */
    public function get( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ) );
    }

    /**
     * Get a tier by slug.
     *
     * @param string $slug Tier slug.
     * @return object|null
     */
    public function get_by_slug( $slug ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE slug = %s", $slug ) );
    }

    /**
     * Create a new tier.
     *
     * @param array $data Tier data.
     * @return int|false Tier ID on success, false on failure.
     */
    public function create( $data ) {
        global $wpdb;

        $defaults = array(
            'events_included' => 0,
            'monthly_price'   => 0.00,
            'quarterly_price' => 0.00,
            'annual_price'    => 0.00,
            'sort_order'      => 0,
            'is_active'       => 1,
        );

        $data = wp_parse_args( $data, $defaults );

        if ( empty( $data['name'] ) ) {
            return false;
        }

        if ( empty( $data['slug'] ) ) {
            $data['slug'] = sanitize_title( $data['name'] );
        }

        $result = $wpdb->insert( $this->table, array(
            'name'            => sanitize_text_field( $data['name'] ),
            'slug'            => sanitize_title( $data['slug'] ),
            'events_included' => (int) $data['events_included'],
            'monthly_price'   => (float) $data['monthly_price'],
            'quarterly_price' => (float) $data['quarterly_price'],
            'annual_price'    => (float) $data['annual_price'],
            'sort_order'      => (int) $data['sort_order'],
            'is_active'       => (int) $data['is_active'],
        ) );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update a tier.
     *
     * @param int   $id   Tier ID.
     * @param array $data Fields to update.
     * @return bool
     */
    public function update( $id, $data ) {
        global $wpdb;

        $allowed = array(
            'name', 'slug', 'events_included',
            'monthly_price', 'quarterly_price', 'annual_price',
            'sort_order', 'is_active',
        );

        $update = array();
        foreach ( $allowed as $field ) {
            if ( ! isset( $data[ $field ] ) ) {
                continue;
            }

            switch ( $field ) {
                case 'name':
                    $update['name'] = sanitize_text_field( $data['name'] );
                    break;
                case 'slug':
                    $update['slug'] = sanitize_title( $data['slug'] );
                    break;
                case 'events_included':
                case 'sort_order':
                case 'is_active':
                    $update[ $field ] = (int) $data[ $field ];
                    break;
                case 'monthly_price':
                case 'quarterly_price':
                case 'annual_price':
                    $update[ $field ] = (float) $data[ $field ];
                    break;
            }
        }

        if ( empty( $update ) ) {
            return false;
        }

        return (bool) $wpdb->update( $this->table, $update, array( 'id' => (int) $id ) );
    }

    /**
     * Check if a tier includes events in the membership.
     *
     * @param int $tier_id Tier ID.
     * @return bool
     */
    public function tier_includes_events( $tier_id ) {
        $tier = $this->get( $tier_id );
        return $tier && (bool) $tier->events_included;
    }

    /**
     * Get tiers as id => name array (for dropdowns).
     *
     * @param bool $active_only Only active tiers.
     * @return array
     */
    public function get_options( $active_only = true ) {
        $tiers   = $this->get_all( $active_only );
        $options = array();
        foreach ( $tiers as $tier ) {
            $options[ $tier->id ] = $tier->name;
        }
        return $options;
    }
}
