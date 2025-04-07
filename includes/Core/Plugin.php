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
        
        $this->setup_ajax_handlers();
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
        wp_enqueue_script(
            'tailwind-cdn',
            'https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4',
            [],
            null,
            true
        );

        // Enqueue admin scripts
        wp_enqueue_script(
            'ymodules-admin',
            YMODULES_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'tailwind-cdn'],
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
        // Include admin template with proper path validation
        $template_path = YMODULES_PLUGIN_DIR . 'templates/admin-page.php';
        if (file_exists($template_path)) {
            include $template_path;
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
     * Loads and initializes active modules
     */
    public function load_active_modules() {
        $modules = $this->module_manager->get_installed_modules();
        
        foreach ($modules as $module) {
            if (isset($module['active']) && $module['active']) {
                $this->module_manager->activate_module($module['slug']);
            }
        }
    }
} 