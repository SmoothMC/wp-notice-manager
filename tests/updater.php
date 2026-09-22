<?php
// Isolated updater contract tests. Run: php tests/updater.php
define('ABSPATH', __DIR__);
define('ZZZNM_FILE', '/plugins/notice-manager-by-zzzooo/notice-manager-by-zzzooo.php');
define('ZZZNM_VERSION', '1.0.3');
define('HOUR_IN_SECONDS', 3600);
define('MINUTE_IN_SECONDS', 60);
$cache = []; $requests = 0; $checks = 0; $hooks = [];
function plugin_basename($file) { return 'notice-manager-by-zzzooo/notice-manager-by-zzzooo.php'; }
function add_filter($hook, ...$args) { $GLOBALS['hooks'][] = $hook; }
function add_action($hook, ...$args) { $GLOBALS['hooks'][] = $hook; }
function get_site_transient($key) { return $GLOBALS['cache'][$key] ?? false; }
function set_site_transient($key, $value, $ttl) { $GLOBALS['cache'][$key] = $value; $GLOBALS['ttl'] = $ttl; }
function delete_site_transient($key) { unset($GLOBALS['cache'][$key]); }
function wp_safe_remote_get($url, $args) {
    $GLOBALS['requests']++; $GLOBALS['request'] = [$url, $args];
    return $GLOBALS['response'];
}
function is_wp_error($response) { return $response === 'error'; }
function wp_remote_retrieve_response_code($response) { return $response['code']; }
function wp_remote_retrieve_body($response) { return $response['body']; }
function sanitize_text_field($text) { return strip_tags($text); }
function wp_kses_post($text) { return strip_tags($text, '<p><pre>'); }
function check($condition, $message) {
    $GLOBALS['checks']++;
    if (!$condition) { throw new RuntimeException($message); }
}
require dirname(__DIR__) . '/includes/class-updater.php';
$updater = new ZZZNM_Updater();
$manifest = ['name' => 'WP Notice Manager by ZZZOOO', 'slug' => ZZZNM_Updater::SLUG, 'version' => '1.0.4',
    'download_url' => ZZZNM_Updater::REPOSITORY . '/releases/download/v1.0.4/wp-notice-manager-1.0.4.zip',
    'requires' => '6.0', 'requires_php' => '7.4', 'description' => '<p>Hinweise</p>', 'changelog' => '<p>Änderung</p>'];
$response = ['code' => 200, 'body' => json_encode($manifest)];
check($updater->validate_metadata($manifest)['version'] === '1.0.4', 'Valid release accepted');
check($updater->validate_metadata(null) === false, 'Missing metadata rejected');
foreach (['name', 'slug', 'version', 'download_url', 'requires', 'requires_php'] as $key) {
    $bad = $manifest; unset($bad[$key]);
    check($updater->validate_metadata($bad) === false, 'Required field: ' . $key);
}
foreach (['1.0.4-beta', '../1.0.4', "1.0.4\n"] as $version) {
    $bad = $manifest; $bad['version'] = $version;
    check($updater->validate_metadata($bad) === false, 'Invalid version rejected');
}
foreach (['https://example.com/plugin.zip', 'http://github.com/SmoothMC/wp-notice-manager/releases/download/v1.0.4/wp-notice-manager-1.0.4.zip',
    ZZZNM_Updater::REPOSITORY . '/releases/download/v1.0.3/wp-notice-manager-1.0.3.zip'] as $url) {
    $bad = $manifest; $bad['download_url'] = $url;
    check($updater->validate_metadata($bad) === false, 'Untrusted/mismatched package rejected');
}
$bad = $manifest; $bad['slug'] = 'other-plugin';
check($updater->validate_metadata($bad) === false, 'Wrong plugin rejected');
$unchanged = ['version' => '2.0.0'];
check($updater->update($unchanged, [], 'other/other.php', []) === $unchanged, 'Other plugins untouched');
check($requests === 0, 'No request for other plugin');
$update = $updater->update(false, ['Version' => '1.0.3'], plugin_basename(ZZZNM_FILE), []);
check($update['version'] === '1.0.4' && version_compare($update['version'], '1.0.3', '>'), 'New version offered');
check($update['package'] === $manifest['download_url'], 'Installable release URL supplied');
check(!isset($update['autoupdate']) && !in_array('auto_update_plugin', $hooks), 'Administrator controls auto-updates');
check($requests === 1 && $ttl === 21600, 'Successful metadata cached for 6 hours');
check($request[0] === ZZZNM_Updater::METADATA && $request[1]['timeout'] === 10, 'Fixed endpoint with timeout');
$updater->metadata(); check($requests === 1, 'Cache reused');
$info = $updater->information(false, 'plugin_information', (object) ['slug' => ZZZNM_Updater::SLUG]);
check($info->version === '1.0.4' && $info->download_link === $manifest['download_url'], 'Details dialog uses release');
check($updater->information('preserve', 'query_plugins', (object) []) === 'preserve', 'Unrelated API calls preserved');
$updater->metadata(true); check($requests === 2, 'Manual check bypasses cache');
foreach ([['code' => 404, 'body' => '{}'], ['code' => 200, 'body' => 'invalid json'], 'error'] as $failure) {
    $response = $failure;
    check($updater->metadata(true) === false, 'Transport/JSON failure handled');
    check($ttl === 300, 'Failure has short retry cache');
    $before = $requests; $updater->metadata(); check($requests === $before, 'Repeated failed requests avoided');
}
$response = ['code' => 200, 'body' => json_encode($manifest)];
$updater->metadata(true);
$updater->clear_after_upgrade(null, ['type' => 'plugin', 'action' => 'update', 'plugins' => ['other/other.php']]);
check(get_site_transient(ZZZNM_Updater::CACHE) !== false, 'Other upgrades preserve cache');
$updater->clear_after_upgrade(null, ['type' => 'plugin', 'action' => 'update', 'plugins' => [plugin_basename(ZZZNM_FILE)]]);
check(get_site_transient(ZZZNM_Updater::CACHE) === false, 'Own update clears cache');
$manifest['version'] = '1.0.3';
$manifest['download_url'] = ZZZNM_Updater::REPOSITORY . '/releases/download/v1.0.3/wp-notice-manager-1.0.3.zip';
$response = ['code' => 200, 'body' => json_encode($manifest)];
$update = $updater->update(false, ['Version' => '1.0.3'], plugin_basename(ZZZNM_FILE), []);
check(!version_compare($update['version'], '1.0.3', '>'), 'Same version becomes no_update in WordPress core');
echo "PASS: {$checks} updater checks (metadata, package URLs, versions, caching, failures, isolation, details and auto-update choice).\n";
