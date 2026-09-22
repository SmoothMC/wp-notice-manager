<?php
if (!defined('ABSPATH')) { exit; }

final class ZZZNM_Admin {
    private $manager;
    private $locked = false;

    public function __construct($manager) {
        $this->manager = $manager;
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('add_meta_boxes', [$this, 'boxes']);
        add_filter('wp_insert_post_data', [$this, 'validate_post'], 99, 2);
        add_action('save_post', [$this, 'save'], 10, 2);
        add_action('wp_after_insert_post', [$this, 'verify_saved'], 99, 2);
        add_action('admin_notices', [$this, 'notices']);
        add_action('admin_post_zzznm_import', [$this, 'import']);
        add_action('admin_bar_menu', [$this, 'admin_bar'], 100);
        add_action('shutdown', [$this, 'unlock']);
        foreach ([ZZZNM_Manager::POPUP, ZZZNM_Manager::TICKER] as $type) {
            add_filter('manage_' . $type . '_posts_columns', [$this, 'columns']);
            add_action('manage_' . $type . '_posts_custom_column', [$this, 'column'], 10, 2);
        }
        add_filter('post_row_actions', [$this, 'row_actions'], 10, 2);
    }

    public function menu() {
        add_menu_page('WP Notice Manager by ZZZOOO', 'Notice Manager', 'manage_options', 'zzznm', [$this, 'settings_page'], 'dashicons-megaphone', 26);
        add_submenu_page('zzznm', 'Einstellungen', 'Einstellungen', 'manage_options', 'zzznm', [$this, 'settings_page']);
    }

    public function admin_bar($bar) {
        if (current_user_can('manage_options')) {
            $bar->add_node(['id' => 'zzznm', 'title' => 'Website-Hinweise', 'href' => admin_url('admin.php?page=zzznm')]);
        }
    }

    public function register_settings() {
        register_setting('zzznm', ZZZNM_Manager::OPTION, ['type' => 'array', 'sanitize_callback' => [$this, 'sanitize_settings']]);
    }

    public function sanitize_settings($input) {
        $input = is_array($input) ? $input : [];
        $enum = static function ($key, $allowed, $default) use ($input) {
            return isset($input[$key]) && in_array($input[$key], $allowed, true) ? $input[$key] : $default;
        };
        $id = absint($input['template_id'] ?? 0);
        if ($id && (get_post_type($id) !== 'elementor_library' || get_post_status($id) !== 'publish'
            || get_post_meta($id, '_elementor_template_type', true) !== 'popup')) { $id = 0; }
        return ['enabled' => empty($input['enabled']) ? 0 : 1,
            'renderer' => $enum('renderer', ['standalone', 'elementor'], 'standalone'),
            'template_id' => $id, 'delay' => min(60000, absint($input['delay'] ?? 400)),
            'complianz_delay' => max(0, min(60000, (int) ($input['complianz_delay'] ?? 300))),
            'dismiss' => $enum('dismiss', ['always', 'content', 'date', 'forever'], 'content'),
            'ticker_mode' => $enum('ticker_mode', ['rotate', 'marquee'], 'rotate'),
            'interval' => max(2, min(60, absint($input['interval'] ?? 6))),
            'speed' => max(10, min(200, absint($input['speed'] ?? 45)))];
    }

    private function select($key, $label, $choices, $settings) {
        echo '<tr><th><label for="zzznm-' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td><select id="zzznm-' . esc_attr($key) . '" name="' . esc_attr(ZZZNM_Manager::OPTION) . '[' . esc_attr($key) . ']">';
        foreach ($choices as $value => $text) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($settings[$key], $value, false) . '>' . esc_html($text) . '</option>';
        }
        echo '</select></td></tr>';
    }

    public function settings_page() {
        if (!current_user_can('manage_options')) { return; }
        $settings = $this->manager->settings();
        $templates = [0 => 'Kein Template – Standalone verwenden'];
        foreach (get_posts(['post_type' => 'elementor_library', 'post_status' => 'publish', 'numberposts' => -1,
            'meta_key' => '_elementor_template_type', 'meta_value' => 'popup']) as $post) {
            $templates[$post->ID] = $post->post_title . ' (#' . $post->ID . ')';
        }
        echo '<div class="wrap"><h1>Notice Manager <small>by ZZZOOO</small></h1>';
        settings_errors();
        echo '<p>Verwalte Inhalte unter <a href="' . esc_url(admin_url('edit.php?post_type=zzznm_popup')) . '">Popups</a> und <a href="' . esc_url(admin_url('edit.php?post_type=zzznm_ticker')) . '">Ticker</a>. Die Darstellung wird hier zentral festgelegt.</p><form action="options.php" method="post">';
        settings_fields('zzznm');
        echo '<h2>Popups</h2><table class="form-table"><tr><th>Automatische Ausgabe</th><td><label><input type="checkbox" name="zzznm_settings[enabled]" value="1" ' . checked($settings['enabled'], 1, false) . '> Popups auf der Website anzeigen</label></td></tr>';
        $this->select('renderer', 'Darstellung', ['standalone' => 'Standalone (ohne Page Builder)', 'elementor' => 'Elementor Pro'], $settings);
        $this->select('template_id', 'Standard-Elementor-Template', $templates, $settings);
        $this->select('dismiss', 'Nach dem Schließen', ['content' => 'Erneut bei geändertem Inhalt oder Zeitraum', 'date' => 'Erneut bei geändertem Zeitraum', 'forever' => 'Diesen Popup-Beitrag dauerhaft ausblenden', 'always' => 'Bei jedem Seitenaufruf anzeigen'], $settings);
        echo '<tr><th><label for="zzznm-delay">Verzögerung (Millisekunden)</label></th><td><input id="zzznm-delay" name="zzznm_settings[delay]" type="number" min="0" max="60000" step="100" value="' . esc_attr($settings['delay']) . '"></td></tr>';
        echo '<tr><th><label for="zzznm-complianz-delay">Verzögerung nach Complianz-Freigabe (ms)</label></th><td><input id="zzznm-complianz-delay" name="zzznm_settings[complianz_delay]" type="number" min="0" max="60000" step="1" value="' . esc_attr($settings['complianz_delay']) . '" aria-describedby="zzznm-complianz-delay-help"><p class="description" id="zzznm-complianz-delay-help">Wartezeit nach dem Schließen des Cookie-Banners. Standard: 300 ms. Die allgemeine Popup-Verzögerung kommt zusätzlich hinzu. 0 = keine zusätzliche Wartezeit.</p></td></tr></table>';
        echo '<p>Ohne verfügbares Elementor Pro oder gültiges Template wird das Standalone-Popup verwendet. Im Elementor-Template die Shortcodes unten einsetzen. Automatische Elementor-Trigger für dieses Template deaktivieren: Die Zeitplanung übernimmt der Notice Manager.</p>';
        echo '<h2>Ticker</h2><table class="form-table">';
        $this->select('ticker_mode', 'Standard-Darstellung', ['rotate' => 'Wechselnde Meldungen', 'marquee' => 'Durchlaufendes Laufband'], $settings);
        foreach (['interval' => ['Wechselintervall (Sekunden)', 2, 60], 'speed' => ['Laufband-Geschwindigkeit (Pixel pro Sekunde)', 10, 200]] as $key => $field) {
            echo '<tr><th><label for="zzznm-' . esc_attr($key) . '">' . esc_html($field[0]) . '</label></th><td><input id="zzznm-' . esc_attr($key) . '" name="zzznm_settings[' . esc_attr($key) . ']" type="number" min="' . (int) $field[1] . '" max="' . (int) $field[2] . '" value="' . esc_attr($settings[$key]) . '"></td></tr>';
        }
        echo '</table>';
        submit_button();
        echo '</form><hr><h2>Shortcodes</h2><p><code>[notice_ticker]</code> – alle aktuell aktiven Tickertexte, z. B. in der Topbar.</p><p><code>[notice_ticker mode="marquee"]</code> – Laufband; <code>[notice_ticker mode="rotate" interval="6"]</code> – wechselnde Meldungen.</p><p><code>[notice_popup_heading]</code>, <code>[notice_popup_text]</code>, <code>[notice_popup_notice]</code> – Inhalt des aktuell aktiven Popups.</p><p>Die bisherigen Namen <code>[praxis_popup_heading]</code>, <code>[praxis_popup_text]</code> und <code>[praxis_popup_notice]</code> bleiben als Aliasse verfügbar.</p>';
        if (get_option('zzz_praxis_popup_hinweis_options') && !get_option('zzznm_imported')) {
            echo '<hr><h2>Bisherigen Praxis-Hinweis übernehmen</h2><p>Übernimmt Inhalt und Zeitraum einmalig als Entwurf. Bitte anschließend prüfen und veröffentlichen. Die zentralen Einstellungen bleiben unverändert.</p><form action="' . esc_url(admin_url('admin-post.php')) . '" method="post"><input type="hidden" name="action" value="zzznm_import">';
            wp_nonce_field('zzznm_import');
            submit_button('Als Entwurf übernehmen', 'secondary');
            echo '</form>';
        }
        echo '</div>';
    }

    public function boxes() {
        foreach ([ZZZNM_Manager::POPUP, ZZZNM_Manager::TICKER] as $type) {
            add_meta_box('zzznm_schedule', 'Anzeigezeitraum & Ausgabe', [$this, 'box'], $type, 'normal', 'high');
        }
    }

    public function box($post) {
        wp_nonce_field('zzznm_save_' . $post->ID, 'zzznm_nonce');
        $popup = $post->post_type === ZZZNM_Manager::POPUP;
        echo '<p>' . ($popup ? 'Start und Ende sind für die Veröffentlichung erforderlich. Veröffentlichte Popups dürfen sich zeitlich nicht überschneiden.' : 'Beide Datumsfelder sind optional. Ohne Zeitraum bleibt dieser Tickertext aktiv. Mehrere Tickertexte dürfen gleichzeitig aktiv sein.') . '</p>';
        foreach (['from' => 'Start', 'until' => 'Ende'] as $key => $label) {
            $value = get_post_meta($post->ID, '_zzznm_' . $key, true);
            $value = $value ? wp_date('Y-m-d\TH:i', (int) $value, wp_timezone()) : '';
            $attempt = get_post_meta($post->ID, '_zzznm_raw_' . $key, true);
            if ($attempt !== '') { $value = $attempt; }
            echo '<p><label for="zzznm-' . esc_attr($key) . '"><strong>' . esc_html($label) . ($popup ? ' *' : '') . '</strong></label><br><input id="zzznm-' . esc_attr($key) . '" name="zzznm_' . esc_attr($key) . '" type="datetime-local" value="' . esc_attr($value) . '"></p>';
        }
        echo '<p>Zeitzone: <strong>' . esc_html(wp_timezone_string()) . '</strong>. Das Ende ist exklusiv: Ein weiterer Hinweis darf genau dann beginnen.</p>';
        if ($popup) {
            $columns = max(1, (int) get_post_meta($post->ID, '_zzznm_columns', true));
            echo '<p><label for="zzznm-columns">Textspalten</label> <select id="zzznm-columns" name="zzznm_columns">';
            for ($i = 1; $i <= 6; $i++) { echo '<option ' . selected($columns, $i, false) . '>' . $i . '</option>'; }
            echo '</select></p><p>Der Beitragstitel ist die Popup-Überschrift. Die Darstellung folgt den zentralen Einstellungen.</p>';
        } else {
            echo '<p><label for="zzznm-order">Reihenfolge (kleinere Zahlen zuerst)</label> <input id="zzznm-order" type="number" name="zzznm_order" value="' . (int) $post->menu_order . '"></p><p>Der Editor enthält die sichtbare Meldung, der Titel dient der internen Verwaltung. Einbindung: <code>[notice_ticker]</code></p>';
        }
        echo '<p>Mit „Veröffentlichen“ wird die Anzeige für den oben eingetragenen Zeitraum freigegeben. Unvollständige Einträge können als Entwurf gespeichert werden.</p>';
    }

    private function submitted($id) {
        return isset($_POST['zzznm_nonce']) && is_string($_POST['zzznm_nonce'])
            && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['zzznm_nonce'])), 'zzznm_save_' . $id)
            && current_user_can('edit_post', $id);
    }

    private function input($key) {
        return isset($_POST[$key]) && is_string($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
    }

    private function error($message) {
        set_transient('zzznm_error_' . get_current_user_id(), $message, 120);
    }

    public function validate_post($data, $postarr) {
        if (!in_array($data['post_type'], [ZZZNM_Manager::POPUP, ZZZNM_Manager::TICKER], true)
            || in_array($data['post_status'], ['trash', 'auto-draft', 'inherit'], true)) { return $data; }
        $id = (int) ($postarr['ID'] ?? 0);
        $range = $this->manager->schedule($id);
        $submitted = $this->submitted($id);
        $invalid = (bool) (get_post_meta($id, '_zzznm_raw_from', true) || get_post_meta($id, '_zzznm_raw_until', true));
        foreach (['from', 'until'] as $key) {
            if (isset($postarr['meta_input']['_zzznm_' . $key])) { $range[$key] = (int) $postarr['meta_input']['_zzznm_' . $key]; }
        }
        if ($submitted) {
            $invalid = false;
            foreach (['from', 'until'] as $key) {
                $raw = $this->input('zzznm_' . $key);
                $range[$key] = ZZZNM_Manager::parse_date($raw);
                $invalid = $invalid || ($raw !== '' && !$range[$key]);
            }
            if ($data['post_type'] === ZZZNM_Manager::TICKER) { $data['menu_order'] = (int) $this->input('zzznm_order'); }
        }
        $publishing = in_array($data['post_status'], ['publish', 'future'], true);
        if (!$publishing) { return $data; }
        // Serialize popup publication checks across concurrent admin requests.
        if ($data['post_type'] === ZZZNM_Manager::POPUP && !$this->locked) {
            $this->lock();
            if (!$this->locked) {
                $data['post_status'] = 'draft';
                $this->error('Ein anderer Popup-Speichervorgang läuft. Der Beitrag wurde als Entwurf gespeichert. Bitte kurz warten und erneut veröffentlichen.');
                return $data;
            }
        }
        $error = $invalid ? 'Bitte gültige Datumswerte eingeben. Diese Ortszeit existiert möglicherweise wegen der Zeitumstellung nicht.'
            : $this->manager->validate($data['post_type'], $id, $range['from'], $range['until']);
        if ($error) {
            $data['post_status'] = 'draft';
            $this->error($error . ' Der Beitrag wurde als Entwurf gespeichert und wird nicht angezeigt.');
        }
        return $data;
    }

    public function unlock() {
        if ($this->locked) {
            global $wpdb;
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', 'zzznm_' . md5($wpdb->prefix)));
            $this->locked = false;
        }
    }

    private function lock() {
        if (!$this->locked) {
            global $wpdb;
            // MySQL releases advisory locks automatically if a request disconnects.
            $this->locked = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 1)', 'zzznm_' . md5($wpdb->prefix))) === 1;
        }
        return $this->locked;
    }

    public function verify_saved($id, $post) {
        if (!in_array($post->post_type, [ZZZNM_Manager::POPUP, ZZZNM_Manager::TICKER], true)
            || !in_array($post->post_status, ['publish', 'future'], true)) { return; }
        $range = $this->manager->schedule($id);
        $error = $post->post_type === ZZZNM_Manager::POPUP && !$this->lock()
            ? 'Der Zeitraum konnte wegen eines parallelen Speichervorgangs nicht geprüft werden.'
            : $this->manager->validate($post->post_type, $id, $range['from'], $range['until']);
        if ($error) {
            wp_update_post(['ID' => $id, 'post_status' => 'draft']);
            $this->error($error . ' Der Beitrag bleibt als Entwurf gespeichert.');
        }
    }

    public function save($id, $post) {
        if (!in_array($post->post_type, [ZZZNM_Manager::POPUP, ZZZNM_Manager::TICKER], true)
            || wp_is_post_revision($id) || wp_is_post_autosave($id) || !$this->submitted($id)) { return; }
        foreach (['from', 'until'] as $key) {
            $raw = $this->input('zzznm_' . $key);
            update_post_meta($id, '_zzznm_' . $key, ZZZNM_Manager::parse_date($raw));
            update_post_meta($id, '_zzznm_raw_' . $key, $raw !== '' && !ZZZNM_Manager::parse_date($raw) ? $raw : '');
        }
        update_post_meta($id, '_zzznm_columns', max(1, min(6, (int) $this->input('zzznm_columns'))));
    }

    public function notices() {
        if (!current_user_can('manage_options')) { return; }
        $key = 'zzznm_error_' . get_current_user_id();
        if ($message = get_transient($key)) {
            echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
            delete_transient($key);
        }
    }

    public function columns($columns) {
        $columns['zzznm_range'] = 'Anzeigezeitraum';
        $columns['zzznm_state'] = 'Anzeige';
        return $columns;
    }

    public function column($column, $id) {
        $range = $this->manager->schedule($id);
        if ($column === 'zzznm_range') {
            echo esc_html(($range['from'] ? wp_date('d.m.Y. H:i', $range['from']) : 'Ohne Start') . ' → ' . ($range['until'] ? wp_date('d.m.Y. H:i', $range['until']) : 'Ohne Ende'));
        }
        if ($column === 'zzznm_state') {
            $state = get_post_status($id) !== 'publish' ? 'Nicht freigegeben' :
                ($range['until'] && $range['until'] <= time() ? 'Abgelaufen' : ($range['from'] > time() ? 'Geplant' : 'Aktiv'));
            echo esc_html($state);
        }
    }

    public function row_actions($actions, $post) {
        if (in_array($post->post_type, [ZZZNM_Manager::POPUP, ZZZNM_Manager::TICKER], true)) { unset($actions['inline hide-if-no-js']); }
        return $actions;
    }

    public function import() {
        if (!current_user_can('manage_options')) { wp_die('Keine Berechtigung.'); }
        check_admin_referer('zzznm_import');
        if (get_option('zzznm_imported')) { wp_safe_redirect(admin_url('edit.php?post_type=zzznm_popup')); exit; }
        $old = (array) get_option('zzz_praxis_popup_hinweis_options', []);
        $id = wp_insert_post(wp_slash(['post_type' => ZZZNM_Manager::POPUP, 'post_status' => 'draft',
            'post_title' => sanitize_text_field($old['heading'] ?? 'Übernommener Praxis-Hinweis'),
            'post_content' => wp_kses_post($old['text'] ?? ''),
            'meta_input' => ['_zzznm_from' => ZZZNM_Manager::parse_date($old['show_from'] ?? ''),
                '_zzznm_until' => ZZZNM_Manager::parse_date($old['show_until'] ?? ''),
                '_zzznm_columns' => max(1, min(6, (int) ($old['columns'] ?? 1)))]]), true);
        if (is_wp_error($id)) { wp_die(esc_html($id->get_error_message())); }
        update_option('zzznm_imported', $id, false);
        wp_safe_redirect(admin_url('post.php?post=' . $id . '&action=edit'));
        exit;
    }
}
