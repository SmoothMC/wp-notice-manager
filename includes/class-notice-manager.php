<?php
if (!defined('ABSPATH')) { exit; }

final class ZZZNM_Manager {
    const OPTION = 'zzznm_settings';
    const POPUP = 'zzznm_popup';
    const TICKER = 'zzznm_ticker';

    public function __construct() {
        add_action('init', [$this, 'register_types']);
    }

    public function register_types() {
        foreach ([self::POPUP => ['Popups', 'Popup'], self::TICKER => ['Ticker', 'Tickertext']] as $type => $names) {
            if (!$this->is_enabled($type)) { continue; }
            register_post_type($type, [
                'labels' => ['name' => $names[0], 'singular_name' => $names[1],
                    'add_new' => 'Neu hinzufügen', 'add_new_item' => $names[1] . ' hinzufügen',
                    'edit_item' => $names[1] . ' bearbeiten', 'all_items' => $names[0],
                    'not_found' => 'Keine Einträge gefunden.'],
                'public' => false, 'show_ui' => true, 'show_in_menu' => 'zzznm',
                'show_in_rest' => false, 'rewrite' => false, 'query_var' => false,
                'supports' => ['title', 'editor'], 'map_meta_cap' => true,
                'capabilities' => ['create_posts' => 'manage_options', 'edit_posts' => 'manage_options',
                    'edit_others_posts' => 'manage_options', 'publish_posts' => 'manage_options',
                    'read_private_posts' => 'manage_options', 'delete_posts' => 'manage_options',
                    'delete_private_posts' => 'manage_options', 'delete_published_posts' => 'manage_options',
                    'delete_others_posts' => 'manage_options', 'edit_private_posts' => 'manage_options',
                    'edit_published_posts' => 'manage_options'],
            ]);
        }
    }

    public function settings() {
        return wp_parse_args((array) get_option(self::OPTION, []), [
            'enabled' => 1, 'ticker_enabled' => 1, 'ticker_controls' => 1, 'renderer' => 'standalone', 'template_id' => 0, 'divi_template_id' => 0,
            'delay' => 400, 'complianz_delay' => 300, 'ticker_mode' => 'rotate',
            'interval' => 6, 'speed' => 45,
        ]);
    }

    public function is_enabled($type) {
        $settings = $this->settings();
        return !empty($settings[$type === self::POPUP ? 'enabled' : 'ticker_enabled']);
    }

    public static function parse_date($value) {
        if (!is_string($value) || $value === '') { return 0; }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, wp_timezone());
        // Round-trip rejects normalized invalid dates and nonexistent DST hours.
        return $date && $date->format('Y-m-d\TH:i') === $value ? $date->getTimestamp() : 0;
    }

    public static function overlaps($from, $until, $other_from, $other_until) {
        return $from < $other_until && $until > $other_from;
    }

    public function schedule($id) {
        return [
            'from' => (int) get_post_meta($id, '_zzznm_from', true),
            'until' => (int) get_post_meta($id, '_zzznm_until', true),
        ];
    }

    public function validate($type, $id, $from, $until, $check_overlap = true) {
        if ($type === self::POPUP && (!$from || !$until)) {
            return 'Für Popups sind Start- und Enddatum mit Uhrzeit erforderlich.';
        }
        if ($from && $until && $until <= $from) {
            return 'Das Enddatum muss nach dem Startdatum liegen.';
        }
        if ($type !== self::POPUP || !$check_overlap) { return ''; }
        foreach (get_posts(['post_type' => self::POPUP, 'post_status' => ['publish', 'future'],
            'numberposts' => -1, 'exclude' => [$id]]) as $other) {
            $range = $this->schedule($other->ID);
            if (self::overlaps($from, $until, $range['from'], $range['until'])) {
                return sprintf('Der Zeitraum überschneidet sich mit „%s“ (#%d). Bitte wähle einen freien Zeitraum.', $other->post_title, $other->ID);
            }
        }
        return '';
    }

    public function active($type, $now = null) {
        if (!$this->is_enabled($type)) { return []; }
        $now = $now ?? time();
        $active = [];
        foreach (get_posts(['post_type' => $type, 'post_status' => 'publish', 'numberposts' => -1,
            'orderby' => ['menu_order' => 'ASC', 'ID' => 'ASC']]) as $post) {
            if ($post->post_password !== '') { continue; }
            $range = $this->schedule($post->ID);
            if ($type === self::POPUP && (!$range['from'] || !$range['until'] || $range['until'] <= $range['from'])) { continue; }
            if ((!$range['from'] || $range['from'] <= $now) && (!$range['until'] || $now < $range['until'])) {
                $active[] = $post;
                if ($type === self::POPUP) { break; }
            }
        }
        return $active;
    }

    public function body($post) {
        return wp_kses_post(wpautop($post->post_content));
    }

    public function popup_data($post) {
        $dismiss = $this->dismiss_mode($post->ID);
        $range = $this->schedule($post->ID);
        $key = (string) $post->ID;
        if (in_array($dismiss, ['content', 'date'], true)) {
            $key .= '|' . $range['from'] . '|' . $range['until'];
        }
        if ($dismiss === 'content') { $key .= '|' . $post->post_title . '|' . $post->post_content; }
        return ['id' => $post->ID, 'dismiss' => $dismiss, 'heading' => $post->post_title, 'html' => $this->body($post),
            'columns' => max(1, min(6, (int) get_post_meta($post->ID, '_zzznm_columns', true))),
            'until' => $range['until'], 'key' => 'zzznm_closed_' . md5($key)];
    }

    public function dismiss_mode($id) {
        $mode = get_post_meta($id, '_zzznm_dismiss', true);
        return in_array($mode, ['content', 'date', 'forever', 'always'], true) ? $mode : 'content';
    }

    public function valid_divi_template($id) {
        return $id && get_post_type($id) === 'et_pb_layout' && get_post_status($id) === 'publish'
            && has_term('popup', 'layout_tag', $id);
    }

    public function divi_template_id() {
        $settings = $this->settings();
        $id = (int) $settings['divi_template_id'];
        return $settings['enabled'] && $settings['renderer'] === 'divi' && $this->valid_divi_template($id) ? $id : 0;
    }

    public function template_id() {
        $settings = $this->settings();
        $id = (int) $settings['template_id'];
        return $settings['renderer'] === 'elementor' && class_exists('ElementorPro\\Modules\\Popup\\Module')
            && get_post_type($id) === 'elementor_library' && get_post_status($id) === 'publish'
            && get_post_meta($id, '_elementor_template_type', true) === 'popup' ? $id : 0;
    }
}
