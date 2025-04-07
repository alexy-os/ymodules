<?php
namespace YModules\Core;

/**
 * Main plugin controller class
 * 
 * Manages plugin initialization, module loading,
 * and provides AJAX interfaces for module management
 */
class Plugin {
    /** @var Plugin|null Singleton instance */
    private static $instance = null;
    
    /** @var ModuleManager Module management instance */
    private $module_manager;

    /**
     * Private constructor to prevent direct instantiation
     * Initializes dependencies and hooks
     */
    private function __construct() {
        $this->module_manager = new ModuleManager();
        $this->init_hooks();
        
        // Ensure welcome module is activated when plugin loads
        add_action('admin_init', [$this, 'ensure_welcome_module_active'], 5);
    }

    /**
     * Returns singleton instance
     * 
     * @return Plugin Plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initializes WordPress hooks
     */
    private function init_hooks() {
        register_activation_hook(YMODULES_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(YMODULES_PLUGIN_FILE, [$this, 'deactivate']);

        // Load active modules first, before init hook
        $this->load_active_modules();

        add_action('init', [$this, 'load_textdomain']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // Optimize performance by reducing unnecessary requests
        $this->optimize_micro_requests();
        
        $this->setup_ajax_handlers();
    }

    /**
     * Optimizes performance by reducing WordPress micro-requests
     * 
     * Follows Y Modules Manifesto principle of Minimal Requests
     */
    private function optimize_micro_requests() {
        // We're using direct PHP rendering instead of AJAX requests
        // This is intentionally left minimal to avoid interference with WordPress core
    }

    /**
     * Sets up AJAX handlers for various module operations
     */
    private function setup_ajax_handlers() {
        // Register AJAX handlers for module management
        add_action('wp_ajax_ymodules_upload_module', [$this, 'handle_module_upload']);
        add_action('wp_ajax_ymodules_get_modules', [$this, 'get_modules_list']);
        add_action('wp_ajax_ymodules_activate_module', [$this, 'handle_activate_module']);
        add_action('wp_ajax_ymodules_deactivate_module', [$this, 'handle_deactivate_module']);
        add_action('wp_ajax_ymodules_delete_module', [$this, 'handle_delete_module']);
    }

    /**
     * Adds admin menu item for the plugin
     */
    public function add_admin_menu() {
        add_menu_page(
            __('YModules', 'ymodules'),
            __('YModules', 'ymodules'),
            'manage_options',
            'ymodules',
            [$this, 'render_admin_page'],
            'dashicons-grid-view',
            30
        );
    }

    /**
     * Enqueues admin scripts and styles
     * 
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets($hook) {
        // Only load assets on our plugin's pages for performance
        if ('toplevel_page_ymodules' !== $hook) {
            return;
        }

        // Enqueue Tailwind from CDN
        /*wp_enqueue_script(
            'tailwind-cdn',
            'https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4',
            [],
            null,
            true
        );*/

        // Enqueue Tailwind CSS
        wp_enqueue_style(
            'tailwind-css',
            YMODULES_PLUGIN_URL . 'assets/css/tailwind.css',
            [],
            YMODULES_VERSION
        );

        // Enqueue admin scripts
        wp_enqueue_script(
            'ymodules-admin',
            YMODULES_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'], // ['jquery', 'tailwind-cdn'],
            YMODULES_VERSION,
            true
        );

        // Pass AJAX parameters securely
        $ajax_data = [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ymodules-nonce'),
        ];

        wp_localize_script('ymodules-admin', 'ymodulesAdmin', $ajax_data);
    }

    /**
     * Renders the admin page
     */
    public function render_admin_page() {
        // Get modules directly using PHP
        $modules = $this->module_manager->get_installed_modules();
        
        // Make modules data available to the template
        $ymodules_data = [
            'modules' => $modules,
            'count' => count($modules)
        ];
        
        // Include admin template with proper path validation
        $template_path = YMODULES_PLUGIN_DIR . 'templates/admin-page.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<div class="wrap"><p class="notice notice-error">';
            echo __('Error: Admin template not found.', 'ymodules');
            echo '</p></div>';
        }
    }

    /**
     * Handles module upload via AJAX
     */
    public function handle_module_upload() {
        try {
            // Verify nonce for security
            if (!check_ajax_referer('ymodules-nonce', 'nonce', false)) {
                wp_send_json_error([
                    'message' => __('Security check failed', 'ymodules'),
                    'code' => 'invalid_nonce'
                ]);
            }

            // Check user permissions
            if (!current_user_can('manage_options')) {
                wp_send_json_error([
                    'message' => __('Permission denied', 'ymodules'),
                    'code' => 'insufficient_permissions'
                ]);
            }

            // Validate file upload
            if (!isset($_FILES['module'])) {
                wp_send_json_error([
                    'message' => __('No file uploaded', 'ymodules'),
                    'code' => 'no_file'
                ]);
            }

            $file = $_FILES['module'];
            
            // Check for upload errors
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error_message = $this->get_upload_error_message($file['error']);
                wp_send_json_error([
                    'message' => $error_message,
                    'code' => 'upload_error_' . $file['error']
                ]);
            }

            // Validate file size (max 10MB)
            $max_size = 10 * 1024 * 1024; // 10MB in bytes
            if ($file['size'] > $max_size) {
                wp_send_json_error([
                    'message' => __('File size exceeds maximum limit of 10MB', 'ymodules'),
                    'code' => 'file_too_large'
                ]);
            }

            // Pass to module manager for installation
            $result = $this->module_manager->install_module($file);

            if (is_wp_error($result)) {
                wp_send_json_error([
                    'message' => $result->get_error_message(),
                    'code' => $result->get_error_code()
                ]);
            }

            wp_send_json_success([
                'message' => __('Module installed successfully', 'ymodules'),
                'module' => $result
            ]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => 'exception'
            ]);
        }
    }

    /**
     * Gets human-readable error message for upload errors
     * 
     * @param int $error_code PHP upload error code
     * @return string Human-readable error message
     */
    private function get_upload_error_message($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
                return __('The uploaded file exceeds the upload_max_filesize directive in php.ini', 'ymodules');
            case UPLOAD_ERR_FORM_SIZE:
                return __('The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form', 'ymodules');
            case UPLOAD_ERR_PARTIAL:
                return __('The uploaded file was only partially uploaded', 'ymodules');
            case UPLOAD_ERR_NO_FILE:
                return __('No file was uploaded', 'ymodules');
            case UPLOAD_ERR_NO_TMP_DIR:
                return __('Missing a temporary folder', 'ymodules');
            case UPLOAD_ERR_CANT_WRITE:
                return __('Failed to write file to disk', 'ymodules');
            case UPLOAD_ERR_EXTENSION:
                return __('A PHP extension stopped the file upload', 'ymodules');
            default:
                return __('Unknown upload error', 'ymodules');
        }
    }

    /**
     * Returns list of installed modules via AJAX
     */
    public function get_modules_list() {
        // Verify nonce for security
        if (!check_ajax_referer('ymodules-nonce', 'nonce', false)) {
            wp_send_json_error([
                'message' => __('Security check failed', 'ymodules'),
                'code' => 'invalid_nonce'
            ]);
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Permission denied', 'ymodules'),
                'code' => 'insufficient_permissions'
            ]);
        }

        $modules = $this->module_manager->get_installed_modules();
        wp_send_json_success($modules);
    }

    /**
     * Handles module activation via AJAX
     */
    public function handle_activate_module() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ymodules-nonce')) {
            wp_send_json_error([
                'message' => __('Security check failed', 'ymodules'),
                'code' => 'invalid_nonce'
            ]);
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Permission denied', 'ymodules'),
                'code' => 'insufficient_permissions'
            ]);
        }

        // Validate required parameters
        if (!isset($_POST['slug']) || empty($_POST['slug'])) {
            wp_send_json_error([
                'message' => __('Module slug is required', 'ymodules'),
                'code' => 'missing_slug'
            ]);
        }

        // Sanitize user input
        $slug = sanitize_text_field($_POST['slug']);
        
        // Activate the module
        $result = $this->module_manager->activate_module($slug);

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message(),
                'code' => $result->get_error_code()
            ]);
        }

        wp_send_json_success([
            'message' => __('Module activated successfully', 'ymodules')
        ]);
    }

    /**
     * Handles module deactivation via AJAX
     */
    public function handle_deactivate_module() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ymodules-nonce')) {
            wp_send_json_error([
                'message' => __('Security check failed', 'ymodules'),
                'code' => 'invalid_nonce'
            ]);
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Permission denied', 'ymodules'),
                'code' => 'insufficient_permissions'
            ]);
        }

        // Validate required parameters
        if (!isset($_POST['slug']) || empty($_POST['slug'])) {
            wp_send_json_error([
                'message' => __('Module slug is required', 'ymodules'),
                'code' => 'missing_slug'
            ]);
        }

        // Sanitize user input
        $slug = sanitize_text_field($_POST['slug']);
        
        // Deactivate the module
        $result = $this->module_manager->deactivate_module($slug);

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message(),
                'code' => $result->get_error_code()
            ]);
        }

        wp_send_json_success([
            'message' => __('Module deactivated successfully', 'ymodules')
        ]);
    }

    /**
     * Handles module deletion via AJAX
     */
    public function handle_delete_module() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ymodules-nonce')) {
            wp_send_json_error([
                'message' => __('Security check failed', 'ymodules'),
                'code' => 'invalid_nonce'
            ]);
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Permission denied', 'ymodules'),
                'code' => 'insufficient_permissions'
            ]);
        }

        // Validate required parameters
        if (!isset($_POST['slug']) || empty($_POST['slug'])) {
            wp_send_json_error([
                'message' => __('Module slug is required', 'ymodules'),
                'code' => 'missing_slug'
            ]);
        }

        // Sanitize user input
        $slug = sanitize_text_field($_POST['slug']);
        
        // Delete the module
        $result = $this->module_manager->delete_module($slug);

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message(),
                'code' => $result->get_error_code()
            ]);
        }

        wp_send_json_success([
            'message' => __('Module deleted successfully', 'ymodules')
        ]);
    }

    /**
     * Loads plugin text domain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain('ymodules', false, dirname(plugin_basename(YMODULES_PLUGIN_FILE)) . '/languages');
    }

    /**
     * Handles plugin activation
     */
    public function activate() {
        // Create necessary directories and set up initial state
    }

    /**
     * Handles plugin deactivation
     */
    public function deactivate() {
        // Clean up resources if needed
    }

    /**
     * Loads all active modules on plugin initialization
     */
    public function load_active_modules() {
        $active_modules = get_option('ymodules_active_modules', []);
        
        // Debug information
        error_log('YModules Debug: Active modules - ' . json_encode($active_modules));
        
        if (empty($active_modules)) {
            error_log('YModules Debug: No active modules found');
            
            // Try to activate welcome module automatically for testing
            if ($this->module_manager->module_exists('welcome-module')) {
                error_log('YModules Debug: Welcome module exists, attempting to activate it');
                $result = $this->module_manager->activate_module('welcome-module');
                if (is_wp_error($result)) {
                    error_log('YModules Debug: Failed to activate welcome module: ' . $result->get_error_message());
                } else {
                    error_log('YModules Debug: Welcome module activated successfully');
                    $active_modules = get_option('ymodules_active_modules', []);
                }
            } else {
                error_log('YModules Debug: Welcome module does not exist');
            }
            
            if (empty($active_modules)) {
                return;
            }
        }
        
        // Get information about all installed modules
        $all_modules = $this->module_manager->get_installed_modules();
        $installed_slugs = array_column($all_modules, 'slug');
        
        // Initialize each active module that is actually installed
        foreach ($active_modules as $slug) {
            // Skip if module is not installed
            if (!in_array($slug, $installed_slugs)) {
                continue;
            }
            
            try {
                // Find and include the module file
                $module_file = $this->module_manager->find_module_file($slug);
                
                if ($module_file && is_readable($module_file)) {
                    include_once $module_file;
                    
                    // Get module info to determine namespace
                    $module_info = $this->module_manager->get_module_info($slug);
                    
                    if (!is_wp_error($module_info)) {
                        // Determine class name based on module info
                        $namespace = isset($module_info['namespace']) ? $module_info['namespace'] : 'YModules\\' . ucfirst($slug);
                        $class = $namespace . '\\Module';
                        
                        // Check if class exists
                        if (class_exists($class)) {
                            // Initialize module
                            if (method_exists($class, 'init')) {
                                call_user_func([$class, 'init']);
                                
                                // Register admin_init hook if method exists
                                if (method_exists($class, 'admin_init')) {
                                    error_log('YModules Debug: Adding admin_init hook for class ' . $class);
                                    
                                    // Check if we're in admin
                                    if (is_admin()) {
                                        error_log('YModules Debug: We are in admin area, calling admin_init directly');
                                        // Call admin_init directly if we're already in admin
                                        call_user_func([$class, 'admin_init']);
                                    }
                                    
                                    // Still add the hook for future requests
                                    add_action('admin_init', function() use ($class) {
                                        call_user_func([$class, 'admin_init']);
                                    });
                                    
                                    // Check if admin_menu hook is still available
                                    if (!did_action('admin_menu') && has_action('admin_menu')) {
                                        error_log('YModules Debug: admin_menu hook is available');
                                    } else {
                                        error_log('YModules Debug: WARNING - admin_menu hook already fired or not available!');
                                    }
                                }
                                
                                // Log successful module loading
                                if (defined('WP_DEBUG') && WP_DEBUG) {
                                    error_log(sprintf('YModules: Successfully loaded module %s', $slug));
                                }
                            } else {
                                error_log(sprintf('YModules: Module %s missing init method', $slug));
                            }
                        } else {
                            error_log(sprintf('YModules: Class %s not found for module %s', $class, $slug));
                        }
                    } else {
                        error_log(sprintf('YModules: Error getting module info for %s: %s', 
                            $slug, $module_info->get_error_message()));
                    }
                } else {
                    error_log(sprintf('YModules: Module file not found or not readable for %s', $slug));
                }
            } catch (\Exception $e) {
                // Log any exceptions during module loading
                error_log(sprintf('YModules: Exception loading module %s: %s', $slug, $e->getMessage()));
            }
        }
    }

    /**
     * Imports modules from a directory
     * 
     * Scans the specified directory for module folders and imports them
     * This allows for quickly adding multiple modules without going through the ZIP upload process
     * 
     * @param string $source_dir Directory to scan for modules
     * @param bool $activate Whether to activate imported modules
     * @return array Results of the import process
     */
    public function import_modules_from_directory($source_dir, $activate = false) {
        $results = [
            'imported' => [],
            'skipped' => [],
            'errors' => []
        ];
        
        // Ensure the source directory exists and is readable
        if (!is_dir($source_dir) || !is_readable($source_dir)) {
            $results['errors'][] = sprintf(__('Source directory %s does not exist or is not readable', 'ymodules'), $source_dir);
            return $results;
        }
        
        // Scan the directory for potential modules
        $items = array_diff(scandir($source_dir), ['.', '..']);
        
        foreach ($items as $item) {
            $item_path = $source_dir . '/' . $item;
            
            // Skip non-directories
            if (!is_dir($item_path)) {
                continue;
            }
            
            // Check if this looks like a module
            $module_json = $item_path . '/module.json';
            
            if (!file_exists($module_json)) {
                $results['skipped'][] = [
                    'path' => $item_path,
                    'reason' => __('No module.json found', 'ymodules')
                ];
                continue;
            }
            
            // Parse module info
            $json_content = file_get_contents($module_json);
            $module_info = json_decode($json_content, true);
            
            if (!$module_info || json_last_error() !== JSON_ERROR_NONE) {
                $results['skipped'][] = [
                    'path' => $item_path,
                    'reason' => __('Invalid module.json file', 'ymodules')
                ];
                continue;
            }
            
            // Determine module slug
            $slug = isset($module_info['slug']) 
                ? sanitize_title($module_info['slug']) 
                : sanitize_title($module_info['name'] ?? $item);
            
            // Skip existing modules by default
            if (is_dir(YMODULES_MODULES_DIR . $slug) && !isset($results['imported'][$slug])) {
                $results['skipped'][] = [
                    'path' => $item_path,
                    'slug' => $slug,
                    'reason' => __('Module already exists', 'ymodules')
                ];
                continue;
            }
            
            // Copy module to the modules directory
            $dest_dir = YMODULES_MODULES_DIR . $slug . '/';
            
            // Create the destination directory
            if (!wp_mkdir_p($dest_dir)) {
                $results['errors'][] = [
                    'path' => $item_path,
                    'slug' => $slug,
                    'reason' => __('Failed to create module directory', 'ymodules')
                ];
                continue;
            }
            
            // Copy module files
            if (!$this->module_manager->copy_directory($item_path, $dest_dir)) {
                $results['errors'][] = [
                    'path' => $item_path,
                    'slug' => $slug,
                    'reason' => __('Failed to copy module files', 'ymodules')
                ];
                continue;
            }
            
            // Successfully imported
            $results['imported'][] = [
                'path' => $item_path,
                'slug' => $slug,
                'info' => $module_info
            ];
            
            // Activate the module if requested
            if ($activate) {
                $result = $this->module_manager->activate_module($slug);
                
                if (is_wp_error($result)) {
                    $results['errors'][] = [
                        'slug' => $slug,
                        'reason' => sprintf(
                            __('Module imported but activation failed: %s', 'ymodules'),
                            $result->get_error_message()
                        )
                    ];
                }
            }
        }
        
        return $results;
    }

    /**
     * Ensures that the welcome module is active
     * For diagnostic purposes
     */
    public function ensure_welcome_module_active() {
        error_log('YModules Debug: Checking welcome module status');
        
        $active_modules = get_option('ymodules_active_modules', []);
        
        if (!in_array('welcome-module', $active_modules)) {
            error_log('YModules Debug: Welcome module is not active, attempting to activate');
            
            if ($this->module_manager->module_exists('welcome-module')) {
                $result = $this->module_manager->activate_module('welcome-module');
                
                if (is_wp_error($result)) {
                    error_log('YModules Debug: Failed to activate welcome module: ' . $result->get_error_message());
                } else {
                    error_log('YModules Debug: Welcome module forcibly activated');
                    
                    // Force admin_menu to run again if we're after that point
                    if (did_action('admin_menu')) {
                        error_log('YModules Debug: admin_menu already ran, attempting to run setup_admin directly');
                        
                        // Try to load the module file and call setup_admin directly
                        $module_file = $this->module_manager->find_module_file('welcome-module');
                        if ($module_file && is_readable($module_file)) {
                            include_once $module_file;
                            $module_info = $this->module_manager->get_module_info('welcome-module');
                            
                            if (!is_wp_error($module_info)) {
                                $namespace = isset($module_info['namespace']) ? $module_info['namespace'] : 'YModules\\Welcome';
                                $class = $namespace . '\\Module';
                                
                                if (class_exists($class)) {
                                    $instance = call_user_func([$class, 'admin_init']);
                                    error_log('YModules Debug: Manual admin_init called for welcome module: ' . (is_object($instance) ? 'success' : 'failed'));
                                    
                                    // Force add_admin_menu to run
                                    if (method_exists($instance, 'add_admin_menu')) {
                                        $instance->add_admin_menu();
                                        error_log('YModules Debug: Forced add_admin_menu for welcome module');
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                error_log('YModules Debug: Welcome module does not exist');
            }
        } else {
            error_log('YModules Debug: Welcome module is already active');
        }
    }
} 