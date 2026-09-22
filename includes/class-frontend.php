<?php
if (!defined('ABSPATH')) { exit; }

final class ZZZNM_Frontend {
    private $manager;

    public function __construct($manager) {
        $this->manager = $manager;
        add_action('wp_enqueue_scripts', [$this, 'assets'], 100);
        add_action('wp_print_footer_scripts', [$this, 'complianz_config'], 0);
        add_action('wp_footer', [$this, 'prepare_template'], 5);
        add_action('wp_ajax_zzznm_state', [$this, 'state']);
        add_action('wp_ajax_nopriv_zzznm_state', [$this, 'state']);
        add_shortcode('notice_ticker', [$this, 'ticker']);
        foreach (['heading', 'text', 'notice'] as $part) {
            foreach (['notice_popup_', 'praxis_popup_'] as $prefix) {
                add_shortcode($prefix . $part, function () use ($part) { return $this->popup_shortcode($part); });
            }
        }
    }

    public function assets() {
        wp_enqueue_style('zzznm', plugins_url('assets/css/frontend.css', ZZZNM_FILE), [], ZZZNM_VERSION);
        wp_enqueue_script('zzznm', plugins_url('assets/js/frontend.js', ZZZNM_FILE), [], ZZZNM_VERSION, true);
        wp_localize_script('zzznm', 'ZZZNM', ['endpoint' => admin_url('admin-ajax.php'),
            'complianzDelay' => (int) $this->manager->settings()['complianz_delay'],
            'preview' => is_preview() || isset($_GET['elementor-preview']) || isset($_GET['et_fb']),
            'close' => 'Hinweis schließen', 'pause' => 'Pause', 'play' => 'Fortsetzen', 'next' => 'Nächste Meldung']);
    }

    public function prepare_template() {
        $id = $this->manager->template_id();
        $callback = ['ElementorPro\\Modules\\Popup\\Module', 'add_popup_to_location'];
        if ($id && is_callable($callback) && $this->manager->settings()['enabled']) {
            call_user_func($callback, $id);
        }
    }

    public function complianz_config() {
        // Check the page's actual script queue, not plugin activation: Complianz
        // may legitimately omit its banner on a particular page or region.
        $expected = wp_script_is('cmplz-cookiebanner', 'enqueued') || wp_script_is('cmplz-cookiebanner', 'done');
        wp_add_inline_script('zzznm', 'window.ZZZNM.complianzExpected = ' . wp_json_encode($expected) . ';', 'before');
    }

    public function state() {
        // Public, read-only endpoint: no visitor nonce, so cached pages remain usable.
        nocache_headers();
        $settings = $this->manager->settings();
        $now = time();
        $popups = $settings['enabled'] ? $this->manager->active(ZZZNM_Manager::POPUP, $now) : [];
        $tickers = [];
        foreach ($this->manager->active(ZZZNM_Manager::TICKER, $now) as $post) {
            $tickers[] = ['id' => $post->ID, 'html' => $this->manager->body($post)];
        }
        $next = $now + 60;
        foreach (get_posts(['post_type' => [ZZZNM_Manager::POPUP, ZZZNM_Manager::TICKER],
            'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids']) as $id) {
            foreach ($this->manager->schedule($id) as $boundary) {
                if ($boundary > $now) { $next = min($next, $boundary); }
            }
        }
        wp_send_json_success(['popup' => $popups ? $this->manager->popup_data($popups[0]) : null,
            'tickers' => $tickers, 'settings' => $settings, 'template' => $this->manager->template_id(),
            'now' => $now, 'next' => $next]);
    }

    public function popup_shortcode($part) {
        // Populated from the uncached endpoint, including in cached Elementor markup.
        $tag = $part === 'heading' ? 'span' : 'div';
        return '<' . $tag . ' data-zzznm-popup="' . esc_attr($part) . '"></' . $tag . '>';
    }

    public function ticker($attributes) {
        $attributes = shortcode_atts(['mode' => '', 'interval' => '', 'speed' => ''], $attributes, 'notice_ticker');
        $mode = in_array($attributes['mode'], ['rotate', 'marquee'], true) ? $attributes['mode'] : '';
        $interval = $attributes['interval'] === '' ? '' : max(2, min(60, (int) $attributes['interval']));
        $speed = $attributes['speed'] === '' ? '' : max(10, min(200, (int) $attributes['speed']));
        return '<section class="zzznm-ticker" aria-label="Aktuelle Hinweise" data-zzznm-ticker data-mode="' . esc_attr($mode)
            . '" data-interval="' . esc_attr($interval) . '" data-speed="' . esc_attr($speed) . '" hidden></section>';
    }
}
