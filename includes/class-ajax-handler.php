<?php
/**
 * Gestore Richieste AJAX e Endpoint REST
 *
 * @package WPInactivityLogoutPro
 * @since   1.0.3
 * @author  PeopleInside
 */

namespace WPInactivityLogoutPro;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AjaxHandler {

    const NONCE_ACTION = 'wpinact_session_nonce';

    public function init() {
        add_action( 'wp_ajax_wpinact_keepalive', [ $this, 'handle_keepalive_ajax' ] );
        add_action( 'wp_ajax_wpinact_logout_now', [ $this, 'handle_logout_ajax' ] );
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
    }

    public function register_rest_routes() {
        register_rest_route( 'wpinact/v1', '/keepalive', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_keepalive_rest' ],
            'permission_callback' => function() {
                return function_exists( 'is_user_logged_in' ) && is_user_logged_in();
            },
        ] );
    }

    public function handle_keepalive_ajax() {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ], 401 );
        }

        update_user_meta( $user_id, '_wpinact_last_activity', time() );

        wp_send_json_success( [
            'status'    => 'session_refreshed',
            'timestamp' => time(),
        ] );
    }

    public function handle_keepalive_rest( $request = null ) {
        $user_id = get_current_user_id();
        if ( $user_id ) {
            update_user_meta( $user_id, '_wpinact_last_activity', time() );
        }

        if ( function_exists( 'rest_ensure_response' ) ) {
            return rest_ensure_response( [
                'success'   => true,
                'status'    => 'session_refreshed',
                'timestamp' => time(),
            ] );
        }

        return [ 'success' => true ];
    }

    public function handle_logout_ajax() {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        $user_id = get_current_user_id();
        if ( $user_id ) {
            if ( function_exists( 'wp_logout' ) ) {
                wp_logout();
            }

            delete_user_meta( $user_id, '_wpinact_last_activity' );
        }

        wp_send_json_success( [
            'logged_out' => true,
            'redirect'   => add_query_arg( 'wpinact_status', 'inactivity_logout', wp_login_url() ),
        ] );
    }
}
