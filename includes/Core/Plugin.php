<?php
namespace YModules\Core;

class Plugin {
    private static $instance = null;
    private $admin;
    private $module_manager;

    private function __construct() {
        $this->init_hooks();
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function init_hooks() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_ymodules_upload_module', [$this, 'handle_module_upload']);
        add_action('wp_ajax_ymodules_get_modules', [$this, 'get_modules_list']);
        add_action('wp_ajax_ymodules_activate_module', [$this, 'handle_activate_module']);
        add_action('wp_ajax_ymodules_deactivate_module', [$this, 'handle_deactivate_module']);
        add_action('wp_ajax_ymodules_delete_module', [$this, 'handle_delete_module']);
    }

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

    public function enqueue_admin_assets($hook) {
        if ('toplevel_page_ymodules' !== $hook) {
            return;
        }

        error_log('YModules: Enqueuing admin assets for hook: ' . $hook);

        // Enqueue Tailwind and shadcn/ui from CDN
        wp_enqueue_script(
            'tailwind',
            'https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4',
            [],
            null,
            true
        );

        /*wp_enqueue_style(
            'shadcn',
            'https://cdn.jsdelivr.net/npm/@shadcn/ui@latest/dist/shadcn-ui.min.css',
            [],
            null
        );*/

        // Enqueue our custom styles and scripts
        /*wp_enqueue_style(
            'ymodules-admin',
            YMODULES_PLUGIN_URL . 'assets/css/admin.css',
            [],
            YMODULES_VERSION
        );*/

        wp_enqueue_script(
            'ymodules-admin',
            YMODULES_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'tailwind'],
            YMODULES_VERSION,
            true
        );

        $ajax_data = [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ymodules-nonce'),
        ];

        error_log('YModules: AJAX data: ' . print_r($ajax_data, true));

        wp_localize_script('ymodules-admin', 'ymodulesAdmin', $ajax_data);
    }

    public function render_admin_page() {
        include YMODULES_PLUGIN_DIR . 'templates/admin-page.php';
    }

    public function handle_module_upload() {
        try {
            error_log('YModules: Starting module upload');
            
            check_ajax_referer('ymodules-nonce', 'nonce');
            error_log('YModules: Nonce check passed');

            if (!current_user_can('manage_options')) {
                error_log('YModules: Permission denied');
                wp_send_json_error(['message' => __('Permission denied', 'ymodules')]);
            }

            if (!isset($_FILES['module'])) {
                error_log('YModules: No file uploaded');
                wp_send_json_error(['message' => __('No file uploaded', 'ymodules')]);
            }

            $file = $_FILES['module'];
            error_log('YModules: File info: ' . print_r($file, true));
            
            // Check for upload errors
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error_message = $this->get_upload_error_message($file['error']);
                error_log('YModules: Upload error: ' . $error_message);
                wp_send_json_error(['message' => $error_message]);
            }

            // Validate file size (max 10MB)
            $max_size = 10 * 1024 * 1024; // 10MB in bytes
            if ($file['size'] > $max_size) {
                error_log('YModules: File too large: ' . $file['size'] . ' bytes');
                wp_send_json_error(['message' => __('File size exceeds maximum limit of 10MB', 'ymodules')]);
            }

            error_log('YModules: Creating ModuleManager instance');
            $module_manager = new ModuleManager();
            
            error_log('YModules: Installing module');
            $result = $module_manager->install_module($file);

            if (is_wp_error($result)) {
                error_log('YModules: Installation error: ' . $result->get_error_message());
                wp_send_json_error([
                    'message' => $result->get_error_message(),
                    'code' => $result->get_error_code()
                ]);
            }

            error_log('YModules: Module installed successfully');
            wp_send_json_success([
                'message' => __('Module installed successfully', 'ymodules'),
                'module' => $result
            ]);
        } catch (\Exception $e) {
            error_log('YModules: Exception during upload: ' . $e->getMessage());
            error_log('YModules: Stack trace: ' . $e->getTraceAsString());
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => 'exception'
            ]);
        }
    }

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

    public function get_modules_list() {
        check_ajax_referer('ymodules-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'ymodules')]);
        }

        $module_manager = new ModuleManager();
        $modules = $module_manager->get_installed_modules();

        wp_send_json_success(['modules' => $modules]);
    }

    public function handle_get_modules() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ymodules_admin_nonce')) {
            wp_send_json_error(['message' => __('Security check failed', 'ymodules')]);
        }

        // Check user capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'ymodules')]);
        }

        $modules = $this->module_manager->get_installed_modules();
        wp_send_json_success($modules);
    }

    public function handle_activate_module() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ymodules_admin_nonce')) {
            wp_send_json_error(['message' => __('Security check failed', 'ymodules')]);
        }

        // Check user capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'ymodules')]);
        }

        // Check if module slug is provided
        if (!isset($_POST['slug']) || empty($_POST['slug'])) {
            wp_send_json_error(['message' => __('Module slug is required', 'ymodules')]);
        }

        $slug = sanitize_text_field($_POST['slug']);
        $result = $this->module_manager->activate_module($slug);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => __('Module activated successfully', 'ymodules')]);
    }

    public function handle_deactivate_module() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ymodules_admin_nonce')) {
            wp_send_json_error(['message' => __('Security check failed', 'ymodules')]);
        }

        // Check user capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'ymodules')]);
        }

        // Check if module slug is provided
        if (!isset($_POST['slug']) || empty($_POST['slug'])) {
            wp_send_json_error(['message' => __('Module slug is required', 'ymodules')]);
        }

        $slug = sanitize_text_field($_POST['slug']);
        $result = $this->module_manager->deactivate_module($slug);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => __('Module deactivated successfully', 'ymodules')]);
    }

    public function handle_delete_module() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ymodules_admin_nonce')) {
            wp_send_json_error(['message' => __('Security check failed', 'ymodules')]);
        }

        // Check user capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'ymodules')]);
        }

        // Check if module slug is provided
        if (!isset($_POST['slug']) || empty($_POST['slug'])) {
            wp_send_json_error(['message' => __('Module slug is required', 'ymodules')]);
        }

        $slug = sanitize_text_field($_POST['slug']);
        $result = $this->module_manager->delete_module($slug);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => __('Module deleted successfully', 'ymodules')]);
    }
} 