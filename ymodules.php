<?php
/**
 * YModules - Modular Extension System for WordPress
 *
 * A lightweight, performance-focused modular system that follows the Y Modules Manifesto principles
 * of Zero Redundancy, Minimal Requests, and Maximal Performance.
 *
 * @package YModules
 * @version 1.0.0
 *
 * Plugin Name: YModules
 * Plugin URI: https://example.com/ymodules
 * Description: Lightweight modular extension system for WordPress
 * Version: 1.0.0
 * Author: YModules Team
 * Author URI: https://example.com
 * Text Domain: ymodules
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

// If this file is called directly, abort
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('YMODULES_VERSION', '1.0.0');
define('YMODULES_PLUGIN_FILE', __FILE__);
define('YMODULES_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('YMODULES_PLUGIN_URL', plugin_dir_url(__FILE__));
define('YMODULES_MODULES_DIR', YMODULES_PLUGIN_DIR . 'modules/');

// Define new structure constants
define('YMODULES_SRC_DIR', YMODULES_PLUGIN_DIR . 'src/');
define('YMODULES_SRC_URL', YMODULES_PLUGIN_URL . 'src/');
define('YMODULES_ASSETS_DIR', YMODULES_PLUGIN_DIR . 'assets/');
define('YMODULES_ASSETS_URL', YMODULES_PLUGIN_URL . 'assets/');
define('YMODULES_SRC_ASSETS_DIR', YMODULES_SRC_DIR . 'assets/');
define('YMODULES_SRC_ASSETS_URL', YMODULES_SRC_URL . 'assets/');

// Include the autoloader
require_once YMODULES_PLUGIN_DIR . 'autoload.php';

// Initialize plugin
function ymodules_init() {
    // Create and initialize YModules with WordPress adapter
    return \YModules\YModules::getInstance([
        'plugin_file' => YMODULES_PLUGIN_FILE
    ]);
}

// Start the plugin
ymodules_init();