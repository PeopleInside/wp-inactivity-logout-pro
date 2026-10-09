<?php
/**
 * Modulo di Protezione Server-Side a Scheda Chiusa
 *
 * @package WPInactivityLogoutPro
 * @since   1.0.3
 * @author  PeopleInside
 */

namespace WPInactivityLogoutPro;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ServerSessionGuard {

    const META_KEY_LAST_ACTIVITY = '_wpinact_last_activity';
    const THROTTLE_SECONDS       = 60; // Aggiorna il timestamp nel DB max 1 volta ogni 60s

    public function init() {
        // 'init' garantisce che l'ambiente WordPress e pluggable.php siano pronti
        add_action( 'init', [ $this, 'verify_session_inactivity' ], 1 );

        // Avviso personalizzato sulla schermata wp-login.php
        add_filter( 'login_message', [ $this, 'render_login_inactivity_notice' ] );

        // Registra il login iniziale
        add_action( 'wp_login', [ $this, 'on_user_login' ], 10, 2 );

        // Regola la scadenza massima dei cookie
        add_filter( 'auth_cookie_expiration', [ $this, 'filter_auth_cookie_expiration' ], 10, 3 );
    }

    /**
     * Quando un utente accede, registra il timestamp iniziale.
     */
    public function on_user_login( $user_login = '', $user = null ) {
        if ( is_object( $user ) && isset( $user->ID ) ) {
            update_user_meta( (int) $user->ID, self::META_KEY_LAST_ACTIVITY, time() );
        } elseif ( function_exists( 'get_current_user_id' ) ) {
            $uid = (int) get_current_user_id();
            if ( $uid > 0 ) {
                update_user_meta( $uid, self::META_KEY_LAST_ACTIVITY, time() );
            }
        }
    }

    /**
     * Verifica l'inattività dell'utente su ogni richiesta HTTP in ingresso.
     * Previene rigorosamente qualsiasi errore 500, redirect loop o eccezioni di classe non trovata.
     */
    public function verify_session_inactivity() {
        // Ignora processi cron o CLI in background
        if ( ( defined( 'DOING_CRON' ) && DOING_CRON ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
            return;
        }

        if ( ! function_exists( 'is_user_logged_in' ) || ! is_user_logged_in() ) {
            return;
        }

        $user_id = (int) get_current_user_id();
        if ( ! $user_id || $user_id <= 0 ) {
            return;
        }

        $options = Core::get_settings();
        if ( empty( $options['enable_closed_tab_guard'] ) ) {
            return;
        }

        $user = wp_get_current_user();
        if ( ! $user || ! Core::is_user_role_targeted( $user ) ) {
            return;
        }

        // Evita redirect loop se siamo già sulla pagina di login (wp-login.php)
        global $pagenow;
        if ( 'wp-login.php' === $pagenow || ( isset( $_SERVER['SCRIPT_NAME'] ) && false !== strpos( (string) $_SERVER['SCRIPT_NAME'], 'wp-login.php' ) ) ) {
            return;
        }

        $timeout_seconds = (int) ( ( $options['timeout_minutes'] ?? 30 ) * 60 );
        $last_activity   = (int) get_user_meta( $user_id, self::META_KEY_LAST_ACTIVITY, true );
        $current_time    = time();

        // Se l'utente non ha ancora un timestamp di attività registrato, inizializzalo adesso
        if ( $last_activity <= 0 ) {
            update_user_meta( $user_id, self::META_KEY_LAST_ACTIVITY, $current_time );
            return;
        }

        $elapsed = $current_time - $last_activity;

        // CONTROLLO CRITICO: Il tempo trascorso a scheda chiusa/inattività supera la soglia?
        if ( $elapsed > $timeout_seconds ) {
            // Distrugge la sessione con le API native e sicure di WordPress
            $this->destroy_user_session( $user_id );

            $is_ajax = function_exists( 'wp_doing_ajax' ) && wp_doing_ajax();
            $is_rest = ( defined( 'REST_REQUEST' ) && REST_REQUEST )
                || ( isset( $_SERVER['REQUEST_URI'] ) && false !== strpos( (string) $_SERVER['REQUEST_URI'], '/wp-json/' ) )
                || isset( $_GET['rest_route'] );

            // Se è una richiesta AJAX o REST, rispondi con JSON 401 pulito invece di fare redirect
            if ( $is_ajax || $is_rest ) {
                $locale = function_exists( 'determine_locale' ) ? (string) determine_locale() : 'en_US';
                $is_it  = ( 0 === strpos( strtolower( (string) $locale ), 'it' ) );
                $msg    = $is_it
                    ? 'La sessione è scaduta per inattività prolungata.'
                    : 'Session expired due to prolonged inactivity.';

                wp_send_json_error( [
                    'code'    => 'session_timeout_closed_tab',
                    'message' => $msg,
                ], 401 );
                exit;
            }

            // Calcolo dell'URL di destinazione in base alla configurazione
            $redirect_type = $options['redirect_type'] ?? 'login_notice';
            $redirect_url  = '';

            if ( 'custom_url' === $redirect_type && ! empty( $options['custom_redirect_url'] ) ) {
                $redirect_url = esc_url_raw( $options['custom_redirect_url'] );
            } elseif ( 'front_page' === $redirect_type ) {
                $redirect_url = add_query_arg( [ 'wpinact_status' => 'closed_tab_expired' ], home_url( '/' ) );
            } else {
                $redirect_url = add_query_arg(
                    [ 'wpinact_status' => 'closed_tab_expired' ],
                    wp_login_url()
                );
            }

            // Fallback robusto se gli header HTTP sono già stati inviati
            if ( headers_sent() ) {
                echo '<meta http-equiv="refresh" content="0;url=' . esc_url( $redirect_url ) . '">';
                echo '<script>window.location.href=' . json_encode( $redirect_url ) . ';</script>';
                exit;
            }

            if ( 'custom_url' === $redirect_type ) {
                wp_redirect( $redirect_url, 302 );
            } else {
                wp_safe_redirect( $redirect_url );
            }
            exit;
        }

        // Throttling di scrittura DB: aggiorna il timestamp solo dopo 60 secondi
        if ( $elapsed >= self::THROTTLE_SECONDS ) {
            update_user_meta( $user_id, self::META_KEY_LAST_ACTIVITY, $current_time );
        }
    }

    /**
     * Distrugge in modo sicuro la sessione e pulisce i cookie di autenticazione.
     * Utilizza le funzioni native di WordPress in modo completamente immune da errori 500.
     */
    private function destroy_user_session( $user_id ) {
        // wp_logout() nativo distrugge il token di sessione, resetta i cookie e azzera current_user
        if ( function_exists( 'wp_logout' ) ) {
            wp_logout();
        } else {
            if ( function_exists( 'wp_destroy_current_session' ) ) {
                wp_destroy_current_session();
            }
            if ( function_exists( 'wp_clear_auth_cookie' ) ) {
                wp_clear_auth_cookie();
            }
            if ( function_exists( 'wp_set_current_user' ) ) {
                wp_set_current_user( 0 );
            }
        }

        if ( $user_id > 0 ) {
            delete_user_meta( (int) $user_id, self::META_KEY_LAST_ACTIVITY );
        }
    }

    /**
     * Filtra la durata dei cookie di autenticazione in modo sicuro per PHP 8.5.
     */
    public function filter_auth_cookie_expiration( $expiration, $user_id = 0, $remember = false ) {
        $options = Core::get_settings();
        if ( ! empty( $options['enable_closed_tab_guard'] ) ) {
            $timeout_seconds = (int) ( ( $options['timeout_minutes'] ?? 30 ) * 60 );
            if ( $timeout_seconds > 0 ) {
                return (int) max( $timeout_seconds, 1800 );
            }
        }
        return (int) $expiration;
    }

    /**
     * Mostra l'avviso sulla schermata di login quando l'utente viene reindirizzato.
     */
    public function render_login_inactivity_notice( $message = '' ) {
        $status = $_GET['wpinact_status'] ?? '';
        if ( ! in_array( $status, [ 'closed_tab_expired', 'inactivity_logout' ], true ) ) {
            return (string) $message;
        }

        $options = Core::get_settings();
        $locale  = function_exists( 'determine_locale' ) ? (string) determine_locale() : 'en_US';
        $lang    = ( 0 === strpos( strtolower( (string) $locale ), 'it' ) ) ? 'it' : 'en';

        if ( 'closed_tab_expired' === $status ) {
            $default_fallback = ( 'it' === $lang )
                ? 'La tua sessione precedente è scaduta mentre la scheda era chiusa.'
                : 'Your previous session expired while your browser tab was closed.';

            $notice_text = ! empty( $options["strings_{$lang}"]['closed_tab_notice'] )
                ? $options["strings_{$lang}"]['closed_tab_notice']
                : $default_fallback;
        } else {
            $default_fallback = ( 'it' === $lang )
                ? 'Sei stato disconnesso per inattività prolungata.'
                : 'You have been logged out due to extended inactivity.';

            $notice_text = ! empty( $options["strings_{$lang}"]['loggedout_notice'] )
                ? $options["strings_{$lang}"]['loggedout_notice']
                : $default_fallback;
        }

        $title = ( 'it' === $lang ) ? 'Avviso di Sicurezza' : 'Security Notice';

        $notice_html = sprintf(
            '<div class="notice notice-warning message" style="border-left-color: #d63638;"><p><strong>%s:</strong> %s</p></div>',
            esc_html( $title ),
            esc_html( $notice_text )
        );

        return $notice_html . (string) $message;
    }
}
