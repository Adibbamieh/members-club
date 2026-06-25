<?php
/**
 * WooCommerce Subscriptions integration.
 *
 * Membership active-status and tier are owned by WooCommerce Subscriptions —
 * this plugin only reads them. Recurring billing stays entirely in WooCommerce.
 *
 * If WooCommerce Subscriptions is not present, callers fall back to the
 * plugin's own legacy member record (see SWS_Members::get_membership).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SWS_Woo {

    /**
     * Subscription statuses that count as an active membership.
     * 'pending-cancel' = set to cancel at period end but still paid through the term.
     *
     * @return array
     */
    public static function active_statuses() {
        return apply_filters( 'sws_active_subscription_statuses', array( 'active', 'pending-cancel' ) );
    }

    /**
     * Is WooCommerce Subscriptions available to query?
     *
     * @return bool
     */
    public static function is_available() {
        return function_exists( 'wcs_get_users_subscriptions' );
    }

    /**
     * Does this user hold a membership subscription in an active state?
     *
     * @param int $user_id WordPress user ID.
     * @return bool
     */
    public static function is_active_member( $user_id ) {
        if ( ! self::is_available() || ! $user_id ) {
            return false;
        }

        foreach ( wcs_get_users_subscriptions( $user_id ) as $subscription ) {
            if ( $subscription->has_status( self::active_statuses() ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve the SWS tier for a member from their active WooCommerce subscription.
     *
     * Looks at the products on the user's active subscription(s) and matches them
     * against the tier → WC product mapping. If the member holds more than one,
     * an events-included tier wins (most generous).
     *
     * @param int $user_id WordPress user ID.
     * @return object|null SWS tier row, or null if none resolved.
     */
    public static function get_member_tier( $user_id ) {
        if ( ! self::is_available() || ! $user_id ) {
            return null;
        }

        $tiers   = new SWS_Tiers();
        $matched = array();

        foreach ( wcs_get_users_subscriptions( $user_id ) as $subscription ) {
            if ( ! $subscription->has_status( self::active_statuses() ) ) {
                continue;
            }

            foreach ( $subscription->get_items() as $item ) {
                // Match on the variation first (if any), then the parent product.
                $product_id   = $item->get_product_id();
                $variation_id = method_exists( $item, 'get_variation_id' ) ? $item->get_variation_id() : 0;

                $tier = $variation_id ? $tiers->get_by_wc_product( $variation_id ) : null;
                if ( ! $tier ) {
                    $tier = $tiers->get_by_wc_product( $product_id );
                }

                if ( $tier ) {
                    $matched[] = $tier;
                }
            }
        }

        if ( empty( $matched ) ) {
            return null;
        }

        // Prefer an events-included tier when the member matches more than one.
        foreach ( $matched as $tier ) {
            if ( (int) $tier->events_included === 1 ) {
                return $tier;
            }
        }

        return $matched[0];
    }
}
