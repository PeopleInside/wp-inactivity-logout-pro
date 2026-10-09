<?php
/**
 * Core Controller del Plugin
 *
 * @package WPInactivityLogoutPro
 * @since   1.0.3
 * @author  PeopleInside
 */

namespace WPInactivityLogoutPro;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Core {

    public function init() {
        add_action( 'init', [ $this, 'load_textdomain' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function load_textdomain() {
        load_plugin_textdomain(
            WPINACT_TEXTDOMAIN,
            false,
            dirname( plugin_basename( WPINACT_PLUGIN_FILE ) ) . '/languages'
        );
    }

    public function enqueue_assets() {
        if ( ! function_exists( 'is_user_logged_in' ) || ! is_user_logged_in() ) {
            return;
        }

        $user = wp_get_current_user();
        if ( ! self::is_user_role_targeted( $user ) ) {
            return;
        }

        $settings = self::get_settings();
        $area     = $settings['enforce_area'] ?? 'all';

        if ( 'admin_only' === $area && ! is_admin() ) {
            return;
        }
        if ( 'frontend_only' === $area && is_admin() ) {
            return;
        }

        wp_enqueue_style(
            'wpinact-modal-css',
            WPINACT_PLUGIN_URL . 'assets/css/inactivity-modal.css',
            [],
            WPINACT_VERSION
        );

        wp_enqueue_script(
            'wpinact-tracker-js',
            WPINACT_PLUGIN_URL . 'assets/js/inactivity-tracker.js',
            [],
            WPINACT_VERSION,
            true
        );

        // Rileva la lingua di WordPress
        $locale = function_exists( 'determine_locale' ) ? (string) determine_locale() : ( function_exists( 'get_locale' ) ? (string) get_locale() : 'en_US' );
        $lang   = ( 0 === strpos( strtolower( (string) $locale ), 'it' ) ) ? 'it' : 'en';

        // Prende i messaggi personalizzati della lingua attiva, con fallback
        $strings = ! empty( $settings["strings_{$lang}"] ) ? $settings["strings_{$lang}"] : ( 'it' === $lang ? $settings['strings_it'] : $settings['strings_en'] );

        $timeout_minutes = max( 1, (int) ( $settings['timeout_minutes'] ?? 30 ) );
        $raw_warning     = isset( $settings['warning_minutes'] ) ? (int) $settings['warning_minutes'] : 15;

        // Se warning_minutes è 0, l'avviso popup è DISATTIVATO.
        // Se è > 0, deve essere strettamente inferiore al timeout totale di logout.
        if ( $raw_warning <= 0 ) {
            $warning_minutes = 0;
            $enable_modal    = false;
            $countdown_diff  = 0;
        } else {
            $warning_minutes = min( $timeout_minutes - 1, max( 1, $raw_warning ) );
            $enable_modal    = true;
            $countdown_diff  = max( 1, $timeout_minutes - $warning_minutes );
        }

        wp_localize_script( 'wpinact-tracker-js', 'wpInactivityData', [
            'locale'              => $locale,
            'lang'                => $lang,
            'timeoutMs'           => $timeout_minutes * 60 * 1000,
            'warningMs'           => $warning_minutes * 60 * 1000,
            'countdownDurationMs' => $countdown_diff * 60 * 1000,
            'enableWarningModal'  => $enable_modal,
            'enableMultiTabSync'  => ! empty( $settings['enable_multi_tab_sync'] ),
            'enableAudioAlert'    => ! empty( $settings['enable_audio_alert'] ),
            'ajaxUrl'             => admin_url( 'admin-ajax.php' ),
            'restUrl'             => esc_url_raw( rest_url( 'wpinact/v1/keepalive' ) ),
            'logoutUrl'           => wp_logout_url( add_query_arg( 'wpinact_status', 'inactivity_logout', wp_login_url() ) ),
            'nonce'               => wp_create_nonce( 'wpinact_session_nonce' ),
            'strings'             => [
                'modalTitle'     => esc_html( $strings['modal_title'] ?? '' ),
                'modalMessage'   => esc_html( $strings['modal_message'] ?? '' ),
                'countdownLabel' => esc_html( $strings['countdown_label'] ?? '' ),
                'btnStayLogin'   => esc_html( $strings['btn_stay_login'] ?? '' ),
                'btnLogoutNow'   => esc_html( $strings['btn_logout_now'] ?? '' ),
            ],
        ] );
    }

    public static function get_settings() {
        $defaults = [
            'timeout_minutes'        => 30,
            'warning_minutes'        => 15,
            'enable_closed_tab_guard'=> true,
            'enforce_area'           => 'all',
            'selected_roles'         => [ 'administrator', 'editor', 'author', 'shop_manager', 'subscriber' ],
            'redirect_type'          => 'login_notice',
            'custom_redirect_url'    => '',
            'enable_audio_alert'     => true,
            'enable_multi_tab_sync'  => true,
            'strings_it'             => [
                'modal_title'      => 'Sessione in scadenza per inattività',
                'modal_message'    => 'Non abbiamo rilevato alcuna attività di recente. Per motivi di sicurezza, verrai disconnesso automaticamente.',
                'countdown_label'  => 'Disconnessione automatica tra:',
                'btn_stay_login'   => 'Rimani connesso',
                'btn_logout_now'   => 'Disconnettiti ora',
                'loggedout_notice' => 'Sei stato disconnesso per inattività prolungata.',
                'closed_tab_notice'=> 'La tua sessione precedente è scaduta mentre la scheda era chiusa.',
            ],
            'strings_en'             => [
                'modal_title'      => 'Session Expiring Due to Inactivity',
                'modal_message'    => 'We have not detected any activity recently. For your security, you will be logged out automatically.',
                'countdown_label'  => 'Automatic logout in:',
                'btn_stay_login'   => 'Stay Logged In',
                'btn_logout_now'   => 'Log Out Now',
                'loggedout_notice' => 'You have been logged out due to extended inactivity.',
                'closed_tab_notice'=> 'Your previous session expired while your browser tab was closed.',
            ],
        ];

        $saved = get_option( 'wpinact_settings', [] );
        return wp_parse_args( $saved, $defaults );
    }

    public static function is_user_role_targeted( $user = null ) {
        if ( ! is_object( $user ) || empty( $user->roles ) || ! is_array( $user->roles ) ) {
            return false;
        }

        $settings = self::get_settings();
        $targeted = $settings['selected_roles'] ?? [];

        if ( empty( $targeted ) || ! is_array( $targeted ) ) {
            return true;
        }

        foreach ( $user->roles as $role ) {
            if ( in_array( $role, $targeted, true ) ) {
                return true;
            }
        }

        return false;
    }
}
