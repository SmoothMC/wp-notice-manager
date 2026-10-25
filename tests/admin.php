<?php
// Focused admin, role and builder availability contracts with WordPress doubles.
define('ABSPATH', __DIR__);
$mode = $argv[1] ?? 'none';
if (in_array($mode, ['divi', 'both'], true)) { function et_builder_render_layout($html) { return $html; } }
if (in_array($mode, ['elementor', 'both'], true)) { eval('namespace ElementorPro\\Modules\\Popup; class Module {}'); }
$options = []; $caps = []; $menus = []; $registered = []; $checks = 0;
function add_action(...$args) {}
function add_filter(...$args) {}
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value, $autoload = null) { $GLOBALS['options'][$key] = $value; }
function wp_parse_args($args, $defaults) { return array_merge($defaults, $args); }
function current_user_can($cap, ...$args) { return !empty($GLOBALS['caps'][$cap]); }
function get_role($name) { return $GLOBALS['roles'][$name] ?? null; }
function admin_url($path) { return '/wp-admin/' . $path; }
function esc_url($s) { return htmlspecialchars($s, ENT_QUOTES); }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function esc_attr($s) { return esc_html($s); }
function add_menu_page($title, $label, $cap, $slug, $cb, ...$args) { $GLOBALS['menus'][] = compact('title', 'cap', 'slug', 'cb'); }
function add_submenu_page($parent, $title, $label, $cap, $slug, $cb) { $GLOBALS['menus'][] = compact('parent', 'title', 'cap', 'slug', 'cb'); }
function register_post_type($type, $args) { $GLOBALS['registered'][$type] = $args; }
function get_posts($args) { return []; }
function settings_errors() {}
function settings_fields($name) { echo '<input name="option_page" value="' . $name . '">'; }
function submit_button(...$args) {}
function selected($a, $b, $echo = false) { return (string) $a === (string) $b ? 'selected' : ''; }
function checked($a, $b, $echo = false) { return selected($a, $b, $echo); }
function absint($value) { return abs((int) $value); }
function get_post_type($id) { return ''; }
function get_post_status($id) { return ''; }
function get_post_meta(...$args) { return ''; }
function has_term(...$args) { return false; }
function plugin_basename($file) { return 'notice-manager-by-zzzooo/notice-manager-by-zzzooo.php'; }
define('ZZZNM_FILE', '/plugin/notice-manager-by-zzzooo.php');
require dirname(__DIR__) . '/includes/class-notice-manager.php';
require dirname(__DIR__) . '/includes/class-admin.php';
require dirname(__DIR__) . '/includes/class-updater.php';
class TestRole {
    public $capabilities = [];
    public $writes = 0;
    public function add_cap($cap) { $this->capabilities[$cap] = true; $this->writes++; }
}
class TestBar {
    public $nodes = [];
    public function add_node($node) { $this->nodes[$node['id']] = $node; }
}
function check($value, $message) { $GLOBALS['checks']++; if (!$value) { throw new RuntimeException($message); } }
function html($object, $method) { ob_start(); $object->$method(); return ob_get_clean(); }
foreach (['administrator', 'contributor', 'editor', 'author', 'subscriber'] as $role) { $roles[$role] = new TestRole(); }
$manager = new ZZZNM_Manager(); $admin = new ZZZNM_Admin($manager); $updater = new ZZZNM_Updater();
$manager->register_permissions();
check($roles['contributor']->capabilities === [ZZZNM_Manager::MANAGE_NOTICES => true], 'Contributors gain only notice capability');
check($roles['administrator']->capabilities === [ZZZNM_Manager::MANAGE_NOTICES => true], 'Administrators retain notice access');
foreach (['editor', 'author', 'subscriber'] as $role) { check($roles[$role]->capabilities === [], 'Other roles unchanged: ' . $role); }
$manager->register_permissions();
check($roles['contributor']->writes === 1, 'Permission migration does not write on each request');
$manager->register_types();
foreach ([ZZZNM_Manager::POPUP, ZZZNM_Manager::TICKER] as $type) {
    check($registered[$type]['map_meta_cap'] === true, 'Object permissions are mapped');
    check(array_unique(array_values($registered[$type]['capabilities'])) === [ZZZNM_Manager::MANAGE_NOTICES], 'All notice operations use notice capability');
}
$caps = [ZZZNM_Manager::MANAGE_NOTICES => true]; // Standard contributor; no manage_options.
$admin->menu();
check(count($menus) === 1 && $menus[0]['cap'] === ZZZNM_Manager::MANAGE_NOTICES, 'Contributor gets content menu only');
check(html($admin, 'settings_page') === '', 'Contributor cannot open settings');
$overview = html($admin, 'overview_page');
check(strpos($overview, 'post_type=zzznm_popup') !== false && strpos($overview, 'post_type=zzznm_ticker') !== false, 'Content landing links available');
check(strpos($overview, 'options.php') === false, 'No settings form on contributor landing');
$bar = new TestBar(); $admin->admin_bar($bar);
check(array_keys($bar->nodes) === ['zzznm', 'zzznm-zzznm_popup', 'zzznm-zzznm_ticker'], 'Contributor toolbar contains enabled content only');
check(strpos($bar->nodes['zzznm']['title'], 'dashicons-megaphone') !== false, 'Toolbar has icon');
check($bar->nodes['zzznm']['href'] === admin_url('edit.php?post_type=zzznm_popup'), 'Contributor parent opens allowed destination');
$options[ZZZNM_Manager::OPTION] = ['enabled' => 0];
$bar = new TestBar(); $admin->admin_bar($bar);
check(array_keys($bar->nodes) === ['zzznm', 'zzznm-zzznm_ticker'], 'Disabled popup absent');
$options[ZZZNM_Manager::OPTION] = ['enabled' => 0, 'ticker_enabled' => 0];
$bar = new TestBar(); $admin->admin_bar($bar); $menus = []; $admin->menu();
check($bar->nodes === [] && $menus === [], 'Both disabled hides employee entry');
$caps['manage_options'] = true;
$bar = new TestBar(); $admin->admin_bar($bar); $admin->menu();
check(array_keys($bar->nodes) === ['zzznm', 'zzznm-settings'], 'Administrator keeps settings when modules disabled');
check(count($menus) === 2 && $menus[1]['cap'] === 'manage_options', 'Settings submenu restricted');
check($menus[0]['cb'] === $menus[1]['cb'], 'Shared menu slug registers the same callback to avoid rendering twice');
$options[ZZZNM_Manager::OPTION] = [];
$choices = $manager->renderers(); $settings = html($admin, 'settings_page');
check(isset($choices['standalone']), 'Standalone always available');
foreach (['divi' => 'divi_template_id', 'elementor' => 'template_id'] as $builder => $field) {
    $available = $mode === $builder || $mode === 'both';
    check(isset($choices[$builder]) === $available, 'Renderer availability: ' . $builder);
    check((strpos($settings, 'name="zzznm_settings[' . $field . ']"') !== false) === $available, 'Template field visibility: ' . $builder);
    check((strpos($settings, '<option value="' . $builder . '"') !== false) === $available, 'Renderer option visibility: ' . $builder);
    check($admin->sanitize_settings(['renderer' => $builder])['renderer'] === ($available ? $builder : 'standalone'), 'Unavailable renderer cannot be submitted: ' . $builder);
}
$options[ZZZNM_Manager::OPTION] = ['template_id' => 88, 'divi_template_id' => 99];
$saved = $admin->sanitize_settings(['renderer' => 'standalone']);
if (!isset($choices['elementor'])) { check($saved['template_id'] === 88, 'Hidden Elementor selection preserved'); }
if (!isset($choices['divi'])) { check($saved['divi_template_id'] === 99, 'Hidden Divi selection preserved'); }
$links = implode('', $updater->links(['existing']));
check(strpos($links, 'Einstellungen') !== false && strpos($links, 'Nach Updates suchen') === false, 'Plugin settings link replaces manual update link');
$caps = ['update_plugins' => true];
check($updater->links(['existing']) === ['existing'], 'Update-only permission does not expose settings link');
$caps = []; $bar = new TestBar(); $admin->admin_bar($bar);
check($bar->nodes === [] && html($admin, 'overview_page') === '', 'Unprivileged visitor gets no management UI');
echo "PASS: {$checks} admin checks ({$mode}).\n";
