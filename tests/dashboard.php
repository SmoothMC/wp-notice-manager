<?php
define('ABSPATH', __DIR__);
$posts = []; $meta = []; $options = []; $caps = ['zzznm_manage_notices' => true, 'edit_post' => true]; $denied = []; $checks = 0;
function add_action(...$args) {}
function current_user_can($cap, $id = 0) { return !empty($GLOBALS['caps'][$cap]) && !in_array($id, $GLOBALS['denied'], true); }
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function wp_parse_args($args, $defaults) { return array_merge($defaults, $args); }
function get_post_meta($id, $key, $single) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function get_posts($args) { return array_values(array_filter($GLOBALS['posts'], static function ($p) use ($args) {
    return in_array($p->post_type, $args['post_type'], true) && in_array($p->post_status, $args['post_status'], true);
})); }
function get_post_type($id) { return $GLOBALS['posts'][$id]->post_type ?? ''; }
function get_post_status($id) { return $GLOBALS['posts'][$id]->post_status ?? ''; }
function get_post_time($format, $gmt, $post) { return $post->publication; }
function absint($value) { return abs((int) $value); }
function wp_date($format, $time) { return (new DateTimeImmutable('@' . $time))->setTimezone(new DateTimeZone('Europe/Berlin'))->format($format); }
function wp_timezone_string() { return 'Europe/Berlin'; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function esc_url($s) { return esc_html($s); }
function admin_url($p) { return '/wp-admin/' . $p; }
function home_url($p) { return 'https://notices.test' . $p; }
function wp_create_nonce($action) { return $action; }
function add_query_arg($args, $url) { return $url . '?' . http_build_query($args); }
function wp_add_dashboard_widget($id, $title, $callback) { $GLOBALS['widget'] = compact('id', 'title', 'callback'); }
require dirname(__DIR__) . '/includes/class-notice-manager.php';
require dirname(__DIR__) . '/includes/class-dashboard.php';
function check($ok, $message) { $GLOBALS['checks']++; if (!$ok) { throw new RuntimeException($message); } }
function fixture($id, $from, $until, $type = 'zzznm_popup', $status = 'publish') {
    $p = (object) ['ID' => $id, 'post_type' => $type, 'post_status' => $status, 'post_title' => 'Hinweis ' . $id, 'post_password' => '', 'menu_order' => 0, 'publication' => 0];
    $GLOBALS['posts'][$id] = $p; $GLOBALS['meta'][$id] = ['_zzznm_from' => $from, '_zzznm_until' => $until]; return $p;
}
function ids($entries) { return array_map(static function ($e) { return $e['post']->ID; }, $entries); }
$manager = new ZZZNM_Manager(); $dashboard = new ZZZNM_Dashboard($manager);
$dashboard->register(); check($widget['title'] === 'WP Notice Manager', 'Widget registered with short name');
$now = time();
fixture(1, $now-100, $now+4000); fixture(2, $now-100, $now+4000); $meta[2]['_zzznm_priority'] = 50;
fixture(3, 0, 0, ZZZNM_Manager::TICKER)->menu_order = 10;
fixture(4, 0, 0, ZZZNM_Manager::TICKER)->menu_order = 2;
fixture(5, $now+900, $now+4000); fixture(6, $now+100, $now+4000);
fixture(7, $now-100, $now); // End exclusive.
fixture(8, 0, 0, ZZZNM_Manager::POPUP, 'draft');
fixture(9, $now-100, $now+4000); $meta[9]['_zzznm_location'] = 'selected'; $meta[9]['_zzznm_pages'] = [500];
fixture(500, 0, 0, 'page', 'draft');
fixture(10, $now-100, $now+4000)->post_password = 'secret';
fixture(11, $now-100, $now+4000, ZZZNM_Manager::POPUP, 'draft'); // Complete draft is not scheduled.
fixture(12, $now+200, $now+4000, ZZZNM_Manager::POPUP, 'future')->publication = $now+300;
fixture(13, $now+200, $now+4000, ZZZNM_Manager::POPUP, 'future')->publication = $now+5000;
fixture(14, $now-200, $now+4000, ZZZNM_Manager::POPUP, 'future')->publication = $now-100;
fixture(15, $now-100, $now+4000); $denied = [15];
$d = $dashboard->data($now);
check(ids($d['active']) === [2,1,4,3], 'Active popups follow priority and tickers follow menu order');
check(ids($d['planned']) === [6,12,5], 'Future items chronological; complete drafts excluded');
check($d['planned'][1]['from'] === $now+300, 'WordPress publication delays actual start');
check(ids($d['issues']) === [8,9,10,13,14], 'Missing dates, unavailable pages, password, late or missed publication require attention');
check(!in_array(7, ids($d['active']), true), 'End exclusive');
check(strpos($dashboard->location($posts[9]), '0 ') === 0, 'Unpublished selected page does not count');
$posts[500]->post_status = 'publish'; $meta[9]['_zzznm_pages'] = [500,500];
check($dashboard->location($posts[9]) === '1 ausgewählte Seite', 'Unique published page count');
$meta[1]['_zzznm_location'] = 'home'; check($dashboard->location($posts[1]) === 'Nur Homepage', 'Location label');
check($dashboard->location($posts[3]) === 'Am eingebundenen Ticker-Shortcode', 'Ticker location does not claim global visibility');
$options[ZZZNM_Manager::OPTION] = ['enabled' => 0];
$d = $dashboard->data($now); check(ids($d['active']) === [4,3] && !$d['planned'] && !$d['issues'], 'Disabled type excluded throughout');
$options[ZZZNM_Manager::OPTION] = []; $caps = [];
check($dashboard->data($now) === ['active'=>[], 'planned'=>[], 'issues'=>[]], 'Unauthorized data hidden');
unset($widget); $dashboard->register(); check(!isset($widget), 'Unauthorized widget not registered');
ob_start(); $dashboard->render(); check(ob_get_clean() === '', 'Unauthorized callback outputs nothing');
$caps = ['zzznm_manage_notices'=>true, 'edit_post'=>true]; $posts = []; $meta = []; $denied = [];
$end = (new DateTimeImmutable('tomorrow', new DateTimeZone('Europe/Berlin')))->getTimestamp()-1;
fixture(20, $now-3600, $end)->post_title = '<script>alert("x")</script>';
for ($i=21; $i<=27; $i++) { fixture($i, $now+($i*60), $now+86400); }
ob_start(); $dashboard->render(); $html = ob_get_clean();
check(strpos($html, '<script>') === false && strpos($html, '&lt;script&gt;') !== false, 'Titles escaped');
check(strpos($html, 'Endet heute') !== false, 'Today label follows site timezone');
check(strpos($html, 'Als Nächstes geplant (7)') !== false && strpos($html, 'Hinweis 26') === false, 'Only next five planned items shown with total');
check(strpos($html, 'zzznm_nonce=zzznm_preview_20') !== false, 'Preview link nonce bound to popup');
check(strpos($html, '>Einstellungen<') === false, 'Employee has no settings link');
check(strpos($html, 'Popup erstellen') !== false && strpos($html, 'Ticker erstellen') !== false, 'Creation shortcuts available');
check(strpos($html, 'Funktionsstatus') === false, 'No function status section');
$caps['manage_options'] = true; ob_start(); $dashboard->render(); $html = ob_get_clean();
check(strpos($html, '>Einstellungen<') !== false, 'Admin settings shortcut available');
if (getenv('ZZZNM_DASHBOARD_HTML')) { file_put_contents(getenv('ZZZNM_DASHBOARD_HTML'), $html); }
echo "PASS: {$checks} dashboard checks.\n";
