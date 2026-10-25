<?php
if (!defined('ABSPATH')) { exit; }

final class ZZZNM_Dashboard {
    private $manager;

    public function __construct($manager) {
        $this->manager = $manager;
        add_action('wp_dashboard_setup', [$this, 'register']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
    }

    public function assets($hook) {
        if ($hook === 'index.php' && current_user_can(ZZZNM_Manager::MANAGE_NOTICES)) {
            wp_enqueue_style('zzznm-dashboard', plugins_url('assets/css/dashboard.css', ZZZNM_FILE), [], ZZZNM_VERSION);
        }
    }

    public function register() {
        if (current_user_can(ZZZNM_Manager::MANAGE_NOTICES)) {
            wp_add_dashboard_widget('zzznm_dashboard', 'WP Notice Manager', [$this, 'render']);
        }
    }

    private function page_ids($id) {
        return array_values(array_filter(array_unique(array_map('absint', (array) get_post_meta($id, '_zzznm_pages', true))), static function ($page) {
            return $page && get_post_type($page) === 'page' && get_post_status($page) === 'publish';
        }));
    }

    public function location($post) {
        if ($post->post_type === ZZZNM_Manager::TICKER) { return 'Am eingebundenen Ticker-Shortcode'; }
        $mode = $this->manager->location_mode($post->ID);
        if ($mode !== 'selected') { return $this->manager->locations()[$mode]; }
        $count = count($this->page_ids($post->ID));
        return $count . ($count === 1 ? ' ausgewählte Seite' : ' ausgewählte Seiten');
    }

    public function data($now = null) {
        $result = ['active' => [], 'planned' => [], 'issues' => []];
        if (!current_user_can(ZZZNM_Manager::MANAGE_NOTICES)) { return $result; }
        $now = $now ?? time();
        $types = array_values(array_filter([ZZZNM_Manager::POPUP, ZZZNM_Manager::TICKER], [$this->manager, 'is_enabled']));
        if (!$types) { return $result; }
        foreach (get_posts(['post_type' => $types, 'post_status' => ['publish', 'future', 'draft', 'pending'],
            'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC']) as $post) {
            if (!current_user_can('edit_post', $post->ID)) { continue; }
            $popup = $post->post_type === ZZZNM_Manager::POPUP;
            $range = $this->manager->schedule($post->ID);
            $issues = [];
            $error = $this->manager->validate($post->post_type, $post->ID, $range['from'], $range['until']);
            if ($error) { $issues[] = $error; }
            if (get_post_meta($post->ID, '_zzznm_raw_from', true) || get_post_meta($post->ID, '_zzznm_raw_until', true)) {
                $issues[] = 'Ungültige Datumseingabe korrigieren.';
            }
            if ($popup && $this->manager->location_mode($post->ID) === 'selected' && !$this->page_ids($post->ID)) {
                $issues[] = 'Keine veröffentlichte Einzelseite ausgewählt.';
            }
            if ($post->post_password !== '') { $issues[] = 'Passwortgeschützte Hinweise werden nicht angezeigt.'; }
            $entry = ['post' => $post, 'from' => $range['from'], 'until' => $range['until'], 'issues' => $issues];
            if ($issues) { $result['issues'][] = $entry; continue; }
            if (!in_array($post->post_status, ['publish', 'future'], true) || ($range['until'] && $range['until'] <= $now)) { continue; }
            // A WordPress-scheduled post cannot appear before its publication date.
            if ($post->post_status === 'future') {
                $publication = (int) get_post_time('U', true, $post);
                $entry['from'] = max($entry['from'], $publication);
                if ($entry['until'] && $entry['from'] >= $entry['until']) {
                    $entry['issues'] = ['WordPress-Veröffentlichung liegt am oder nach dem Ende des Anzeigezeitraums.'];
                    $result['issues'][] = $entry; continue;
                }
                if ($publication <= $now) {
                    $entry['issues'] = ['Geplante WordPress-Veröffentlichung steht noch aus.'];
                    $result['issues'][] = $entry; continue;
                }
            }
            $result[$entry['from'] > $now ? 'planned' : 'active'][] = $entry;
        }
        usort($result['active'], function ($a, $b) {
            $a_popup = $a['post']->post_type === ZZZNM_Manager::POPUP;
            $b_popup = $b['post']->post_type === ZZZNM_Manager::POPUP;
            if ($a_popup !== $b_popup) { return $a_popup ? -1 : 1; }
            $order = $a_popup ? $this->manager->priority($b['post']->ID) <=> $this->manager->priority($a['post']->ID)
                : $a['post']->menu_order <=> $b['post']->menu_order;
            return $order ?: $a['post']->ID <=> $b['post']->ID;
        });
        usort($result['planned'], static function ($a, $b) { return ($a['from'] <=> $b['from']) ?: ($a['post']->ID <=> $b['post']->ID); });
        usort($result['issues'], static function ($a, $b) { return $a['post']->ID <=> $b['post']->ID; });
        return $result;
    }

    private function section($label, $entries, $kind, $limit, $now) {
        echo '<section class="zzznm-dashboard-section"><h3>' . esc_html($label) . ' <span class="zzznm-dashboard-count">(' . count($entries) . ')</span></h3>';
        if (!$entries) { echo '<p class="zzznm-dashboard-empty">Keine Einträge.</p></section>'; return; }
        echo '<ul class="zzznm-dashboard-list">';
        $rank = 0;
        foreach (array_slice($entries, 0, $limit) as $entry) {
            $post = $entry['post'];
            $popup = $post->post_type === ZZZNM_Manager::POPUP;
            $title = trim($post->post_title) !== '' ? $post->post_title : '(Ohne Titel)';
            $label = $popup ? 'Popup' : 'Ticker';
            if ($kind === 'active' && $popup) { $label .= ' · Reihenfolge ' . ++$rank; }
            if ($popup) { $label .= ' · Priorität ' . $this->manager->priority($post->ID); }
            echo '<li><div class="zzznm-dashboard-entry-head"><strong>' . esc_html($title) . '</strong><span class="zzznm-dashboard-type">' . ($popup ? 'Popup' : 'Ticker') . '</span></div><div class="zzznm-dashboard-meta">' . esc_html($label) . '</div><div class="zzznm-dashboard-detail">';
            echo esc_html($this->location($post)) . '<br>';
            if ($kind === 'issues') {
                echo esc_html(implode(' ', $entry['issues'])) . '<br>';
            } else {
                $start = $kind === 'planned' ? 'Ab ' . wp_date('d.m.Y. H:i', $entry['from']) . ' · ' : '';
                echo esc_html($start . ($entry['until'] ? 'Aktiv bis ' . wp_date('d.m.Y. H:i', $entry['until']) : 'Ohne Enddatum'));
                if ($kind === 'active' && $entry['until'] && wp_date('Y-m-d', $entry['until']) === wp_date('Y-m-d', $now)) {
                    echo ' <strong>' . esc_html('· Endet heute') . '</strong>';
                }
                echo '<br>';
            }
            echo '</div><div class="zzznm-dashboard-actions"><a href="' . esc_url(admin_url('post.php?post=' . $post->ID . '&action=edit')) . '">Bearbeiten</a>';
            if ($popup) {
                $url = add_query_arg(['zzznm_preview' => $post->ID, 'zzznm_nonce' => wp_create_nonce('zzznm_preview_' . $post->ID)], home_url('/'));
                echo ' · <a href="' . esc_url($url) . '" target="_blank" rel="noopener">Popup-Vorschau</a>';
            }
            echo '</div></li>';
        }
        echo '</ul>';
        if (count($entries) > $limit) { echo '<p class="zzznm-dashboard-empty">' . esc_html('Die ersten ' . $limit . ' Einträge werden angezeigt. Weitere findest du über die Listen unten.') . '</p>'; }
        echo '</section>';
    }

    public function render() {
        if (!current_user_can(ZZZNM_Manager::MANAGE_NOTICES)) { return; }
        $now = time(); $data = $this->data($now);
        echo '<div class="zzznm-dashboard"><div class="zzznm-dashboard-stats">';
        foreach (['active' => ['Aktiv', 'megaphone'], 'planned' => ['Geplant', 'calendar-alt'], 'issues' => ['Zu prüfen', 'flag']] as $key => $stat) {
            echo '<div class="zzznm-dashboard-stat"><span class="dashicons dashicons-' . esc_attr($stat[1]) . '" aria-hidden="true"></span><strong>' . count($data[$key]) . '</strong><span>' . esc_html($stat[0]) . '</span></div>';
        }
        echo '</div>';
        $this->section('Aktuell aktiv', $data['active'], 'active', 10, $now);
        $this->section('Als Nächstes geplant', $data['planned'], 'planned', 5, $now);
        $this->section('Handlungsbedarf', $data['issues'], 'issues', 5, $now);
        echo '<div class="zzznm-dashboard-note"><span>Zeitzone: ' . esc_html(wp_timezone_string()) . '</span></div><div class="zzznm-dashboard-footer">';
        foreach ([ZZZNM_Manager::POPUP => ['Popup', 'Popups'], ZZZNM_Manager::TICKER => ['Ticker', 'Ticker']] as $type => $labels) {
            if (!$this->manager->is_enabled($type)) { continue; }
            echo '<div class="zzznm-dashboard-shortcut"><a class="button button-small" href="' . esc_url(admin_url('post-new.php?post_type=' . $type)) . '">' . esc_html($labels[0] . ' erstellen') . '</a> ';
            echo '<a href="' . esc_url(admin_url('edit.php?post_type=' . $type)) . '">' . esc_html('Alle ' . $labels[1]) . '</a></div>';
        }
        if (current_user_can('manage_options')) {
            echo '<a class="zzznm-dashboard-settings" href="' . esc_url(admin_url('admin.php?page=zzznm')) . '">Einstellungen</a>';
        }
        echo '</div></div>';
    }
}
