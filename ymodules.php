<?php
/**
 * Plugin Name: YModules
 * Plugin URI: https://github.com/yourusername/ymodules
 * Description: A modern module management system for WordPress following the Y Modules Manifesto principles.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://your-website.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ymodules
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('YMODULES_VERSION', '1.0.0');
define('YMODULES_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('YMODULES_PLUGIN_URL', plugin_dir_url(__FILE__));
define('YMODULES_PLUGIN_FILE', __FILE__);
define('YMODULES_MODULES_DIR', YMODULES_PLUGIN_DIR . 'modules/');

// Autoloader for plugin classes
spl_autoload_register(function ($class) {
    $prefix = 'YModules\\';
    $base_dir = YMODULES_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Initialize the plugin
function ymodules_init() {
    // Create modules directory if it doesn't exist
    if (!file_exists(YMODULES_MODULES_DIR)) {
        wp_mkdir_p(YMODULES_MODULES_DIR);
    }

    // Initialize the plugin
    \YModules\Core\Plugin::get_instance();
}
add_action('plugins_loaded', 'ymodules_init');

// Activation hook
register_activation_hook(__FILE__, function() {
    // Create necessary directories
    if (!file_exists(YMODULES_MODULES_DIR)) {
        wp_mkdir_p(YMODULES_MODULES_DIR);
    }
    
    // Create .htaccess to protect modules directory
    $htaccess = YMODULES_MODULES_DIR . '.htaccess';
    if (!file_exists($htaccess)) {
        $content = "deny from all\n";
        file_put_contents($htaccess, $content);
    }
}); 