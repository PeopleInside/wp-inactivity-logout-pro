<?php
/**
 * Gestione Pagina Impostazioni in WordPress Admin
 *
 * @package WPInactivityLogoutPro
 * @since   1.0.3
 * @author  PeopleInside
 */

namespace WPInactivityLogoutPro;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AdminSettings {

    const OPTION_NAME = 'wpinact_settings';
    const PAGE_SLUG   = 'wp-inactivity-logout-pro';

    public function init() {
        add_action( 'admin_menu', [ $this, 'register_menu_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_filter( 'plugin_action_links_' . plugin_basename( WPINACT_PLUGIN_FILE ), [ $this, 'add_action_links' ] );
    }

    public function register_menu_page() {
        $locale = function_exists( 'determine_locale' ) ? (string) determine_locale() : 'en_US';
        $is_it  = ( 0 === strpos( strtolower( (string) $locale ), 'it' ) );

        $page_title = $is_it
            ? 'Inactivity Logout Pro - Impostazioni'
            : 'Inactivity Logout Pro - Settings';

        $menu_title = 'Inactivity Logout';

        add_options_page(
            $page_title,
            $menu_title,
            'manage_options',
            self::PAGE_SLUG,
            [ $this, 'render_settings_page' ]
        );
    }

    public function register_settings() {
        register_setting(
            'wpinact_settings_group',
            self::OPTION_NAME,
            [
                'type'              => 'array',
                'sanitize_callback' => [ $this, 'sanitize_settings' ],
                'default'           => [],
            ]
        );
    }

    public function add_action_links( $links ) {
        if ( ! is_array( $links ) ) {
            $links = [];
        }

        $locale = function_exists( 'determine_locale' ) ? (string) determine_locale() : 'en_US';
        $is_it  = ( 0 === strpos( strtolower( (string) $locale ), 'it' ) );
        $label  = $is_it ? 'Impostazioni' : 'Settings';

        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) ),
            esc_html( $label )
        );
        array_unshift( $links, $settings_link );
        return $links;
    }

    public function sanitize_settings( $input ) {
        if ( ! is_array( $input ) ) {
            return [];
        }

        $clean = [];

        $timeout = absint( $input['timeout_minutes'] ?? 30 );
        $clean['timeout_minutes'] = max( 1, min( 1440, $timeout ) );

        // Avviso popup: con 0 è DISATTIVATO. Se > 0, deve essere inferiore al timeout totale di almeno 1 minuto.
        $raw_warning = isset( $input['warning_minutes'] ) ? absint( $input['warning_minutes'] ) : 15;
        if ( 0 === $raw_warning ) {
            $clean['warning_minutes'] = 0;
        } else {
            if ( $clean['timeout_minutes'] <= 1 ) {
                $clean['warning_minutes'] = 0;
                add_settings_error(
                    'wpinact_settings',
                    'wpinact_warning_adjusted',
                    __( "Con un tempo totale di logout di 1 minuto, l'avviso popup è stato impostato a 0 (disattivato).", 'wp-inactivity-logout-pro' ),
                    'warning'
                );
            } elseif ( $raw_warning >= $clean['timeout_minutes'] ) {
                $raw_warning = max( 1, $clean['timeout_minutes'] - 1 );
                add_settings_error(
                    'wpinact_settings',
                    'wpinact_warning_adjusted',
                    __( "L'avviso popup deve avere un valore inferiore al tempo totale di logout di almeno 1 minuto. È stato regolato automaticamente (oppure imposta 0 per disattivarlo del tutto).", 'wp-inactivity-logout-pro' ),
                    'warning'
                );
                $clean['warning_minutes'] = $raw_warning;
            } else {
                $clean['warning_minutes'] = $raw_warning;
            }
        }

        $clean['enable_closed_tab_guard'] = ! empty( $input['enable_closed_tab_guard'] );
        $clean['enable_audio_alert']      = ! empty( $input['enable_audio_alert'] );
        $clean['enable_multi_tab_sync']   = ! empty( $input['enable_multi_tab_sync'] );

        $valid_areas = [ 'all', 'admin_only', 'frontend_only' ];
        $clean['enforce_area'] = in_array( $input['enforce_area'] ?? 'all', $valid_areas, true )
            ? $input['enforce_area']
            : 'all';

        $clean['selected_roles'] = [];
        $wp_roles_obj = function_exists( 'wp_roles' ) ? wp_roles() : null;
        $editable_roles = ( $wp_roles_obj && isset( $wp_roles_obj->roles ) ) ? array_keys( $wp_roles_obj->roles ) : [];

        if ( ! empty( $input['selected_roles'] ) && is_array( $input['selected_roles'] ) ) {
            foreach ( $input['selected_roles'] as $role ) {
                if ( in_array( $role, $editable_roles, true ) ) {
                    $clean['selected_roles'][] = sanitize_key( $role );
                }
            }
        }

        foreach ( [ 'it', 'en' ] as $lang ) {
            $source = $input["strings_{$lang}"] ?? [];
            $clean["strings_{$lang}"] = [
                'modal_title'      => sanitize_text_field( $source['modal_title'] ?? '' ),
                'modal_message'    => sanitize_textarea_field( $source['modal_message'] ?? '' ),
                'countdown_label'  => sanitize_text_field( $source['countdown_label'] ?? '' ),
                'btn_stay_login'   => sanitize_text_field( $source['btn_stay_login'] ?? '' ),
                'btn_logout_now'   => sanitize_text_field( $source['btn_logout_now'] ?? '' ),
                'loggedout_notice' => sanitize_text_field( $source['loggedout_notice'] ?? '' ),
                'closed_tab_notice'=> sanitize_text_field( $source['closed_tab_notice'] ?? '' ),
            ];
        }

        return $clean;
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized access.', 'wp-inactivity-logout-pro' ) );
        }

        $settings = Core::get_settings();
        $locale   = function_exists( 'determine_locale' ) ? (string) determine_locale() : 'en_US';
        $is_it    = ( 0 === strpos( strtolower( (string) $locale ), 'it' ) );

        $wp_roles_obj = function_exists( 'wp_roles' ) ? wp_roles() : null;
        $roles        = ( $wp_roles_obj && isset( $wp_roles_obj->roles ) ) ? $wp_roles_obj->roles : [];

        $it = $settings['strings_it'] ?? [];
        $en = $settings['strings_en'] ?? [];
        ?>
        <div class="wrap wpinact-settings-wrap">
            <h1 style="display:flex;align-items:center;gap:10px;margin-bottom:15px;">
                <span class="dashicons dashicons-shield" style="font-size:32px;width:32px;height:32px;color:#2271b1;"></span>
                <span><?php echo $is_it ? 'WP Inactivity Logout Pro - Impostazioni' : 'WP Inactivity Logout Pro - Settings'; ?></span>
            </h1>

            <div class="notice notice-info inline" style="margin: 15px 0 20px;">
                <p>
                    <strong><?php echo $is_it ? 'Lingua WordPress rilevata:' : 'Detected WordPress Language:'; ?></strong>
                    <?php echo $is_it ? 'Italiano (' . esc_html( $locale ) . ')' : 'English (' . esc_html( $locale ) . ')'; ?>
                    &bull; <em><?php echo $is_it ? "I messaggi e l'interfaccia si adattano automaticamente." : "Messages and interface adapt automatically."; ?></em>
                </p>
            </div>

            <!-- TAB HEADERS CON GESTIONE JAVASCRIPT -->
            <h2 class="nav-tab-wrapper wpinact-nav-tabs">
                <a href="#wpinact-tab-general" class="nav-tab nav-tab-active" data-tab="wpinact-tab-general">
                    <span class="dashicons dashicons-clock" style="vertical-align:text-top;margin-right:3px;"></span>
                    <?php echo $is_it ? 'Generale &amp; Timeout' : 'General &amp; Timeout'; ?>
                </a>
                <a href="#wpinact-tab-closedtab" class="nav-tab" data-tab="wpinact-tab-closedtab">
                    <span class="dashicons dashicons-lock" style="vertical-align:text-top;margin-right:3px;"></span>
                    <?php echo $is_it ? 'Scheda Chiusa (Server-Side)' : 'Closed Tab (Server-Side)'; ?>
                </a>
                <a href="#wpinact-tab-messages-it" class="nav-tab" data-tab="wpinact-tab-messages-it">
                    <span class="dashicons dashicons-translation" style="vertical-align:text-top;margin-right:3px;"></span>
                    <?php echo $is_it ? 'Messaggi Popup (Italiano)' : 'Popup Messages (Italian)'; ?>
                    <?php if ( $is_it ) : ?><span style="background:#00a32a;color:#fff;font-size:10px;padding:1px 6px;border-radius:10px;margin-left:4px;">Attivo</span><?php endif; ?>
                </a>
                <a href="#wpinact-tab-messages-en" class="nav-tab" data-tab="wpinact-tab-messages-en">
                    <span class="dashicons dashicons-translation" style="vertical-align:text-top;margin-right:3px;"></span>
                    <?php echo $is_it ? 'Messaggi Popup (Inglese)' : 'Popup Messages (English)'; ?>
                    <?php if ( ! $is_it ) : ?><span style="background:#00a32a;color:#fff;font-size:10px;padding:1px 6px;border-radius:10px;margin-left:4px;">Active</span><?php endif; ?>
                </a>
                <a href="#wpinact-tab-roles" class="nav-tab" data-tab="wpinact-tab-roles">
                    <span class="dashicons dashicons-groups" style="vertical-align:text-top;margin-right:3px;"></span>
                    <?php echo $is_it ? 'Ruoli Utente' : 'User Roles'; ?>
                </a>
            </h2>

            <form method="post" action="options.php" style="margin-top:20px;">
                <?php settings_fields( 'wpinact_settings_group' ); ?>

                <!-- PANNELLO 1: GENERALE -->
                <div id="wpinact-tab-general" class="wpinact-tab-panel">
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row">
                                <label for="timeout_minutes">
                                    <?php echo $is_it ? 'Tempo Totale di Inattività (Logout)' : 'Total Inactivity Timeout (Logout)'; ?>
                                </label>
                            </th>
                            <td>
                                <input type="number" id="timeout_minutes" name="wpinact_settings[timeout_minutes]" value="<?php echo esc_attr( (string) $settings['timeout_minutes'] ); ?>" min="1" max="1440" step="1" class="small-text" />
                                <span><?php echo $is_it ? 'minuti (Es. 30)' : 'minutes (e.g. 30)'; ?></span>
                                <p class="description">
                                    <?php echo $is_it ? 'Tempo complessivo dopo il quale la sessione utente viene terminata.' : 'Total duration before the session is completely terminated.'; ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="warning_minutes">
                                    <?php echo $is_it ? 'Avviso e Conteggio Popup dopo' : 'Warning and Countdown Popup after'; ?>
                                </label>
                            </th>
                            <td>
                                <input type="number" id="warning_minutes" name="wpinact_settings[warning_minutes]" value="<?php echo esc_attr( (string) $settings['warning_minutes'] ); ?>" min="0" max="<?php echo max( 0, (int) $settings['timeout_minutes'] - 1 ); ?>" step="1" class="small-text" />
                                <span><?php echo $is_it ? 'minuti di inattività (Es. 15, oppure 0 per disattivare)' : 'minutes of inactivity (e.g. 15, or 0 to disable)'; ?></span>
                                <p class="description" id="wpinact-warning-description">
                                    <?php
                                    if ( (int) $settings['warning_minutes'] === 0 ) {
                                        echo $is_it
                                            ? '<span style="color:#2271b1;font-weight:600;">Avviso popup disattivato (0):</span> L&apos;utente verra disconnesso direttamente al raggiungimento del tempo di logout senza mostrare alcun popup di avviso.'
                                            : '<span style="color:#2271b1;font-weight:600;">Warning popup disabled (0):</span> User will be logged out directly upon timeout without any modal popup.';
                                    } else {
                                        $diff = max( 1, (int) $settings['timeout_minutes'] - (int) $settings['warning_minutes'] );
                                        if ( $is_it ) {
                                            printf(
                                                'Con %d minuti di logout e avviso a %d minuti, il popup mostrera un conteggio di %d minuti rimanenti prima del logout. <em>(Imposta 0 per disattivare l&apos;avviso popup)</em>.',
                                                (int) $settings['timeout_minutes'],
                                                (int) $settings['warning_minutes'],
                                                (int) $diff
                                            );
                                        } else {
                                            printf(
                                                'With %d m logout and warning at %d m, popup will countdown %d minutes remaining. <em>(Set 0 to disable the warning popup)</em>.',
                                                (int) $settings['timeout_minutes'],
                                                (int) $settings['warning_minutes'],
                                                (int) $diff
                                            );
                                        }
                                    }
                                    ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php echo $is_it ? 'Ambito di Applicazione' : 'Enforcement Scope'; ?></th>
                            <td>
                                <fieldset>
                                    <label style="display:block;margin-bottom:6px;">
                                        <input type="radio" name="wpinact_settings[enforce_area]" value="all" <?php checked( ( $settings['enforce_area'] ?? 'all' ) === 'all' ); ?> />
                                        <strong><?php echo $is_it ? 'Tutto il Sito (Admin + Frontend)' : 'Entire Site (Admin + Frontend)'; ?></strong>
                                    </label>
                                    <label style="display:block;margin-bottom:6px;">
                                        <input type="radio" name="wpinact_settings[enforce_area]" value="admin_only" <?php checked( ( $settings['enforce_area'] ?? '' ) === 'admin_only' ); ?> />
                                        <?php echo $is_it ? 'Solo Bacheca wp-admin' : 'wp-admin Dashboard Only'; ?>
                                    </label>
                                    <label style="display:block;">
                                        <input type="radio" name="wpinact_settings[enforce_area]" value="frontend_only" <?php checked( ( $settings['enforce_area'] ?? '' ) === 'frontend_only' ); ?> />
                                        <?php echo $is_it ? 'Solo Frontend' : 'Frontend Only'; ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php echo $is_it ? 'Sincronizzazione Multi-Scheda' : 'Multi-Tab Synchronization'; ?></th>
                            <td>
                                <label for="enable_multi_tab_sync">
                                    <input type="checkbox" id="enable_multi_tab_sync" name="wpinact_settings[enable_multi_tab_sync]" value="1" <?php checked( ! empty( $settings['enable_multi_tab_sync'] ) ); ?> />
                                    <?php echo $is_it ? 'Sincronizza l’attività tra tutte le schede aperte via BroadcastChannel' : 'Synchronize activity across all open browser tabs via BroadcastChannel'; ?>
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php echo $is_it ? 'Avviso Acustico' : 'Audio Alert'; ?></th>
                            <td>
                                <label for="enable_audio_alert">
                                    <input type="checkbox" id="enable_audio_alert" name="wpinact_settings[enable_audio_alert]" value="1" <?php checked( ! empty( $settings['enable_audio_alert'] ) ); ?> />
                                    <?php echo $is_it ? 'Riproduci un breve segnale acustico alla comparsa del popup' : 'Play a discreet audio chime when the warning popup appears'; ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- PANNELLO 2: SCHEDA CHIUSA -->
                <div id="wpinact-tab-closedtab" class="wpinact-tab-panel" style="display:none;">
                    <div style="background:#fff;border-left:4px solid #00a32a;padding:15px;margin-bottom:20px;box-shadow:0 1px 1px rgba(0,0,0,.04);">
                        <h3 style="margin-top:0;"><?php echo $is_it ? 'Protezione Server-Side a Scheda Chiusa' : 'Closed Tab Server-Side Protection'; ?></h3>
                        <p style="margin-bottom:0;">
                            <?php echo $is_it
                                ? 'Se un utente chiude la scheda o spegne il computer e ritorna dopo il limite impostato, la sessione viene distrutta immediatamente lato server, cancellando i cookie di autenticazione e revocando il token di sessione.'
                                : 'If a user closes their browser tab or laptop and returns after the timeout, the session is destroyed immediately server-side, clearing auth cookies and revoking the session token.'; ?>
                        </p>
                    </div>

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php echo $is_it ? 'Stato Protezione' : 'Protection Status'; ?></th>
                            <td>
                                <label for="enable_closed_tab_guard">
                                    <input type="checkbox" id="enable_closed_tab_guard" name="wpinact_settings[enable_closed_tab_guard]" value="1" <?php checked( ! empty( $settings['enable_closed_tab_guard'] ) ); ?> />
                                    <strong><?php echo $is_it ? 'Attiva il controllo della sessione a scheda chiusa' : 'Enable closed-tab session validation'; ?></strong>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- PANNELLO 3: MESSAGGI ITALIANO -->
                <div id="wpinact-tab-messages-it" class="wpinact-tab-panel" style="display:none;">
                    <h3>Messaggi Popup (Italiano)</h3>
                    <p class="description">Testi visualizzati quando la lingua attiva di WordPress è l'Italiano.</p>

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label>Titolo Popup</label></th>
                            <td><input type="text" name="wpinact_settings[strings_it][modal_title]" value="<?php echo esc_attr( $it['modal_title'] ?? '' ); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label>Messaggio Principale</label></th>
                            <td><textarea name="wpinact_settings[strings_it][modal_message]" rows="3" class="large-text"><?php echo esc_textarea( $it['modal_message'] ?? '' ); ?></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row"><label>Etichetta Conteggio</label></th>
                            <td><input type="text" name="wpinact_settings[strings_it][countdown_label]" value="<?php echo esc_attr( $it['countdown_label'] ?? '' ); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label>Testo Pulsante "Rimani Connesso"</label></th>
                            <td><input type="text" name="wpinact_settings[strings_it][btn_stay_login]" value="<?php echo esc_attr( $it['btn_stay_login'] ?? '' ); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label>Testo Pulsante "Disconnettiti Ora"</label></th>
                            <td><input type="text" name="wpinact_settings[strings_it][btn_logout_now]" value="<?php echo esc_attr( $it['btn_logout_now'] ?? '' ); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label>Avviso Scheda Chiusa (Login)</label></th>
                            <td><input type="text" name="wpinact_settings[strings_it][closed_tab_notice]" value="<?php echo esc_attr( $it['closed_tab_notice'] ?? '' ); ?>" class="large-text" /></td>
                        </tr>
                    </table>
                </div>

                <!-- PANNELLO 4: MESSAGGI INGLESE -->
                <div id="wpinact-tab-messages-en" class="wpinact-tab-panel" style="display:none;">
                    <h3>Custom Popup Messages (English)</h3>
                    <p class="description">Messages displayed when the active WordPress language is English.</p>

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label>Popup Title</label></th>
                            <td><input type="text" name="wpinact_settings[strings_en][modal_title]" value="<?php echo esc_attr( $en['modal_title'] ?? '' ); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label>Main Message</label></th>
                            <td><textarea name="wpinact_settings[strings_en][modal_message]" rows="3" class="large-text"><?php echo esc_textarea( $en['modal_message'] ?? '' ); ?></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row"><label>Countdown Label</label></th>
                            <td><input type="text" name="wpinact_settings[strings_en][countdown_label]" value="<?php echo esc_attr( $en['countdown_label'] ?? '' ); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label>"Stay Logged In" Button</label></th>
                            <td><input type="text" name="wpinact_settings[strings_en][btn_stay_login]" value="<?php echo esc_attr( $en['btn_stay_login'] ?? '' ); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label>"Log Out Now" Button</label></th>
                            <td><input type="text" name="wpinact_settings[strings_en][btn_logout_now]" value="<?php echo esc_attr( $en['btn_logout_now'] ?? '' ); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label>Closed Tab Notice (Login)</label></th>
                            <td><input type="text" name="wpinact_settings[strings_en][closed_tab_notice]" value="<?php echo esc_attr( $en['closed_tab_notice'] ?? '' ); ?>" class="large-text" /></td>
                        </tr>
                    </table>
                </div>

                <!-- PANNELLO 5: RUOLI UTENTE -->
                <div id="wpinact-tab-roles" class="wpinact-tab-panel" style="display:none;">
                    <h3><?php echo $is_it ? 'Ruoli Utente da Monitorare' : 'Target User Roles'; ?></h3>
                    <p class="description">
                        <?php echo $is_it
                            ? 'Seleziona quali ruoli utente sono soggetti alla disconnessione automatica. I ruoli deselezionati mantengono la durata standard di WordPress.'
                            : 'Select which user roles are subject to inactivity logout. Unchecked roles retain standard session durations.'; ?>
                    </p>

                    <fieldset style="margin-top:15px;">
                        <?php
                        $selected_roles = (array) ( $settings['selected_roles'] ?? [] );
                        foreach ( $roles as $role_key => $role_data ) :
                            $checked = in_array( $role_key, $selected_roles, true );
                            $role_name = isset( $role_data['name'] ) ? ( function_exists( 'translate_user_role' ) ? translate_user_role( $role_data['name'] ) : $role_data['name'] ) : $role_key;
                            ?>
                            <label style="display:block;margin-bottom:8px;font-size:14px;">
                                <input type="checkbox" name="wpinact_settings[selected_roles][]" value="<?php echo esc_attr( $role_key ); ?>" <?php checked( $checked ); ?> />
                                <strong><?php echo esc_html( $role_name ); ?></strong>
                                <span style="color:#646970;font-size:12px;">(<?php echo esc_html( $role_key ); ?>)</span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                </div>

                <div style="margin-top:25px;padding-top:15px;border-top:1px solid #dcdcde;">
                    <?php
                    $btn_label = $is_it ? 'Salva Modifiche' : 'Save Changes';
                    submit_button( esc_html( $btn_label ), 'primary', 'submit', false );
                    ?>
                </div>
            </form>
        </div>

        <!-- SCRIPT JAVASCRIPT PER LO SWITCH DEI TAB -->
        <script>
        (function() {
            function initTabs() {
                var tabLinks = document.querySelectorAll('.wpinact-nav-tabs .nav-tab');
                var tabPanels = document.querySelectorAll('.wpinact-tab-panel');

                function showTab(targetId) {
                    tabLinks.forEach(function(link) {
                        if (link.getAttribute('data-tab') === targetId) {
                            link.classList.add('nav-tab-active');
                        } else {
                            link.classList.remove('nav-tab-active');
                        }
                    });

                    tabPanels.forEach(function(panel) {
                        if (panel.id === targetId) {
                            panel.style.display = 'block';
                        } else {
                            panel.style.display = 'none';
                        }
                    });

                    if (window.history && window.history.replaceState) {
                        window.history.replaceState(null, null, '#' + targetId);
                    }
                }

                tabLinks.forEach(function(link) {
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        var target = this.getAttribute('data-tab');
                        if (target) {
                            showTab(target);
                        }
                    });
                });

                var hash = window.location.hash ? window.location.hash.substring(1) : '';
                if (hash && document.getElementById(hash)) {
                    showTab(hash);
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initTabs);
            } else {
                initTabs();
            }

            var timeoutInput = document.getElementById('timeout_minutes');
            var warningInput = document.getElementById('warning_minutes');
            var warningDesc  = document.getElementById('wpinact-warning-description');
            var isIt         = <?php echo $is_it ? 'true' : 'false'; ?>;

            if (timeoutInput && warningInput) {
                var msgWarning = isIt
                    ? "L'avviso popup deve essere inferiore al timeout totale di logout di almeno 1 minuto (oppure imposta 0 per disattivarlo)."
                    : "Warning popup must be at least 1 minute less than total logout timeout (or set 0 to disable).";

                var msgTimeoutMin = isIt
                    ? "Il tempo totale di logout deve essere di almeno 1 minuto."
                    : "Total logout timeout must be at least 1 minute.";

                function validateLimits() {
                    var tVal = timeoutInput.value.trim();
                    var t = parseInt(tVal, 10);
                    if (isNaN(t) || t < 1) {
                        t = 1;
                    }

                    // Calcolo dinamico del limite massimo consentito per l'avviso sulla base degli input attuali nella UI
                    // Se t = 1, l'avviso non può avere anticipo: consentito solo 0 (disattivato)
                    // Se t >= 2, l'avviso massimo consentito è t - 1 (per garantire almeno 1 minuto di conteggio popup)
                    var maxWarning = Math.max(0, t - 1);
                    warningInput.max = maxWarning;

                    // Validazione per il campo timeout_minutes
                    if (tVal !== '' && parseInt(tVal, 10) < 1) {
                        timeoutInput.setCustomValidity(msgTimeoutMin);
                    } else {
                        timeoutInput.setCustomValidity('');
                    }

                    var wVal = warningInput.value.trim();
                    var w = parseInt(wVal, 10);

                    // Validazione per il campo warning_minutes basata sugli input correnti della UI
                    if (wVal === '' || isNaN(w)) {
                        warningInput.setCustomValidity('');
                    } else if (w < 0) {
                        warningInput.setCustomValidity(isIt ? "Il valore non può essere negativo." : "Value cannot be negative.");
                    } else if (w === 0) {
                        // 0 significa avviso popup disattivato: sempre consentito
                        warningInput.setCustomValidity('');
                    } else if (w > maxWarning) {
                        // Se w >= t (es. avviso 15 con logout 2, o avviso 1 con logout 1)
                        warningInput.setCustomValidity(msgWarning);
                    } else {
                        warningInput.setCustomValidity('');
                    }

                    // Aggiornamento dinamico in tempo reale della spiegazione contestuale
                    if (warningDesc && !isNaN(w) && w >= 0) {
                        if (w === 0) {
                            warningDesc.innerHTML = isIt
                                ? '<span style="color:#2271b1;font-weight:600;">Avviso popup disattivato (0):</span> L&apos;utente verra disconnesso direttamente dopo ' + t + ' minuti senza mostrare alcun popup di avviso.'
                                : '<span style="color:#2271b1;font-weight:600;">Warning popup disabled (0):</span> User will be logged out directly after ' + t + ' minutes without any modal popup.';
                        } else if (t <= 1 && w > 0) {
                            warningDesc.innerHTML = isIt
                                ? '<span style="color:#d63638;font-weight:600;">Attenzione:</span> Con un timeout totale di 1 minuto non è possibile mostrare l&apos;avviso popup prima del logout. Imposta 0 per disattivare l&apos;avviso.'
                                : '<span style="color:#d63638;font-weight:600;">Warning:</span> With 1 minute total timeout, popup warning cannot be shown before logout. Set 0 to disable.';
                        } else if (w > maxWarning) {
                            warningDesc.innerHTML = isIt
                                ? '<span style="color:#d63638;font-weight:600;">Attenzione:</span> L&apos;avviso popup (' + w + ' min) deve essere inferiore al timeout totale (' + t + ' min) di almeno 1 minuto. Massimo consentito: ' + maxWarning + ' min (oppure imposta 0 per disattivarlo).'
                                : '<span style="color:#d63638;font-weight:600;">Warning:</span> Warning popup (' + w + ' min) must be at least 1 minute less than total timeout (' + t + ' min). Maximum allowed: ' + maxWarning + ' min (or set 0 to disable).';
                        } else {
                            var diff = Math.max(1, t - w);
                            warningDesc.innerHTML = isIt
                                ? 'Con ' + t + ' minuti di logout e avviso a ' + w + ' minuti, il popup mostrera un conteggio di ' + diff + ' minuti rimanenti prima del logout. <em>(Imposta 0 per disattivare l&apos;avviso popup)</em>.'
                                : 'With ' + t + ' m logout and warning at ' + w + ' m, popup will countdown ' + diff + ' minutes remaining. <em>(Set 0 to disable the warning popup)</em>.';
                        }
                    }
                }

                timeoutInput.addEventListener('input', validateLimits);
                timeoutInput.addEventListener('change', validateLimits);
                timeoutInput.addEventListener('keyup', validateLimits);

                warningInput.addEventListener('input', validateLimits);
                warningInput.addEventListener('change', validateLimits);
                warningInput.addEventListener('keyup', validateLimits);

                // Allinea immediatamente i limiti e il testo all'apertura della pagina
                validateLimits();
            }
        })();
        </script>
        <?php
    }
}
