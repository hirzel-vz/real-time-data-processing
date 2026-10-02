<?php
/**
 * Plugin Name:       AI H5P Generator
 * Description:       Generate H5P content with the Mistral API directly from your WordPress admin.
 * Version:           0.1.0
 * Author:            thirzel
 * License:           GPL-2.0-or-later
 * Requires at least: 6.0
 * Requires PHP:      8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AIAH5P_VERSION', '0.1.0');
define('AIAH5P_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AIAH5P_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once AIAH5P_PLUGIN_DIR . 'includes/class-aiah5p-db.php';
require_once AIAH5P_PLUGIN_DIR . 'includes/class-aiah5p-settings.php';
require_once AIAH5P_PLUGIN_DIR . 'includes/class-aiah5p-mistral-client.php';
require_once AIAH5P_PLUGIN_DIR . 'includes/class-aiah5p-h5p-builder.php';
require_once AIAH5P_PLUGIN_DIR . 'includes/class-aiah5p-content-store.php';
require_once AIAH5P_PLUGIN_DIR . 'includes/class-aiah5p-generator.php';
require_once AIAH5P_PLUGIN_DIR . 'includes/class-aiah5p-shortcode.php';
require_once AIAH5P_PLUGIN_DIR . 'includes/class-aiah5p-library-manager.php';
require_once AIAH5P_PLUGIN_DIR . 'includes/class-aiah5p-admin.php';

register_activation_hook(__FILE__, ['AIAH5P_Settings', 'activate']);

add_action('init', function () {
    AIAH5P_DB::init();
    AIAH5P_Settings::init();
    AIAH5P_Mistral_Client::init();
    AIAH5P_H5P_Builder::init();
    AIAH5P_Content_Store::init();
    AIAH5P_Generator::init();
    AIAH5P_Shortcode::init();
    AIAH5P_Library_Manager::init();
    AIAH5P_Admin::init();
});
