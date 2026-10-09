<?php
/**
 * Plugin Name: WP Inactivity Logout Pro
 * Plugin URI: https://github.com/PeopleInside/wp-inactivity-logout-pro
 * Description: Automatic inactivity logout with closed-tab server-side protection, customizable countdown warning popup, and full bilingual support (IT/EN).
 * Version: 1.0.6
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: PeopleInside
 * Author URI: https://github.com/PeopleInside
 * License: MIT License
 * License URI: https://github.com/PeopleInside/wp-inactivity-logout-pro/blob/main/LICENSE
 * Text Domain: wp-inactivity-logout-pro
 * Domain Path: /languages
 */

namespace WPInactivityLogoutPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Impedisce l'accesso diretto
}

// Costanti principali del plugin
define( 'WPINACT_VERSION', '1.0.6' );
define( 'WPINACT_PLUGIN_FILE', __FILE__ );
define( 'WPINACT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPINACT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPINACT_TEXTDOMAIN', 'wp-inactivity-logout-pro' );

// Inclusione sicura dei moduli
$wpinact_includes = [
	WPINACT_PLUGIN_DIR . 'includes/class-inactivity-core.php',
	WPINACT_PLUGIN_DIR . 'includes/class-server-session-guard.php',
	WPINACT_PLUGIN_DIR . 'includes/class-admin-settings.php',
	WPINACT_PLUGIN_DIR . 'includes/class-ajax-handler.php',
	WPINACT_PLUGIN_DIR . 'includes/class-updater.php', // <-- AGGIUNTO: Meccanismo di aggiornamento nativo
];

foreach ( $wpinact_includes as $wpinact_inc ) {
	if ( file_exists( $wpinact_inc ) ) {
		require_once $wpinact_inc;
	}
}

/**
 * Inizializza il plugin principale sul hook plugins_loaded.
 */
function run_plugin() {
	if ( class_exists( __NAMESPACE__ . '\Core' ) ) {
		$core = new Core();
		$core->init();
	}
	if ( class_exists( __NAMESPACE__ . '\ServerSessionGuard' ) ) {
		$server_guard = new ServerSessionGuard();
		$server_guard->init();
	}
	if ( is_admin() && class_exists( __NAMESPACE__ . '\AdminSettings' ) ) {
		$admin_settings = new AdminSettings();
		$admin_settings->init();
	}
	if ( class_exists( __NAMESPACE__ . '\AjaxHandler' ) ) {
		$ajax_handler = new AjaxHandler();
		$ajax_handler->init();
	}

	/*
	 * L'updater NON va limitato a wp-admin.
	 * Gli aggiornamenti automatici girano dentro wp-cron.php (e WP-CLI): 
	 * in entrambi i contesti is_admin() vale false. Se non viene istanziato 
	 * qui, il transient degli aggiornamenti viene sovrascritto e l'auto-update 
	 * fallisce silenziosamente.
	 */
	if ( is_admin() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		if ( class_exists( __NAMESPACE__ . '\Updater' ) ) {
			Updater::instance();
		}
	}
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\run_plugin' );

/**
 * Attivazione sicura: registra le opzioni predefinite.
 */
register_activation_hook( __FILE__, function() {
	$default_options = [
		'timeout_minutes'          => 30,
		'warning_minutes'          => 15,
		'enable_closed_tab_guard'  => true,
		'enforce_area'             => 'all',
		'selected_roles'           => ["administrator", "editor", "author", "shop_manager", "subscriber"],
		'redirect_type'            => 'login_notice',
		'custom_redirect_url'      => '',
		'enable_audio_alert'       => true,
		'enable_multi_tab_sync'    => true,
		'strings_it'               => [
			'modal_title'      => 'Sessione in scadenza per inattività',
			'modal_message'    => 'Non abbiamo rilevato alcuna attività di recente. Per motivi di sicurezza, verrai disconnesso automaticamente.',
			'countdown_label'  => 'Disconnessione automatica tra:',
			'btn_stay_login'   => 'Rimani connesso',
			'btn_logout_now'   => 'Disconnettiti ora',
			'loggedout_notice' => 'Sei stato disconnesso per inattività prolungata.',
			'closed_tab_notice'=> 'La tua sessione precedente è scaduta mentre la scheda era chiusa.',
		],
		'strings_en'               => [
			'modal_title'      => 'Session Expiring Due to Inactivity',
			'modal_message'    => 'We have not detected any activity recently. For your security, you will be logged out automatically.',
			'countdown_label'  => 'Automatic logout in:',
			'btn_stay_login'   => 'Stay Logged In',
			'btn_logout_now'   => 'Log Out Now',
			'loggedout_notice' => 'You have been logged out due to extended inactivity.',
			'closed_tab_notice'=> 'Your previous session expired while your browser tab was closed.',
		],
	];

	if ( ! get_option( 'wpinact_settings' ) ) {
		update_option( 'wpinact_settings', $default_options );
	}
} );

/**
 * Deattivazione pulita.
 */
register_deactivation_hook( __FILE__, function() {
	// Mantiene le impostazioni salvate
} );
