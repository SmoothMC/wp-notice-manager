<?php
if (!defined('ABSPATH')) { exit; }

/** JSON-based updates, following the WooSales Manager distribution model. */
final class ZZZNM_Updater {
    const SLUG = 'notice-manager-by-zzzooo';
    const REPOSITORY = 'https://github.com/SmoothMC/wp-notice-manager';
    const METADATA = self::REPOSITORY . '/releases/latest/download/update.json';
    const CACHE = 'zzznm_release_metadata';
    private $basename;

    public function __construct() {
        $this->basename = plugin_basename(ZZZNM_FILE);
        add_filter('update_plugins_github.com', [$this, 'update'], 10, 4);
        add_filter('plugins_api', [$this, 'information'], 10, 3);
        add_filter('plugin_action_links_' . $this->basename, [$this, 'links']);
        add_filter('network_admin_plugin_action_links_' . $this->basename, [$this, 'links']);
        add_action('admin_post_zzznm_check_updates', [$this, 'manual_check']);
        add_action('upgrader_process_complete', [$this, 'clear_after_upgrade'], 10, 2);
    }

    public function metadata($force = false) {
        if (!$force) {
            $cached = get_site_transient(self::CACHE);
            if ($cached !== false) { return is_array($cached) ? $cached : false; }
        }
        $response = wp_safe_remote_get(self::METADATA, [
            'timeout' => 10, 'redirection' => 5, 'limit_response_size' => 131072,
            'headers' => ['Accept' => 'application/json'],
            'user-agent' => 'NoticeManager/' . ZZZNM_VERSION,
        ]);
        $data = !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200
            ? json_decode(wp_remote_retrieve_body($response), true) : null;
        $data = $this->validate_metadata($data);
        // Cache failures briefly as well; never block a page with repeated calls.
        set_site_transient(self::CACHE, $data ?: 'unavailable', $data ? 6 * HOUR_IN_SECONDS : 5 * MINUTE_IN_SECONDS);
        return $data;
    }

    public function validate_metadata($data) {
        if (!is_array($data)) { return false; }
        foreach (['name', 'slug', 'version', 'download_url', 'requires', 'requires_php'] as $key) {
            if (!isset($data[$key]) || !is_string($data[$key]) || $data[$key] === '') { return false; }
        }
        if ($data['slug'] !== self::SLUG || !preg_match('/^\d+\.\d+\.\d+$/D', $data['version'])) { return false; }
        foreach (['requires', 'requires_php'] as $key) {
            if (!preg_match('/^\d+\.\d+(?:\.\d+)?$/D', $data[$key])) { return false; }
        }
        $expected = self::REPOSITORY . '/releases/download/v' . $data['version'] . '/' . 'wp-notice-manager-' . $data['version'] . '.zip';
        if ($data['download_url'] !== $expected) { return false; }
        foreach (['description', 'changelog', 'last_updated', 'tested'] as $key) {
            $data[$key] = isset($data[$key]) && is_string($data[$key]) ? $data[$key] : '';
        }
        return $data;
    }

    public function update($update, $plugin_data, $plugin_file, $locales) {
        if ($plugin_file !== $this->basename) { return $update; }
        $data = $this->metadata();
        if (!$data) { return false; }
        // Core compares versions and populates response/no_update itself.
        // Do not force auto-updates: respect the WordPress administrator's choice.
        return ['id' => self::REPOSITORY, 'slug' => self::SLUG, 'version' => $data['version'],
            'url' => self::REPOSITORY, 'package' => $data['download_url'],
            'requires' => $data['requires'], 'requires_php' => $data['requires_php'], 'tested' => $data['tested']];
    }

    public function information($result, $action, $args) {
        if ($action !== 'plugin_information' || ($args->slug ?? '') !== self::SLUG) { return $result; }
        $data = $this->metadata();
        if (!$data) { return $result; }
        return (object) ['name' => sanitize_text_field($data['name']), 'slug' => self::SLUG,
            'version' => $data['version'], 'author' => '<a href="https://zzzooo.studio/">ZZZOOO Studio</a>',
            'homepage' => self::REPOSITORY, 'download_link' => $data['download_url'],
            'requires' => $data['requires'], 'requires_php' => $data['requires_php'],
            'tested' => $data['tested'], 'last_updated' => sanitize_text_field($data['last_updated']),
            'sections' => ['description' => wp_kses_post($data['description']),
                'changelog' => wp_kses_post($data['changelog'])]];
    }

    public function links($links) {
        if (current_user_can('update_plugins')) {
            $url = wp_nonce_url(admin_url('admin-post.php?action=zzznm_check_updates'), 'zzznm_check_updates');
            $links[] = '<a href="' . esc_url($url) . '">Nach Updates suchen</a>';
        }
        return $links;
    }

    public function manual_check() {
        if (!current_user_can('update_plugins')) { wp_die('Keine Berechtigung.'); }
        check_admin_referer('zzznm_check_updates');
        if (!$this->metadata(true)) {
            wp_die('Die GitHub-Updateinformationen sind derzeit nicht erreichbar oder ungültig. Bitte später erneut versuchen.', 'Update-Prüfung', ['back_link' => true]);
        }
        delete_site_transient('update_plugins');
        wp_update_plugins();
        wp_safe_redirect(self_admin_url('plugins.php'));
        exit;
    }

    public function clear_after_upgrade($upgrader, $options) {
        if (($options['type'] ?? '') !== 'plugin' || ($options['action'] ?? '') !== 'update') { return; }
        $plugins = (array) ($options['plugins'] ?? []);
        if (isset($options['plugin'])) { $plugins[] = $options['plugin']; }
        if (in_array($this->basename, $plugins, true)) { delete_site_transient(self::CACHE); }
    }
}
