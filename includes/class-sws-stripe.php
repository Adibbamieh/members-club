<?php
/**
 * All Stripe API interactions.
 *
 * Uses WordPress HTTP API (wp_remote_*) — no Stripe PHP SDK required.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SWS_Stripe {

    /**
     * Get the active Stripe secret key (test or live).
     *
     * @return string
     */
    public static function get_secret_key() {
        $test_mode = (bool) get_option( 'sws_stripe_test_mode', 1 );
        return $test_mode
            ? get_option( 'sws_stripe_test_secret_key', '' )
            : get_option( 'sws_stripe_secret_key', '' );
    }

    /**
     * Get the active Stripe publishable key.
     *
     * @return string
     */
    public static function get_publishable_key() {
        $test_mode = (bool) get_option( 'sws_stripe_test_mode', 1 );
        return $test_mode
            ? get_option( 'sws_stripe_test_publishable_key', '' )
            : get_option( 'sws_stripe_publishable_key', '' );
    }

    /**
     * Check if Stripe is configured.
     *
     * @return bool
     */
    public static function is_configured() {
        return ! empty( self::get_secret_key() ) && ! empty( self::get_publishable_key() );
    }

    /**
     * Create a PaymentIntent for an event ticket purchase.
     *
     * @param float  $amount      Amount in major currency unit (e.g. 25.00).
     * @param string $currency    Currency code (default GBP).
     * @param string $customer_id Stripe customer ID (optional).
     * @param array  $metadata    Optional metadata.
     * @return array|WP_Error { id, client_secret } on success.
     */
    public static function create_payment_intent( $amount, $currency = 'GBP', $customer_id = '', $metadata = array() ) {
        $secret = self::get_secret_key();
        if ( empty( $secret ) ) {
            return new \WP_Error( 'stripe_not_configured', __( 'Stripe is not configured.', 'sws-members-club' ) );
        }

        // Stripe expects amount in smallest currency unit (pence for GBP).
        $amount_minor = (int) round( $amount * 100 );

        $body = array(
            'amount'               => $amount_minor,
            'currency'             => strtolower( $currency ),
            'automatic_payment_methods' => array( 'enabled' => 'true' ),
        );

        if ( ! empty( $customer_id ) ) {
            $body['customer'] = $customer_id;
        }

        if ( ! empty( $metadata ) ) {
            foreach ( $metadata as $key => $value ) {
                $body[ 'metadata[' . $key . ']' ] = (string) $value;
            }
        }

        $response = wp_remote_post( 'https://api.stripe.com/v1/payment_intents', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $secret,
            ),
            'body'    => $body,
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $data['error'] ) ) {
            return new \WP_Error(
                'stripe_error',
                $data['error']['message'] ?? __( 'Stripe payment error.', 'sws-members-club' )
            );
        }

        return array(
            'id'            => $data['id'],
            'client_secret' => $data['client_secret'],
        );
    }

    /**
     * Confirm a PaymentIntent has been paid (server-side check).
     *
     * @param string $payment_intent_id Stripe PaymentIntent ID.
     * @return bool True if payment succeeded.
     */
    public static function confirm_payment( $payment_intent_id ) {
        $secret = self::get_secret_key();
        if ( empty( $secret ) ) {
            return false;
        }

        $response = wp_remote_get( 'https://api.stripe.com/v1/payment_intents/' . $payment_intent_id, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $secret,
            ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return isset( $data['status'] ) && $data['status'] === 'succeeded';
    }

    /**
     * Refund a PaymentIntent (full refund).
     *
     * @param string $payment_intent_id Stripe PaymentIntent ID.
     * @return bool True on success.
     */
    public static function refund( $payment_intent_id ) {
        $secret = self::get_secret_key();
        if ( empty( $secret ) ) {
            return false;
        }

        $response = wp_remote_post( 'https://api.stripe.com/v1/refunds', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $secret,
            ),
            'body'    => array(
                'payment_intent' => $payment_intent_id,
            ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return isset( $data['id'] ) && ! isset( $data['error'] );
    }

    /**
     * Retrieve a Stripe subscription status.
     *
     * @param string $subscription_id Stripe Subscription ID.
     * @return string|false Subscription status or false on error.
     */
    public static function get_subscription_status( $subscription_id ) {
        $secret = self::get_secret_key();
        if ( empty( $secret ) ) {
            return false;
        }

        $response = wp_remote_get( 'https://api.stripe.com/v1/subscriptions/' . $subscription_id, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $secret,
            ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return $data['status'] ?? false;
    }
}
