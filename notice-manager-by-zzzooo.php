<?php
/**
 * Plugin Name: WP Notice Manager by ZZZOOO
 * Description: Planbare Popups und Ticker. Eigenständige Popup-Ausgabe oder ein gemeinsames Elementor-Pro-Template.
 * Version: 1.0.3
 * Plugin URI: https://github.com/SmoothMC/wp-notice-manager
 * Update URI: https://github.com/SmoothMC/wp-notice-manager
 * Author: Mikka | ZZZOOO Studio
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: notice-manager-by-zzzooo
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) { exit; }
define('ZZZNM_FILE', __FILE__);
define('ZZZNM_VERSION', '1.0.3');
require_once __DIR__ . '/includes/class-updater.php';
new ZZZNM_Updater();
require_once __DIR__ . '/includes/class-notice-manager.php';
require_once __DIR__ . '/includes/class-admin.php';
require_once __DIR__ . '/includes/class-frontend.php';

// The legacy plugin owns the same shortcode names. Avoid two popup controllers.
add_action('plugins_loaded', static function () {
    if (class_exists('ZZZ_Praxis_Popup_Hinweis')) {
        add_action('admin_notices', static function () {
            echo '<div class="notice notice-warning"><p><strong>WP Notice Manager by ZZZOOO:</strong> Bitte zuerst „Praxis Popup Hinweis“ deaktivieren. Der neue Manager bleibt bis dahin inaktiv; die bisherigen Einstellungen bleiben gespeichert.</p></div>';
        });
        return;
    }
    $manager = new ZZZNM_Manager();
    new ZZZNM_Admin($manager);
    new ZZZNM_Frontend($manager);
});
