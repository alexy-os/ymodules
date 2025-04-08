<?php
namespace YModules\Adapters\WordPress;

use YModules\Core\PlatformAdapterInterface;
use YModules\Core\ModuleLoader;

/**
 * WordPress Adapter
 * 
 * Integrates YModules with WordPress using the adapter pattern
 */
class WordPressAdapter implements PlatformAdapterInterface {
    /** @var ModuleLoader Core module loader instance */
    private $module_loader;
    
    /** @var string Plugin base file */
    private $plugin_file;
    
    /** @var string Plugin directory path */
    private $plugin_dir;
    
    /** @var string Plugin URL */
    private $plugin_url;
    
    /** @var string Option name for storing active modules */
    const ACTIVE_MODULES_OPTION = 'ymodules_active_modules';
    
    /**
     * Constructor
     * 
     * @param string $plugin_file Path to the main plugin file
     */
    public function __construct($plugin_file) {
        $this->plugin_file = $plugin_file;
        $this->plugin_dir = plugin_dir_path($plugin_file);
        $this->plugin_url = plugin_dir_url($plugin_file);
        
        // Initialize ModuleLoader with WordPress-specific paths
        $this->module_loader = new ModuleLoader(
            YMODULES_PLUGIN_DIR,
            YMODULES_MODULES_DIR,
            YMODULES_ASSETS_DIR,
            $this->getActiveModules()
        );
    }
    
    /**
     * {@inheritdoc}
     */
    public function initialize() {
        // Register activation/deactivation hooks
        register_activation_hook($this->plugin_file, [$this, 'activate']);
        register_deactivation_hook($this->plugin_file, [$this, 'deactivate']);
        
        // Register admin interface
        if (is_admin()) {
            $this->registerAdminInterface();
        }
        
        // Load active modules
        $this->loadModules();
        
        // Register text domain for translations
        add_action('init', [$this, 'loadTextDomain']);
        
        return true;
    }
    
    /**
     * Plugin activation handler
     */
    public function activate() {
        // Create necessary directories
        if (!is_dir($this->module_loader->getModulesPath())) {
            wp_mkdir_p($this->module_loader->getModulesPath());
        }
    }
    
    /**
     * Plugin deactivation handler
     */
    public function deactivate() {
        // Nothing to do on deactivation yet
    }
    
    /**
     * Loads text domain for translations
     */
    public function loadTextDomain() {
        load_plugin_textdomain(
            'ymodules', 
            false, 
            dirname(plugin_basename($this->plugin_file)) . '/languages'
        );
    }
    
    /**
     * Loads all active modules
     */
    private function loadModules() {
        $result = $this->module_loader->loadActiveModules();
        
        // Log errors if any
        if (!empty($result['errors'])) {
            foreach ($result['errors'] as $slug => $error) {
                $this->log("Error loading module {$slug}: {$error}", 'error');
            }
        }
        
        // Handle successful module loading
        if (!empty($result['success'])) {
            foreach ($result['success'] as $slug => $data) {
                $class = $data['class'];
                $instance = $data['instance'];
                
                // If we're in the admin area, handle admin-specific hooks
                if (is_admin()) {
                    // If module has admin_init method, call it directly and also register for future requests
                    if (method_exists($class, 'admin_init')) {
                        $this->log("Calling admin_init for module {$slug}", 'debug');
                        
                        // Call admin_init directly if we're already in admin
                        call_user_func([$class, 'admin_init']);
                        
                        // Still add the hook for future requests
                        add_action('admin_init', function() use ($class) {
                            call_user_func([$class, 'admin_init']);
                        });
                    }
                    
                    // Check if the module has an add_admin_menu method (for object instances)
                    if (is_object($instance) && method_exists($instance, 'add_admin_menu')) {
                        // If admin_menu already fired, call it directly
                        if (did_action('admin_menu')) {
                            $this->log("admin_menu already fired, calling add_admin_menu directly for {$slug}", 'debug');
                            $instance->add_admin_menu();
                        } else {
                            // Otherwise, add the hook
                            add_action('admin_menu', [$instance, 'add_admin_menu']);
                        }
                    }
                    
                    // Some modules might use a static admin_menu method
                    if (method_exists($class, 'admin_menu')) {
                        // If admin_menu already fired, call it directly
                        if (did_action('admin_menu')) {
                            $this->log("admin_menu already fired, calling admin_menu directly for {$slug}", 'debug');
                            call_user_func([$class, 'admin_menu']);
                        } else {
                            // Otherwise, add the hook
                            add_action('admin_menu', function() use ($class) {
                                call_user_func([$class, 'admin_menu']);
                            });
                        }
                    }
                }
                
                // Log successful module loading
                $this->log("Successfully loaded module {$slug}", 'info');
            }
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function getActiveModules() {
        return get_option(self::ACTIVE_MODULES_OPTION, []);
    }
    
    /**
     * {@inheritdoc}
     */
    public function saveActiveModules(array $modules) {
        return update_option(self::ACTIVE_MODULES_OPTION, $modules);
    }
    
    /**
     * {@inheritdoc}
     */
    public function registerAdminInterface() {
        // Add admin menu
        add_action('admin_menu', [$this, 'addAdminMenu']);
        
        // Register admin scripts and styles
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        
        // Register AJAX handlers
        $this->registerAjaxHandlers();
        
        return true;
    }
    
    /**
     * Adds admin menu item
     */
    public function addAdminMenu() {
        add_menu_page(
            __('YModules', 'ymodules'),
            __('YModules', 'ymodules'),
            'manage_options',
            'ymodules',
            [$this, 'renderAdminInterface'],
            'dashicons-grid-view',
            30
        );
    }
    
    /**
     * Enqueues admin assets
     * 
     * @param string $hook Current admin page
     */
    public function enqueueAdminAssets($hook) {
        // Only load assets on our plugin's pages
        if ('toplevel_page_ymodules' !== $hook) {
            return;
        }
        
        // Enqueue Tailwind CSS
        wp_enqueue_style(
            'ymodules-tailwind',
            $this->getAssetUrl('css/tailwind.css'),
            [],
            $this->getVersion()
        );
        
        // Enqueue admin scripts
        wp_enqueue_script(
            'ymodules-admin',
            $this->getAssetUrl('js/admin.js'),
            ['jquery'],
            $this->getVersion(),
            true
        );
        
        // Pass AJAX parameters securely
        wp_localize_script('ymodules-admin', 'ymodulesAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ymodules-nonce'),
        ]);
    }
    
    /**
     * Gets plugin version
     * 
     * @return string Plugin version
     */
    private function getVersion() {
        if (!function_exists('get_plugin_data')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        
        $plugin_data = get_plugin_data($this->plugin_file);
        return $plugin_data['Version'];
    }
    
    /**
     * Registers AJAX handlers for module operations
     */
    private function registerAjaxHandlers() {
        // Upload module
        add_action('wp_ajax_ymodules_upload_module', [$this, 'handleAjaxModuleUpload']);
        
        // Module operations
        add_action('wp_ajax_ymodules_activate_module', [$this, 'handleAjaxActivateModule']);
        add_action('wp_ajax_ymodules_deactivate_module', [$this, 'handleAjaxDeactivateModule']);
        add_action('wp_ajax_ymodules_delete_module', [$this, 'handleAjaxDeleteModule']);
        
        // Get modules list
        add_action('wp_ajax_ymodules_get_modules', [$this, 'handleAjaxGetModules']);
    }
    
    /**
     * AJAX handler for module upload
     */
    public function handleAjaxModuleUpload() {
        try {
            // Verify nonce
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
                $error_message = $this->getUploadErrorMessage($file['error']);
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
            
            // Handle the actual installation
            $result = $this->installModule($file);
            
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
    private function getUploadErrorMessage($error_code) {
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
     * AJAX handler for getting modules list
     */
    public function handleAjaxGetModules() {
        // Verify nonce
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
        
        $modules = $this->module_loader->getInstalledModules();
        wp_send_json_success(array_values($modules));
    }
    
    /**
     * AJAX handler for module activation
     */
    public function handleAjaxActivateModule() {
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
        
        // Validate slug
        if (!isset($_POST['slug']) || empty($_POST['slug'])) {
            wp_send_json_error([
                'message' => __('Module slug is required', 'ymodules'),
                'code' => 'missing_slug'
            ]);
        }
        
        $slug = sanitize_text_field($_POST['slug']);
        $result = $this->activateModule($slug);
        
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
     * AJAX handler for module deactivation
     */
    public function handleAjaxDeactivateModule() {
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
        
        // Validate slug
        if (!isset($_POST['slug']) || empty($_POST['slug'])) {
            wp_send_json_error([
                'message' => __('Module slug is required', 'ymodules'),
                'code' => 'missing_slug'
            ]);
        }
        
        $slug = sanitize_text_field($_POST['slug']);
        $result = $this->deactivateModule($slug);
        
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
     * AJAX handler for module deletion
     */
    public function handleAjaxDeleteModule() {
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
        
        // Validate slug
        if (!isset($_POST['slug']) || empty($_POST['slug'])) {
            wp_send_json_error([
                'message' => __('Module slug is required', 'ymodules'),
                'code' => 'missing_slug'
            ]);
        }
        
        $slug = sanitize_text_field($_POST['slug']);
        $result = $this->deleteModule($slug);
        
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
     * {@inheritdoc}
     */
    public function activateModule($slug) {
        // Check if module exists
        if (!$this->module_loader->moduleExists($slug)) {
            return new \WP_Error('module_not_found', __('Module not found', 'ymodules'));
        }
        
        // Activate the module in the core module loader
        if (!$this->module_loader->activateModule($slug)) {
            return new \WP_Error('activation_failed', __('Failed to activate module', 'ymodules'));
        }
        
        // Save the active modules list
        $this->saveActiveModules($this->module_loader->getActiveModules());
        
        return true;
    }
    
    /**
     * {@inheritdoc}
     */
    public function deactivateModule($slug) {
        // Check if module exists
        if (!$this->module_loader->moduleExists($slug)) {
            return new \WP_Error('module_not_found', __('Module not found', 'ymodules'));
        }
        
        // Deactivate the module in the core module loader
        if (!$this->module_loader->deactivateModule($slug)) {
            return new \WP_Error('deactivation_failed', __('Failed to deactivate module', 'ymodules'));
        }
        
        // Save the active modules list
        $this->saveActiveModules($this->module_loader->getActiveModules());
        
        return true;
    }
    
    /**
     * {@inheritdoc}
     */
    public function installModule($file) {
        // Move uploaded file to a temporary location
        $tmp_dir = $this->getUploadDir();
        $tmp_file = $tmp_dir . uniqid('ymodules_') . '.zip';
        
        if (!move_uploaded_file($file['tmp_name'], $tmp_file)) {
            return new \WP_Error('upload_failed', __('Failed to move uploaded file', 'ymodules'));
        }
        
        // Install the module from the ZIP file
        $module_info = $this->module_loader->installModuleFromZip($tmp_file);
        
        // Clean up the temporary file
        @unlink($tmp_file);
        
        if (!$module_info) {
            return new \WP_Error('install_failed', __('Failed to install module', 'ymodules'));
        }
        
        return $module_info;
    }
    
    /**
     * {@inheritdoc}
     */
    public function deleteModule($slug) {
        // Check if module exists
        if (!$this->module_loader->moduleExists($slug)) {
            return new \WP_Error('module_not_found', __('Module not found', 'ymodules'));
        }
        
        // Delete the module using the core module loader
        if (!$this->module_loader->deleteModule($slug)) {
            return new \WP_Error('delete_failed', __('Failed to delete module', 'ymodules'));
        }
        
        return true;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getBasePath() {
        return $this->plugin_dir;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getAssetUrl($relative_path) {
        // First check if the file exists in the new src/assets structure
        $src_path = YMODULES_SRC_ASSETS_DIR . $relative_path;
        
        if (file_exists($src_path)) {
            return YMODULES_SRC_ASSETS_URL . $relative_path;
        }
        
        // Fall back to the original assets directory
        return YMODULES_ASSETS_URL . $relative_path;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getUploadDir() {
        $upload_dir = wp_upload_dir();
        $ymodules_dir = $upload_dir['basedir'] . '/ymodules/';
        
        if (!is_dir($ymodules_dir)) {
            wp_mkdir_p($ymodules_dir);
        }
        
        return $ymodules_dir;
    }
    
    /**
     * {@inheritdoc}
     */
    public function log($message, $level = 'info') {
        // Always log during development phase
        error_log("YModules {$level}: {$message}");
    }
    
    /**
     * {@inheritdoc}
     */
    public function renderAdminInterface($context = []) {
        // Handle WordPress passing a string instead of an array
        if (!is_array($context)) {
            $context = [];
        }
        
        // Get modules data
        $modules = $this->module_loader->getInstalledModules();
        
        // Prepare data for template
        $ymodules_data = [
            'modules' => array_values($modules),
            'count' => count($modules)
        ];
        
        // Merge with provided context
        $ymodules_data = array_merge($ymodules_data, $context);
        
        // First check for template in the new src/templates directory
        $src_template_path = YMODULES_SRC_DIR . 'templates/admin-page.php';
        if (file_exists($src_template_path)) {
            include $src_template_path;
            return;
        }
        
        // Fall back to original templates directory
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
     * Gets the module loader instance
     * 
     * @return ModuleLoader Module loader instance
     */
    public function getModuleLoader() {
        return $this->module_loader;
    }
} 