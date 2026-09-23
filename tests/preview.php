<?php
// Isolated authorization checks; no real WordPress installation required.
define('ABSPATH', __DIR__);
function add_action(...$args) {}
function add_shortcode(...$args) {}
function absint($v) { return abs((int) $v); }
function sanitize_text_field($v) { return trim(strip_tags($v)); }
function wp_unslash($v) { return stripslashes($v); }
function current_user_can($cap, ...$args) { return $GLOBALS['caps'][$cap] ?? false; }
function wp_verify_nonce($nonce, $action) { return $nonce === $action; }
function get_post($id) { return $GLOBALS['posts'][$id] ?? null; }
require dirname(__DIR__) . '/includes/class-notice-manager.php';
require dirname(__DIR__) . '/includes/class-frontend.php';
$frontend = new ZZZNM_Frontend(new ZZZNM_Manager());
$posts[12] = (object) ['post_type' => ZZZNM_Manager::POPUP, 'post_status' => 'draft'];
$caps = ['manage_options' => true, 'edit_post' => true];
$input = ['zzznm_preview' => 12, 'zzznm_nonce' => 'zzznm_preview_12'];
$checks = 0;
function verify($expected, $input) {
    $GLOBALS['checks']++;
    if ($GLOBALS['frontend']->authorized_preview($input) !== $expected) { throw new RuntimeException('Preview authorization failed'); }
}
verify(12, $input);
verify(0, []);
verify(0, ['zzznm_preview' => 12]);
verify(0, ['zzznm_preview' => 12, 'zzznm_nonce' => 'wrong']);
verify(0, ['zzznm_preview' => [12], 'zzznm_nonce' => ['bad']]);
verify(0, ['zzznm_preview' => 13, 'zzznm_nonce' => 'zzznm_preview_12']);
$caps['manage_options'] = false; verify(0, $input);
$caps['manage_options'] = true; $caps['edit_post'] = false; verify(0, $input);
$caps['edit_post'] = true;
foreach (['trash', 'auto-draft', 'inherit'] as $status) { $posts[12]->post_status = $status; verify(0, $input); }
$posts[12]->post_status = 'publish'; $posts[12]->post_type = 'page'; verify(0, $input);
echo "PASS: {$checks} preview authorization checks.\n";
