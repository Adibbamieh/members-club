<?php
/**
 * Penalty strike tracking and status changes.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SWS_Penalties {

    /**
     * @var string Table name.
     */
    private $table;

    public function __construct() {
        $this->table = SWS_Database::table( 'penalties' );
    }

    /**
     * Add a penalty strike to a member.
     *
     * @param int    $user_id  WordPress user ID.
     * @param int    $event_id Event ID.
     * @param string $reason   'no_show' or 'late_cancellation'.
     * @param string $note     Optional admin note.
     * @return int New strike count.
     */
    public function add_strike( $user_id, $event_id, $reason, $note = '' ) {
        global $wpdb;

        $wpdb->insert( $this->table, array(
            'member_user_id' => (int) $user_id,
            'event_id'       => (int) $event_id,
            'reason'         => sanitize_text_field( $reason ),
            'admin_note'     => sanitize_text_field( $note ),
        ) );

        $members   = new SWS_Members();
        $new_count = $members->add_strike( $user_id );

        do_action( 'sws_penalty_added', $user_id, $event_id, $reason, $new_count );

        return $new_count;
    }

    /**
     * Get penalty history for a member.
     *
     * @param int $user_id WordPress user ID.
     * @return array
     */
    public function get_member_penalties( $user_id ) {
        global $wpdb;

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT p.*, e.title AS event_title, e.event_date
             FROM {$this->table} p
             LEFT JOIN " . SWS_Database::table( 'events' ) . " e ON p.event_id = e.id
             WHERE p.member_user_id = %d
             ORDER BY p.created_at DESC",
            $user_id
        ) );
    }
}
