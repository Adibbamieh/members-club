<?php
/**
 * Data aggregation for the admin reporting dashboard.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SWS_Reports {

    /**
     * Get overview stats.
     *
     * @return object
     */
    public function get_overview() {
        global $wpdb;

        $members_table  = SWS_Database::table( 'members' );
        $tiers_table    = SWS_Database::table( 'membership_tiers' );
        $events_table   = SWS_Database::table( 'events' );
        $bookings_table = SWS_Database::table( 'bookings' );

        $month_start = date( 'Y-m-01' );
        $month_end   = date( 'Y-m-t' );

        $overview = new \stdClass();

        // Members by tier.
        $overview->members_by_tier = $wpdb->get_results(
            "SELECT t.name AS tier_name, t.id AS tier_id,
                    COUNT(m.id) AS count
             FROM {$tiers_table} t
             LEFT JOIN {$members_table} m ON t.id = m.membership_tier_id AND m.membership_status = 'active'
             GROUP BY t.id, t.name
             ORDER BY t.sort_order ASC"
        );

        $overview->total_active = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$members_table} WHERE membership_status = 'active'"
        );

        // Events this month.
        $overview->events_this_month = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$events_table} WHERE event_date BETWEEN %s AND %s AND status != 'cancelled'",
            $month_start, $month_end
        ) );

        // Revenue this month.
        $overview->revenue_this_month = (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(b.amount_paid), 0)
             FROM {$bookings_table} b
             INNER JOIN {$events_table} e ON b.event_id = e.id
             WHERE b.status = 'confirmed'
             AND e.event_date BETWEEN %s AND %s",
            $month_start, $month_end
        ) );

        // Average attendance rate.
        $overview->avg_attendance = $wpdb->get_var(
            "SELECT ROUND(AVG(
                CASE WHEN e.capacity > 0
                     THEN (confirmed_count * 100.0 / e.capacity)
                     ELSE 0
                END
             ), 1)
             FROM {$events_table} e
             LEFT JOIN (
                 SELECT event_id, COUNT(*) AS confirmed_count
                 FROM {$bookings_table}
                 WHERE status = 'confirmed'
                 GROUP BY event_id
             ) bc ON e.id = bc.event_id
             WHERE e.status IN ('published', 'completed')"
        );

        return $overview;
    }

    /**
     * Get per-event attendance report.
     *
     * @param string $tier_filter Optional tier ID to filter by.
     * @return array
     */
    public function get_attendance_report( $tier_filter = '' ) {
        global $wpdb;

        $events_table   = SWS_Database::table( 'events' );
        $bookings_table = SWS_Database::table( 'bookings' );
        $members_table  = SWS_Database::table( 'members' );

        $tier_join  = '';
        $tier_where = '';
        $prepare    = array();

        if ( $tier_filter ) {
            $tier_join  = "INNER JOIN {$members_table} m ON b.member_user_id = m.user_id";
            $tier_where = "AND m.membership_tier_id = %d";
            $prepare[]  = (int) $tier_filter;
        }

        $sql = "SELECT e.id, e.title, e.event_date, e.capacity,
                    COALESCE(SUM(CASE WHEN b.status = 'confirmed' THEN 1 ELSE 0 END), 0) AS bookings,
                    COALESCE(SUM(CASE WHEN b.status IN ('cancelled', 'refunded') THEN 1 ELSE 0 END), 0) AS cancellations,
                    COALESCE(SUM(CASE WHEN b.status = 'no_show' THEN 1 ELSE 0 END), 0) AS no_shows,
                    CASE WHEN e.capacity > 0
                         THEN ROUND(COALESCE(SUM(CASE WHEN b.status = 'confirmed' THEN 1 ELSE 0 END), 0) * 100.0 / e.capacity, 1)
                         ELSE 0
                    END AS attendance_rate
                FROM {$events_table} e
                LEFT JOIN {$bookings_table} b ON e.id = b.event_id
                {$tier_join}
                WHERE e.status IN ('published', 'completed')
                {$tier_where}
                GROUP BY e.id, e.title, e.event_date, e.capacity
                ORDER BY e.event_date DESC
                LIMIT 50";

        if ( ! empty( $prepare ) ) {
            return $wpdb->get_results( $wpdb->prepare( $sql, ...$prepare ) );
        }

        return $wpdb->get_results( $sql );
    }

    /**
     * Get cancellation report.
     *
     * @return array
     */
    public function get_cancellation_report() {
        global $wpdb;

        $bookings_table = SWS_Database::table( 'bookings' );
        $events_table   = SWS_Database::table( 'events' );

        return $wpdb->get_results(
            "SELECT
                DATE_FORMAT(b.cancelled_at, '%Y-%m') AS month,
                COUNT(*) AS total_cancellations,
                COALESCE(SUM(CASE WHEN b.status = 'refunded' THEN b.amount_paid ELSE 0 END), 0) AS refund_total
             FROM {$bookings_table} b
             WHERE b.status IN ('cancelled', 'refunded')
             AND b.cancelled_at IS NOT NULL
             GROUP BY DATE_FORMAT(b.cancelled_at, '%Y-%m')
             ORDER BY month DESC
             LIMIT 12"
        );
    }

    /**
     * Get waitlist report.
     *
     * @return object
     */
    public function get_waitlist_report() {
        global $wpdb;

        $bookings_table = SWS_Database::table( 'bookings' );

        $report = new \stdClass();

        // Total offered.
        $report->total_offered = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$bookings_table} WHERE waitlist_offered_at IS NOT NULL"
        );

        // Total claimed (waitlisted that became confirmed).
        $report->total_claimed = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$bookings_table}
             WHERE waitlist_position IS NOT NULL AND status = 'confirmed'"
        );

        $report->conversion_rate = $report->total_offered > 0
            ? round( ( $report->total_claimed / $report->total_offered ) * 100, 1 )
            : 0;

        return $report;
    }

    /**
     * Get revenue report.
     *
     * @return array
     */
    public function get_revenue_report() {
        global $wpdb;

        $bookings_table = SWS_Database::table( 'bookings' );
        $events_table   = SWS_Database::table( 'events' );
        $members_table  = SWS_Database::table( 'members' );
        $tiers_table    = SWS_Database::table( 'membership_tiers' );

        // Revenue by month.
        $by_month = $wpdb->get_results(
            "SELECT DATE_FORMAT(e.event_date, '%Y-%m') AS month,
                    COALESCE(SUM(b.amount_paid), 0) AS revenue
             FROM {$bookings_table} b
             INNER JOIN {$events_table} e ON b.event_id = e.id
             WHERE b.status = 'confirmed'
             GROUP BY DATE_FORMAT(e.event_date, '%Y-%m')
             ORDER BY month DESC
             LIMIT 12"
        );

        // Revenue by tier.
        $by_tier = $wpdb->get_results(
            "SELECT t.name AS tier_name,
                    COALESCE(SUM(b.amount_paid), 0) AS revenue,
                    COUNT(b.id) AS ticket_count
             FROM {$bookings_table} b
             INNER JOIN {$members_table} m ON b.member_user_id = m.user_id
             INNER JOIN {$tiers_table} t ON m.membership_tier_id = t.id
             WHERE b.status = 'confirmed'
             GROUP BY t.id, t.name
             ORDER BY revenue DESC"
        );

        // Revenue by event (top 20).
        $by_event = $wpdb->get_results(
            "SELECT e.title, e.event_date,
                    COALESCE(SUM(b.amount_paid), 0) AS revenue,
                    COUNT(b.id) AS tickets
             FROM {$bookings_table} b
             INNER JOIN {$events_table} e ON b.event_id = e.id
             WHERE b.status = 'confirmed'
             GROUP BY e.id, e.title, e.event_date
             ORDER BY revenue DESC
             LIMIT 20"
        );

        return array(
            'by_month' => $by_month,
            'by_tier'  => $by_tier,
            'by_event' => $by_event,
        );
    }

    /**
     * Get member engagement report.
     *
     * @return array
     */
    public function get_engagement_report() {
        global $wpdb;

        $members_table  = SWS_Database::table( 'members' );
        $bookings_table = SWS_Database::table( 'bookings' );
        $tiers_table    = SWS_Database::table( 'membership_tiers' );

        // Most active members.
        $most_active = $wpdb->get_results(
            "SELECT u.display_name, u.user_email, t.name AS tier_name,
                    COUNT(b.id) AS booking_count
             FROM {$bookings_table} b
             INNER JOIN {$wpdb->users} u ON b.member_user_id = u.ID
             INNER JOIN {$members_table} m ON b.member_user_id = m.user_id
             INNER JOIN {$tiers_table} t ON m.membership_tier_id = t.id
             WHERE b.status = 'confirmed'
             GROUP BY b.member_user_id, u.display_name, u.user_email, t.name
             ORDER BY booking_count DESC
             LIMIT 20"
        );

        // Members with strikes.
        $with_strikes = $wpdb->get_results(
            "SELECT u.display_name, u.user_email, m.penalty_strikes, m.membership_status,
                    t.name AS tier_name
             FROM {$members_table} m
             INNER JOIN {$wpdb->users} u ON m.user_id = u.ID
             INNER JOIN {$tiers_table} t ON m.membership_tier_id = t.id
             WHERE m.penalty_strikes > 0
             ORDER BY m.penalty_strikes DESC"
        );

        // Lapsed members.
        $lapsed = $wpdb->get_results(
            "SELECT u.display_name, u.user_email, m.membership_status, t.name AS tier_name
             FROM {$members_table} m
             INNER JOIN {$wpdb->users} u ON m.user_id = u.ID
             INNER JOIN {$tiers_table} t ON m.membership_tier_id = t.id
             WHERE m.membership_status = 'lapsed'
             ORDER BY m.updated_at DESC
             LIMIT 20"
        );

        return array(
            'most_active'  => $most_active,
            'with_strikes' => $with_strikes,
            'lapsed'       => $lapsed,
        );
    }

    /**
     * Export a report as CSV.
     *
     * @param string $report_type Report name.
     */
    public function export_csv( $report_type ) {
        $filename = 'sws-' . $report_type . '-' . date( 'Y-m-d' ) . '.csv';

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

        $output = fopen( 'php://output', 'w' );

        switch ( $report_type ) {
            case 'attendance':
                fputcsv( $output, array( 'Event', 'Date', 'Capacity', 'Bookings', 'Cancellations', 'No-Shows', 'Attendance Rate' ) );
                foreach ( $this->get_attendance_report() as $row ) {
                    fputcsv( $output, array( $row->title, $row->event_date, $row->capacity, $row->bookings, $row->cancellations, $row->no_shows, $row->attendance_rate . '%' ) );
                }
                break;

            case 'cancellations':
                fputcsv( $output, array( 'Month', 'Total Cancellations', 'Refund Total' ) );
                foreach ( $this->get_cancellation_report() as $row ) {
                    fputcsv( $output, array( $row->month, $row->total_cancellations, number_format( $row->refund_total, 2 ) ) );
                }
                break;

            case 'revenue':
                $data = $this->get_revenue_report();
                fputcsv( $output, array( 'Month', 'Revenue' ) );
                foreach ( $data['by_month'] as $row ) {
                    fputcsv( $output, array( $row->month, number_format( $row->revenue, 2 ) ) );
                }
                break;

            case 'engagement':
                $data = $this->get_engagement_report();
                fputcsv( $output, array( 'Name', 'Email', 'Tier', 'Bookings' ) );
                foreach ( $data['most_active'] as $row ) {
                    fputcsv( $output, array( $row->display_name, $row->user_email, $row->tier_name, $row->booking_count ) );
                }
                break;
        }

        fclose( $output );
        exit;
    }
}
