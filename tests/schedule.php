<?php
// Isolated domain tests with minimal WordPress doubles. Run: php tests/schedule.php
define('ABSPATH', __DIR__);
$posts = []; $meta = []; $options = []; $checks = 0; $notice = '';
function add_action(...$args) {}
function add_filter(...$args) {}
function add_shortcode(...$args) {}
function wp_script_is($handle, $status) { return !empty($GLOBALS['scripts'][$handle][$status]); }
function wp_json_encode($value) { return json_encode($value); }
function wp_add_inline_script($handle, $code, $position) { $GLOBALS['inline'] = $code; }
function wp_timezone() { return new DateTimeZone('Europe/Berlin'); }
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function wp_parse_args($args, $defaults) { return array_merge($defaults, $args); }
function get_post_meta($id, $key, $single) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function update_post_meta($id, $key, $value) { $GLOBALS['meta'][$id][$key] = $value; }
function get_posts($args) {
    return array_values(array_filter($GLOBALS['posts'], static function ($p) use ($args) {
        return in_array($p->post_type, (array) $args['post_type'], true)
            && in_array($p->post_status, (array) $args['post_status'], true)
            && !in_array($p->ID, $args['exclude'] ?? [], true);
    }));
}
function wp_verify_nonce($nonce, $action) { return $nonce === $action; }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function wp_unslash($value) { return stripslashes($value); }
function current_user_can(...$args) { return true; }
function get_current_user_id() { return 1; }
function set_transient($key, $value, $expiry) { $GLOBALS['notice'] = $value; }
function wp_is_post_revision($id) { return false; }
function wp_is_post_autosave($id) { return false; }
function esc_url_raw($value, $protocols = []) { return $value; } // Domain double; WordPress owns URL sanitization.
function wp_kses_post($value) { return $value; }
function wpautop($value) { return '<p>' . $value . '</p>'; }
function absint($value) { return abs((int) $value); }
function wp_update_post($data) { $GLOBALS['posts'][$data['ID']]->post_status = $data['post_status']; }
$wpdb = new class {
    public $prefix = 'test_'; public $available = true;
    public function prepare($query, ...$args) { return $query; }
    public function get_var($query) { return $this->available ? 1 : 0; }
};
require dirname(__DIR__) . '/includes/class-notice-manager.php';
require dirname(__DIR__) . '/includes/class-admin.php';
require dirname(__DIR__) . '/includes/class-frontend.php';
function check($condition, $message) {
    $GLOBALS['checks']++;
    if (!$condition) { throw new RuntimeException($message); }
}
function fixture($id, $from, $until, $type = ZZZNM_Manager::POPUP, $status = 'publish') {
    $GLOBALS['posts'][$id] = (object) ['ID' => $id, 'post_type' => $type, 'post_status' => $status,
        'post_title' => 'Hinweis ' . $id, 'post_content' => 'Inhalt', 'post_password' => ''];
    $GLOBALS['meta'][$id] = ['_zzznm_from' => $from, '_zzznm_until' => $until];
    return $GLOBALS['posts'][$id];
}
$manager = new ZZZNM_Manager();
$admin = new ZZZNM_Admin($manager);
check($manager->settings()['complianz_delay'] === 300, 'Existing settings default to 300ms');
check($admin->sanitize_settings([])['complianz_delay'] === 300, 'Missing release delay defaults to 300ms');
check($admin->sanitize_settings(['complianz_delay' => 0])['complianz_delay'] === 0, 'Zero release delay is preserved');
check($admin->sanitize_settings(['complianz_delay' => 1250])['complianz_delay'] === 1250, 'Custom release delay preserved');
check($admin->sanitize_settings(['complianz_delay' => -100])['complianz_delay'] === 0, 'Negative delay clamped');
check($admin->sanitize_settings(['complianz_delay' => 999999])['complianz_delay'] === 60000, 'Release delay bounded');
function register_post_type($type, $args) { $GLOBALS['registered'][] = $type; }
$options[ZZZNM_Manager::OPTION] = ['enabled' => 0, 'ticker_enabled' => 0];
$registered = []; $manager->register_types();
check($registered === [], 'Disabled modules do not register post types');
check($manager->active(ZZZNM_Manager::TICKER) === [], 'Disabled ticker returns no posts');
$frontend = new ZZZNM_Frontend($manager);
check($frontend->ticker([]) === '', 'Disabled ticker shortcode is empty');
check($frontend->popup_shortcode('notice') === '', 'Disabled popup shortcode is empty');
$options[ZZZNM_Manager::OPTION] = ['enabled' => 0, 'ticker_enabled' => 1];
$registered = []; $manager->register_types();
check($registered === [ZZZNM_Manager::TICKER], 'Ticker independent of popup');
$options[ZZZNM_Manager::OPTION] = ['enabled' => 1, 'ticker_enabled' => 0];
$registered = []; $manager->register_types();
check($registered === [ZZZNM_Manager::POPUP], 'Popup independent of ticker');
$options[ZZZNM_Manager::OPTION] = [];
check($manager->is_enabled(ZZZNM_Manager::TICKER), 'Existing installs retain ticker');
check($manager->dismiss_mode(999) === 'content', 'Popup default is content');
$meta[999]['_zzznm_dismiss'] = 'always';
check($manager->dismiss_mode(999) === 'always', 'Per popup mode preserved');
check($manager->dismiss_mode(998) === 'content', 'Other popup unaffected');
$meta[999]['_zzznm_dismiss'] = 'invalid';
check($manager->dismiss_mode(999) === 'content', 'Invalid mode falls back');
function get_post_type($id) { return $GLOBALS['posts'][$id]->post_type ?? ''; }
function get_post_status($id) { return $GLOBALS['posts'][$id]->post_status ?? ''; }
function has_term($term, $taxonomy, $id) { return $taxonomy === 'layout_tag' && in_array($term, $GLOBALS['tags'][$id] ?? [], true); }
fixture(800, 0, 0, 'et_pb_layout');
check(!$manager->valid_divi_template(800), 'Untagged Divi layout rejected');
$tags[800] = ['popup'];
check($manager->valid_divi_template(800), 'Published Popup-tagged Divi layout accepted');
$posts[800]->post_status = 'draft';
check(!$manager->valid_divi_template(800), 'Draft Divi layout rejected');
$posts[800]->post_status = 'publish'; $posts[800]->post_type = 'page';
check(!$manager->valid_divi_template(800), 'Other post types rejected despite tag');
$posts = []; $meta = [];
$parse = [ZZZNM_Manager::class, 'parse_date'];
check($parse('') === 0, 'Empty dates rejected');
check($parse('2026-02-30T12:00') === 0, 'Invalid calendar date rejected');
check($parse('2026-03-29T02:30') === 0, 'Nonexistent DST hour rejected');
check($parse('2026-09-22T12:00') === strtotime('2026-09-22T10:00:00Z'), 'Summer timezone conversion');
check($parse('2026-01-22T12:00') === strtotime('2026-01-22T11:00:00Z'), 'Winter timezone conversion');
check($parse('2026-03-29T03:30') - $parse('2026-03-29T01:30') === 3600, 'DST transition uses real duration');
check($manager->validate(ZZZNM_Manager::POPUP, 0, 0, 200) !== '', 'Start required');
check($manager->validate(ZZZNM_Manager::POPUP, 0, 100, 0) !== '', 'End required');
check($manager->validate(ZZZNM_Manager::POPUP, 0, 100, 100) !== '', 'Zero duration rejected');
check($manager->validate(ZZZNM_Manager::POPUP, 0, 200, 100) !== '', 'Reverse range rejected');
fixture(1, 100, 200);
check($manager->validate(ZZZNM_Manager::POPUP, 2, 200, 300) === '', 'Adjacent ranges allowed');
check($manager->validate(ZZZNM_Manager::POPUP, 2, 50, 100) === '', 'Adjacent preceding range allowed');
check($manager->validate(ZZZNM_Manager::POPUP, 2, 150, 250) !== '', 'Partial overlap rejected');
check($manager->validate(ZZZNM_Manager::POPUP, 2, 50, 250) !== '', 'Enclosing overlap rejected');
check($manager->validate(ZZZNM_Manager::POPUP, 2, 120, 180) !== '', 'Contained overlap rejected');
check($manager->validate(ZZZNM_Manager::POPUP, 1, 100, 200) === '', 'Editing same post allowed');
fixture(2, 300, 400, ZZZNM_Manager::POPUP, 'draft');
check($manager->validate(ZZZNM_Manager::POPUP, 3, 300, 400) === '', 'Drafts do not reserve time');
check(count($manager->active(ZZZNM_Manager::POPUP, 100)) === 1, 'Start inclusive');
check(count($manager->active(ZZZNM_Manager::POPUP, 200)) === 0, 'End exclusive');
fixture(3, 100, 200); // Corrupt external data must still never yield two popups.
check(count($manager->active(ZZZNM_Manager::POPUP, 150)) === 1, 'Only one active popup');
$posts[1]->post_password = 'secret'; $posts[3]->post_password = 'secret';
check(count($manager->active(ZZZNM_Manager::POPUP, 150)) === 0, 'Password protected content excluded');
fixture(4, 0, 0, ZZZNM_Manager::TICKER);
fixture(5, 0, 200, ZZZNM_Manager::TICKER);
check(count($manager->active(ZZZNM_Manager::TICKER, 150)) === 2, 'Concurrent tickers allowed');
check(count($manager->active(ZZZNM_Manager::TICKER, 200)) === 1, 'Optional ticker end respected');
check($manager->validate(ZZZNM_Manager::TICKER, 6, 0, 0) === '', 'Ticker dates optional');
check($manager->validate(ZZZNM_Manager::TICKER, 6, 300, 200) !== '', 'Reversed ticker range rejected');
$_POST = ['zzznm_nonce' => 'zzznm_save_2', 'zzznm_from' => '2026-09-22T12:00', 'zzznm_until' => ''];
$data = ['post_type' => ZZZNM_Manager::POPUP, 'post_status' => 'publish'];
$validated = $admin->validate_post($data, ['ID' => 2]);
check($validated['post_status'] === 'draft', 'Incomplete publication becomes draft');
check(strpos($notice, 'Enddatum') !== false, 'Clear validation message');
$_POST['zzznm_until'] = '2026-09-22T13:00';
check($admin->validate_post($data, ['ID' => 2])['post_status'] === 'publish', 'Valid publication allowed');
$admin->save(2, $posts[2]);
check($meta[2]['_zzznm_until'] === $parse('2026-09-22T13:00'), 'Dates saved as timestamps');
$admin->unlock(); $wpdb->available = false;
check($admin->validate_post($data, ['ID' => 2])['post_status'] === 'draft', 'Concurrent publication fails closed');
$wpdb->available = true; $_POST = [];
check($admin->validate_post($data, ['ID' => 9, 'meta_input' => ['_zzznm_from' => 100, '_zzznm_until' => 200]])['post_status'] === 'draft', 'Programmatic meta_input overlap rejected');
$post = fixture(7, 500, 600);
$key = $manager->popup_data($post)['key']; $post->post_content = 'Neu';
check($manager->popup_data($post)['key'] !== $key, 'Content changes reset dismissal');
$meta[$post->ID]['_zzznm_dismiss'] = 'forever';
$key = $manager->popup_data($post)['key']; $post->post_content = 'Wieder neu';
check($manager->popup_data($post)['key'] === $key, 'Permanent dismissal survives changes');
$meta[$post->ID]['_zzznm_dismiss'] = 'date';
$key = $manager->popup_data($post)['key']; $post->post_content = 'Noch einmal';
check($manager->popup_data($post)['key'] === $key, 'Date dismissal ignores content changes');
$meta[7]['_zzznm_until'] = 650;
check($manager->popup_data($post)['key'] !== $key, 'Date changes reset date dismissal');
$admin->unlock();
$frontend = new ZZZNM_Frontend($manager);
$frontend->complianz_config();
check(strpos($GLOBALS['inline'], '= false;') !== false, 'No banner script means no forced wait');
$GLOBALS['scripts']['cmplz-cookiebanner']['enqueued'] = true;
$frontend->complianz_config();
check(strpos($GLOBALS['inline'], '= true;') !== false, 'Queued banner protects delayed initialization');
$GLOBALS['scripts']['cmplz-cookiebanner'] = ['done' => true];
$frontend->complianz_config();
check(strpos($GLOBALS['inline'], '= true;') !== false, 'Already printed banner remains detected');
foreach (glob(dirname(__DIR__) . '/includes/*.php') as $file) { token_get_all(file_get_contents($file), TOKEN_PARSE); }
token_get_all(file_get_contents(dirname(__DIR__) . '/notice-manager-by-zzzooo.php'), TOKEN_PARSE);
$meta[$post->ID]['_zzznm_dismiss'] = 'content';
$key = $manager->popup_data($post)['key'];
$meta[$post->ID]['_zzznm_button_text'] = 'Details';
$meta[$post->ID]['_zzznm_button_url'] = 'https://example.org/info';
$button_data = $manager->popup_data($post);
check($button_data['button_text'] === 'Details' && $button_data['button_url'] === 'https://example.org/info', 'Button data is per popup');
check($button_data['key'] !== $key, 'Button changes reset content dismissal');
$meta[$post->ID]['_zzznm_dismiss'] = 'forever';
$key = $manager->popup_data($post)['key'];
$meta[$post->ID]['_zzznm_button_url'] = 'https://example.org/changed';
check($manager->popup_data($post)['key'] === $key, 'Button changes preserve forever dismissal');
check($manager->matches_location(777, []), 'Existing popups default to all pages');
foreach (['home' => 'front', 'archives' => 'archive', 'posts' => 'post', 'pages' => 'page'] as $mode => $flag) {
    $meta[777]['_zzznm_location'] = $mode;
    check($manager->matches_location(777, [$flag => true]), 'Correct page context: ' . $mode);
    check(!$manager->matches_location(777, []), 'Missing context rejected: ' . $mode);
    check(!$manager->matches_location(777, [$flag => false]), 'Different context rejected: ' . $mode);
}
$meta[777]['_zzznm_location'] = 'selected'; $meta[777]['_zzznm_pages'] = [42, 84];
check($manager->matches_location(777, ['page' => true, 'id' => 42]), 'Selected page matches');
check($manager->matches_location(777, ['page' => true, 'front' => true, 'id' => 84]), 'Selected static homepage matches');
check(!$manager->matches_location(777, ['page' => true, 'id' => 21]), 'Unselected page rejected');
check(!$manager->matches_location(777, ['post' => true, 'id' => 42]), 'Posts are not selected pages');
check(!$manager->matches_location(777, ['page' => true, 'id' => []]), 'Malformed page context rejected');
$meta[777]['_zzznm_pages'] = [];
check(!$manager->matches_location(777, ['page' => true, 'id' => 42]), 'Empty selection displays nowhere');
check($manager->settings()['fade_duration'] === 300, 'Default fade duration');
check($admin->sanitize_settings(['fade_enabled' => 1, 'fade_duration' => 750])['fade_duration'] === 750, 'Custom fade duration');
check($admin->sanitize_settings(['fade_duration' => -20])['fade_duration'] === 0, 'Negative fade clamped');
check($admin->sanitize_settings(['fade_duration' => 99999])['fade_duration'] === 5000, 'Fade upper bound');
check($admin->sanitize_settings([])['fade_enabled'] === 0, 'Fade can be disabled');
echo "PASS: {$checks} checks; dates, DST, required fields, overlaps, boundaries, drafts, concurrent writes, active selection, dismissal keys; all PHP files parse.\n";
