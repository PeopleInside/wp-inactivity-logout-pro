<?php
/**
 * Plugin Name: WP Inactivity Logout Pro
 * Plugin URI: https://github.com/PeopleInside/wp-inactivity-logout-pro
 * Description: Automatic inactivity logout with closed-tab server-side protection, customizable countdown warning popup, and full bilingual support (IT/EN).
 * Version: 1.0.5
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: PeopleInside
 * Author URI: https://peopleinside.it
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-inactivity-logout-pro
 * Domain Path: /languages
 */

namespace WPInactivityLogoutPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Impedisce l'accesso diretto
}

// Costanti principali del plugin
define( 'WPINACT_VERSION', '1.0.5' );
define( 'WPINACT_PLUGIN_FILE', __FILE__ );
define( 'WPINACT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPINACT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPINACT_TEXTDOMAIN', 'wp-inactivity-logout-pro' );

// --- INIZIALIZZAZIONE AGGIORNAMENTI AUTOMATICI DA GITHUB ---
// Richiede la libreria: https://github.com/YahnisElsts/plugin-update-checker
// Installala tramite Composer: composer require yahnis-elsts/plugin-update-checker
// Oppure scarica il repository e posizionalo in: vendor/plugin-update-checker/
if ( file_exists( WPINACT_PLUGIN_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php' ) ) {
	require_once WPINACT_PLUGIN_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
	
	$update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/PeopleInside/wp-inactivity-logout-pro',
		__FILE__,
		'wp-inactivity-logout-pro'
	);
	
	// Imposta il branch principale da cui controllare gli aggiornamenti
	$update_checker->setBranch('main');
}
// -------------------------------------------------------------

// Inclusione sicura dei moduli
$wpinact_includes = [
	WPINACT_PLUGIN_DIR . 'includes/class-inactivity-core.php',
	WPINACT_PLUGIN_DIR . 'includes/class-server-session-guard.php',
	WPINACT_PLUGIN_DIR . 'includes/class-admin-settings.php',
	WPINACT_PLUGIN_DIR . 'includes/class-ajax-handler.php',
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
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\run_plugin' );

/**
 * Attivazione sicura: registra le opzioni predefinite senza output per evitare errori di attivazione.
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
			'loggedout_notice' => 'Sei stato disconnesso
