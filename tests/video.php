<?php
// Exercise the real WordPress shortcode renderer and HTML sanitizer without a database.
// Usage: php tests/video.php /path/to/wordpress
$root = $argv[1] ?? '/wordpress';
define('ABSPATH', rtrim($root, '/') . '/');
define('WPINC', 'wp-includes');
require ABSPATH . WPINC . '/plugin.php';
require ABSPATH . WPINC . '/functions.php';
require ABSPATH . WPINC . '/formatting.php';
require ABSPATH . WPINC . '/kses.php';
require ABSPATH . WPINC . '/shortcodes.php';
require ABSPATH . WPINC . '/media.php';
function get_post($id = null) { return null; }
function wp_installing() { return false; }
function is_admin() { return false; }
add_filter('pre_option_blog_charset', function () { return 'UTF-8'; });
require dirname(__DIR__) . '/includes/class-notice-manager.php';
$manager = new ZZZNM_Manager();
$post = (object) ['ID'=>42, 'post_type'=>ZZZNM_Manager::POPUP, 'post_content'=>'[video mp4="https://example.org/movie.mp4" autoplay="true"][/video]'];
$html = $manager->body($post);
$checks = 0;
function check($condition, $message) { ++$GLOBALS['checks']; if (!$condition) throw new RuntimeException($message); }
check(strpos($html, '<video ') !== false && strpos($html, '<source ') !== false, 'Video and source survive sanitizing');
check(strpos($html, 'controls=') !== false, 'Native controls');
check(strpos($html, 'autoplay=') === false && strpos($html, 'preload="none"') !== false, 'No background playback/preloading');
check(strpos($html, '[video ') === false, 'Shortcode rendered');
check($html === $manager->body($post), 'Stable output across refreshes');
check(apply_filters('wp_video_shortcode_library', 'mediaelement') === 'mediaelement', 'Library filter restored');
$post->post_content = '[[video mp4="https://example.org/movie.mp4"]]';
check(strpos($manager->body($post), '<video ') === false, 'Escaped shortcode stays literal');
$post->post_content = '[video mp4="https://example.org/movie.mp4"][/video] [other] <script>alert(1)</script>';
$html = $manager->body($post);
check(strpos($html, '[other]') !== false && strpos($html, '<script') === false, 'Other shortcodes not executed, unsafe HTML removed');
$post->post_content = '[video src="https://example.org/movie.mp4"][/video] [video webm="https://example.org/movie.webm"][/video]';
$html = $manager->body($post);
check(substr_count($html, '<video ') === 2 && strpos($html, 'zzznm-video-42-2') !== false, 'Multiple videos have unique IDs');
check($html === $manager->body($post), 'Multiple video output remains stable');
$post->post_content = '[video mp4="javascript:alert(1).mp4"][/video]';
check(strpos($manager->body($post), 'src="javascript:') === false, 'Unsafe media protocol removed');
$post->post_type = ZZZNM_Manager::TICKER;
check(strpos($manager->body($post), '<video ') === false, 'Ticker behavior unchanged');
echo "PASS: {$checks} real WordPress video checks.\n";
