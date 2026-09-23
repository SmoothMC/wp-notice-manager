<?php
if (!defined('ABSPATH')) { exit; }

final class ZZZNM_Frontend {
    private $manager;
    private $divi_html = '';
    private $divi_id = 0;
    private $preview_id = 0;

    public function __construct($manager) {
        $this->manager = $manager;
        add_action('template_redirect', [$this, 'preview_page'], 0);
        add_action('wp_ajax_zzznm_preview_state', [$this, 'preview_state']);
        add_action('wp_enqueue_scripts', [$this, 'assets'], 100);
        add_action('wp_print_footer_scripts', [$this, 'complianz_config'], 0);
        add_action('wp_footer', [$this, 'prepare_template'], 5);
        add_action('wp_ajax_zzznm_state', [$this, 'state']);
        add_action('wp_ajax_nopriv_zzznm_state', [$this, 'state']);
        add_shortcode('notice_ticker', [$this, 'ticker']);
        foreach (['heading', 'text', 'notice', 'button', 'button_text'] as $part) {
            foreach (['notice_popup_', 'praxis_popup_'] as $prefix) {
                add_shortcode($prefix . $part, function () use ($part) { return $this->popup_shortcode($part); });
            }
        }
    }

    public function authorized_preview($input) {
        $id = isset($input['zzznm_preview']) && is_scalar($input['zzznm_preview']) ? absint($input['zzznm_preview']) : 0;
        $nonce = isset($input['zzznm_nonce']) && is_string($input['zzznm_nonce']) ? sanitize_text_field(wp_unslash($input['zzznm_nonce'])) : '';
        if (!$id || !current_user_can('manage_options') || !current_user_can('edit_post', $id)
            || !wp_verify_nonce($nonce, 'zzznm_preview_' . $id)) { return 0; }
        $post = get_post($id);
        return $post && $post->post_type === ZZZNM_Manager::POPUP
            && !in_array($post->post_status, ['trash', 'auto-draft', 'inherit'], true) ? $id : 0;
    }

    public function preview_page() {
        if (!isset($_GET['zzznm_preview'])) { return; }
        if (!defined('DONOTCACHEPAGE')) { define('DONOTCACHEPAGE', true); }
        nocache_headers();
        header('X-Robots-Tag: noindex, nofollow', true);
        header('Referrer-Policy: no-referrer', true);
        $this->preview_id = $this->authorized_preview($_GET);
        if (!$this->preview_id) { wp_die('Diese Popup-Vorschau ist nicht verfügbar. Bitte im Popup-Beitrag neu öffnen.', 'Popup-Vorschau', ['response' => 403]); }
    }

    public function preview_state() {
        nocache_headers();
        $id = $this->authorized_preview($_POST);
        if (!$id) { wp_send_json_error(['message' => 'Vorschau nicht autorisiert. Bitte neu öffnen.'], 403); return; }
        $now = time();
        $popup = $this->manager->popup_data(get_post($id));
        $popup['until'] = $now + 3600;
        $popup['dismiss'] = 'always';
        $settings = $this->manager->settings();
        $settings['delay'] = 0;
        wp_send_json_success(['popup' => $popup, 'tickers' => [], 'settings' => $settings,
            'template' => $this->manager->template_id(), 'divi_template' => $this->manager->divi_template_id(true),
            'now' => $now, 'next' => $now + 60]);
    }

    public function assets() {
        $this->render_divi_template();
        wp_enqueue_style('zzznm', plugins_url('assets/css/frontend.css', ZZZNM_FILE), [], ZZZNM_VERSION);
        wp_enqueue_script('zzznm', plugins_url('assets/js/frontend.js', ZZZNM_FILE), [], ZZZNM_VERSION, true);
        wp_localize_script('zzznm', 'ZZZNM', ['endpoint' => admin_url('admin-ajax.php'),
            'pageContext' => ['front' => is_front_page(), 'archive' => is_archive() || is_home(),
                'post' => is_singular('post'), 'page' => is_page(), 'id' => get_queried_object_id()],
            'complianzDelay' => (int) $this->manager->settings()['complianz_delay'],
            'preview' => !$this->preview_id && (is_preview() || isset($_GET['elementor-preview']) || isset($_GET['et_fb'])),
            'adminPreview' => (bool) $this->preview_id, 'previewId' => $this->preview_id,
            'previewNonce' => $this->preview_id ? wp_create_nonce('zzznm_preview_' . $this->preview_id) : '',
            'close' => 'Hinweis schließen', 'pause' => 'Pause', 'play' => 'Fortsetzen', 'next' => 'Nächste Meldung']);
    }

    private function render_divi_template() {
        $id = $this->manager->divi_template_id((bool) $this->preview_id);
        if (!$id) { return; }
        $post = get_post($id);
        if (!$post || trim($post->post_content) === '') { return; }
        // Render during enqueue, before wp_head, so Divi can collect module styles.
        if (has_blocks($post->post_content) && class_exists('WP_Block_Type_Registry')
            && WP_Block_Type_Registry::get_instance()->is_registered('divi/global-layout')) {
            $html = do_blocks('<!-- wp:divi/global-layout {"globalModule":"' . $id . '"} /-->');
        } elseif (!has_blocks($post->post_content) && function_exists('et_builder_render_layout')) {
            $html = et_builder_render_layout($post->post_content);
        } else { return; }
        if (trim($html) !== '') { $this->divi_html = $html; $this->divi_id = $id; }
    }

    public function prepare_template() {
        if ($this->preview_id) { echo '<aside class="zzznm-preview-banner" role="status">Popup-Vorschau – zuletzt gespeicherter Inhalt. Nur für dich sichtbar. <button type="button" data-zzznm-preview-reopen>Erneut öffnen</button><span data-zzznm-preview-error></span></aside>'; }
        if ($this->divi_id) {
            echo '<div id="zzznm-divi-template" data-template-id="' . (int) $this->divi_id . '" hidden><div class="zzznm-divi-content et-l et-l--body">' . $this->divi_html . '</div></div>';
        }

        $id = $this->manager->template_id();
        $callback = ['ElementorPro\\Modules\\Popup\\Module', 'add_popup_to_location'];
        if ($id && is_callable($callback) && ($this->preview_id || $this->manager->settings()['enabled'])) {
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
        $context = isset($_POST['page_context']) && is_string($_POST['page_context'])
            ? json_decode(wp_unslash($_POST['page_context']), true) : [];
        $popups = array_values(array_filter($popups, function ($post) use ($context) {
            return $this->manager->matches_location($post->ID, $context);
        }));
        $tickers = [];
        foreach ($this->manager->active(ZZZNM_Manager::TICKER, $now) as $post) {
            $tickers[] = ['id' => $post->ID, 'title' => (string) get_post_meta($post->ID, '_zzznm_ticker_title', true), 'html' => $this->manager->body($post)];
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
            'divi_template' => $this->manager->divi_template_id(), 'now' => $now, 'next' => $next]);
    }

    public function popup_shortcode($part) {
        if (!$this->preview_id && !$this->manager->is_enabled(ZZZNM_Manager::POPUP)) { return ''; }
        // Populated from the uncached endpoint, including in cached Elementor markup.
        $tag = in_array($part, ['heading', 'button', 'button_text'], true) ? 'span' : 'div';
        return '<' . $tag . ' data-zzznm-popup="' . esc_attr($part) . '"></' . $tag . '>';
    }

    public function ticker($attributes) {
        if (!$this->manager->is_enabled(ZZZNM_Manager::TICKER)) { return ''; }
        $attributes = shortcode_atts(['mode' => '', 'interval' => '', 'speed' => ''], $attributes, 'notice_ticker');
        $mode = in_array($attributes['mode'], ['rotate', 'marquee'], true) ? $attributes['mode'] : '';
        $interval = $attributes['interval'] === '' ? '' : max(2, min(60, (int) $attributes['interval']));
        $speed = $attributes['speed'] === '' ? '' : max(10, min(200, (int) $attributes['speed']));
        return '<section class="zzznm-ticker" aria-label="Aktuelle Hinweise" data-zzznm-ticker data-mode="' . esc_attr($mode)
            . '" data-interval="' . esc_attr($interval) . '" data-speed="' . esc_attr($speed) . '" hidden></section>';
    }
}
