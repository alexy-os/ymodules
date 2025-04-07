<?php
namespace YModules\Core;

class ModuleManager {
    private $modules_dir;
    private $allowed_extensions = ['zip'];
    private $denied_extensions = [
        'phtml', 'php', 'php3', 'php4', 'php5', 'php6', 'php7', 'phps', 'cgi', 'pl', 'asp', 
        'aspx', 'shtml', 'shtm', 'htaccess', 'htpasswd', 'ini', 'log', 'sh', 'js', 'html', 
        'htm', 'css', 'sql', 'spl', 'scgi', 'fcgi'
    ];

    public function __construct() {
        $this->modules_dir = YMODULES_MODULES_DIR;
        
        // Create modules directory if it doesn't exist
        if (!is_dir($this->modules_dir)) {
            wp_mkdir_p($this->modules_dir);
        }
    }

    public function install_module($file) {
        if (!extension_loaded('zip')) {
            return new \WP_Error('zip_extension', __('ZIP extension is not loaded', 'ymodules'));
        }

        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return new \WP_Error('invalid_file', __('Invalid file upload', 'ymodules'));
        }

        $file_info = pathinfo($file['name']);
        $extension = strtolower($file_info['extension']);

        // Validate file extension
        if (!in_array($extension, $this->allowed_extensions)) {
            return new \WP_Error('invalid_extension', __('Invalid file extension', 'ymodules'));
        }

        if (in_array($extension, $this->denied_extensions)) {
            return new \WP_Error('denied_extension', __('File type not allowed', 'ymodules'));
        }

        // Create temporary directory for extraction
        $temp_dir = $this->modules_dir . 'temp_' . uniqid() . '/';
        if (!wp_mkdir_p($temp_dir)) {
            return new \WP_Error('temp_dir_error', __('Failed to create temporary directory', 'ymodules'));
        }

        // Extract ZIP file
        $zip = new \ZipArchive();
        $zip_result = $zip->open($file['tmp_name']);
        
        if ($zip_result !== true) {
            $this->cleanup_temp_dir($temp_dir);
            $error_message = sprintf(
                __('Failed to open ZIP file. Error code: %d', 'ymodules'),
                $zip_result
            );
            return new \WP_Error('zip_error', $error_message);
        }

        // Try to extract
        if (!$zip->extractTo($temp_dir)) {
            $zip->close();
            $this->cleanup_temp_dir($temp_dir);
            return new \WP_Error('extract_error', __('Failed to extract ZIP file', 'ymodules'));
        }

        $zip->close();

        // Validate module structure
        $module_info = $this->validate_module_structure($temp_dir);
        if (is_wp_error($module_info)) {
            $this->cleanup_temp_dir($temp_dir);
            return $module_info;
        }

        // Move module to final location
        $module_dir = $this->modules_dir . $module_info['slug'] . '/';

        if (is_dir($module_dir)) {
            $this->cleanup_temp_dir($module_dir);
        }

        // Create the module directory
        if (!wp_mkdir_p($module_dir)) {
            $this->cleanup_temp_dir($temp_dir);
            return new \WP_Error('create_dir_error', __('Failed to create module directory', 'ymodules'));
        }

        // Copy files from temp to final location
        if (!$this->copy_directory($temp_dir, $module_dir)) {
            $this->cleanup_temp_dir($temp_dir);
            $this->cleanup_temp_dir($module_dir);
            return new \WP_Error('copy_error', __('Failed to copy module files', 'ymodules'));
        }

        // Clean up temp directory
        $this->cleanup_temp_dir($temp_dir);

        return $module_info;
    }

    private function copy_directory($source, $destination) {
        if (!is_dir($source)) {
            return false;
        }

        if (!is_dir($destination)) {
            if (!wp_mkdir_p($destination)) {
                return false;
            }
        }

        $dir = dir($source);
        while (($file = $dir->read()) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $source_path = $source . '/' . $file;
            $dest_path = $destination . '/' . $file;

            if (is_dir($source_path)) {
                if (!$this->copy_directory($source_path, $dest_path)) {
                    return false;
                }
            } else {
                if (!@copy($source_path, $dest_path)) {
                    return false;
                }
            }
        }

        $dir->close();
        return true;
    }

    private function validate_module_structure($dir) {
        // First, check if we have a single directory inside
        $items = scandir($dir);
        $items = array_diff($items, ['.', '..']);
        
        if (count($items) === 1 && is_dir($dir . '/' . reset($items))) {
            // We have a single directory, use it as the module root
            $dir = $dir . '/' . reset($items) . '/';
        }

        // Check for module.json in root
        $json_path = $dir . 'module.json';
        if (!file_exists($json_path)) {
            return new \WP_Error('missing_file', __('Missing required file: module.json', 'ymodules'));
        }

        // Check for module.php in src directory
        $module_php_path = $dir . 'src/module.php';
        if (!file_exists($module_php_path)) {
            return new \WP_Error('missing_file', __('Missing required file: src/module.php', 'ymodules'));
        }

        // Parse module.json
        $json_content = file_get_contents($json_path);
        $module_info = json_decode($json_content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error('invalid_json', __('Invalid module.json file', 'ymodules'));
        }

        // Validate required fields
        $required_fields = ['name', 'version', 'description', 'author'];
        foreach ($required_fields as $field) {
            if (!isset($module_info[$field])) {
                return new \WP_Error('missing_field', sprintf(__('Missing required field in module.json: %s', 'ymodules'), $field));
            }
        }

        // Add slug to module info
        $module_info['slug'] = sanitize_title($module_info['name']);

        return $module_info;
    }

    public function get_installed_modules() {
        $modules = [];
        $dirs = glob($this->modules_dir . '*', GLOB_ONLYDIR);

        foreach ($dirs as $dir) {
            // First check in root
            $module_file = $dir . '/module.json';
            
            // If not found in root, check for a single subdirectory
            if (!file_exists($module_file)) {
                $items = scandir($dir);
                $items = array_diff($items, ['.', '..']);
                
                if (count($items) === 1 && is_dir($dir . '/' . reset($items))) {
                    $subdir = reset($items);
                    $module_file = $dir . '/' . $subdir . '/module.json';
                }
            }

            if (file_exists($module_file)) {
                $module_info = json_decode(file_get_contents($module_file), true);
                if ($module_info) {
                    $module_info['slug'] = basename($dir);
                    $module_info['active'] = get_option('ymodules_' . $module_info['slug'] . '_active', false);
                    $modules[] = $module_info;
                }
            }
        }

        return $modules;
    }

    public function activate_module($slug) {
        // Validate slug
        if (!$this->module_exists($slug)) {
            return new \WP_Error('module_not_found', __('Module not found', 'ymodules'));
        }
        
        // Get module information
        $module_info = $this->get_module_info($slug);
        if (is_wp_error($module_info)) {
            return $module_info;
        }
        
        // Set module as active in options
        update_option('ymodules_' . $slug . '_active', true);
        
        // Load the module's main file
        $module_file = $this->find_module_file($slug);
        
        if (!$module_file) {
            return new \WP_Error('module_file_not_found', __('Module file not found', 'ymodules'));
        }
        
        // Include the module's main file
        try {
            include_once $module_file;
            
            // Determine namespace and class name
            $namespace = isset($module_info['namespace']) ? $module_info['namespace'] : 'YModules\\' . ucfirst($slug);
            $class = $namespace . '\\Module';
            
            // Initialize the module
            if (class_exists($class)) {
                // Initialize main functionality
                if (method_exists($class, 'init')) {
                    call_user_func([$class, 'init']);
                }
                
                // Initialize admin functionality
                if (is_admin() && method_exists($class, 'admin_init')) {
                    call_user_func([$class, 'admin_init']);
                }
                
                // Add hook for future admin init calls
                if (method_exists($class, 'admin_init')) {
                    add_action('admin_init', function() use ($class) {
                        if (class_exists($class)) {
                            call_user_func([$class, 'admin_init']);
                        }
                    });
                }
            } else {
                return new \WP_Error('module_class_not_found', __('Module class not found', 'ymodules'));
            }
            
            return true;
        } catch (\Exception $e) {
            return new \WP_Error('module_init_error', __('Error initializing module', 'ymodules'));
        }
    }
    
    public function deactivate_module($slug) {
        // Validate slug
        if (!$this->module_exists($slug)) {
            return new \WP_Error('module_not_found', __('Module not found', 'ymodules'));
        }
        
        // Set module as inactive in options
        update_option('ymodules_' . $slug . '_active', false);
        
        return true;
    }
    
    public function delete_module($slug) {
        // Validate slug
        if (!$this->module_exists($slug)) {
            return new \WP_Error('module_not_found', __('Module not found', 'ymodules'));
        }
        
        // First deactivate the module
        $this->deactivate_module($slug);
        
        // Delete the module directory
        $module_dir = $this->get_module_directory($slug);
        if (is_dir($module_dir)) {
            $this->cleanup_temp_dir($module_dir);
        }
        
        return true;
    }
    
    private function module_exists($slug) {
        $module_dir = $this->get_module_directory($slug);
        return is_dir($module_dir);
    }
    
    private function get_module_directory($slug) {
        return $this->modules_dir . $slug . '/';
    }
    
    private function get_module_info($slug) {
        $module_dir = $this->get_module_directory($slug);
        
        // First check in root
        $module_file = $module_dir . 'module.json';
        
        // If not found in root, check for a single subdirectory
        if (!file_exists($module_file)) {
            $items = scandir($module_dir);
            $items = array_diff($items, ['.', '..']);
            
            if (count($items) === 1 && is_dir($module_dir . reset($items))) {
                $subdir = reset($items);
                $module_file = $module_dir . $subdir . '/module.json';
            }
        }
        
        if (!file_exists($module_file)) {
            return new \WP_Error('module_info_not_found', __('Module information not found', 'ymodules'));
        }
        
        $module_info = json_decode(file_get_contents($module_file), true);
        if (!$module_info) {
            return new \WP_Error('invalid_module_info', __('Invalid module information', 'ymodules'));
        }
        
        $module_info['slug'] = $slug;
        return $module_info;
    }
    
    private function find_module_file($slug) {
        $module_dir = $this->get_module_directory($slug);
        
        // Check for src/module.php (lowercase only)
        $module_file = $module_dir . 'src/module.php';
        if (file_exists($module_file)) {
            return $module_file;
        }
        
        // Check for a single subdirectory
        $items = scandir($module_dir);
        $items = array_diff($items, ['.', '..']);
        
        if (count($items) === 1 && is_dir($module_dir . reset($items))) {
            $subdir = reset($items);
            $module_file = $module_dir . $subdir . '/src/module.php';
            if (file_exists($module_file)) {
                return $module_file;
            }
        }
        
        return false;
    }

    private function cleanup_temp_dir($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->cleanup_temp_dir($path);
            } else {
                if (file_exists($path)) {
                    @unlink($path);
                }
            }
        }

        @rmdir($dir);
    }
} 