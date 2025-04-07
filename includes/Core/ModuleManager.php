<?php
namespace YModules\Core;

/**
 * Module Manager
 * 
 * Handles module installation, activation, deactivation,
 * and management within the YModules system
 */
class ModuleManager {
    /** @var string Path to modules directory */
    private $modules_dir;
    
    /** @var array Allowed file extensions for module archives */
    private $allowed_extensions = ['zip'];
    
    /** @var array Explicitly denied file extensions for security */
    private $denied_extensions = [
        'phtml', 'php', 'php3', 'php4', 'php5', 'php6', 'php7', 'phps', 'cgi', 'pl', 'asp', 
        'aspx', 'shtml', 'shtm', 'htaccess', 'htpasswd', 'ini', 'log', 'sh', 'js', 'html', 
        'htm', 'css', 'sql', 'spl', 'scgi', 'fcgi'
    ];

    /**
     * Constructor
     * 
     * Initializes module manager and ensures module directory exists
     */
    public function __construct() {
        $this->modules_dir = YMODULES_MODULES_DIR;
        
        // Create modules directory if it doesn't exist
        if (!is_dir($this->modules_dir)) {
            wp_mkdir_p($this->modules_dir);
        }
    }

    /**
     * Installs a module from an uploaded file
     * 
     * @param array $file Uploaded file information from $_FILES
     * @return array|WP_Error Module information on success, WP_Error on failure
     */
    public function install_module($file) {
        // Validate ZIP extension availability
        if (!extension_loaded('zip')) {
            return new \WP_Error('zip_extension', __('ZIP extension is not loaded', 'ymodules'));
        }

        // Validate uploaded file
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return new \WP_Error('invalid_file', __('Invalid file upload', 'ymodules'));
        }

        // Check file extension
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

        // Extract ZIP contents to temporary directory
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

        // Prepare final module location
        $module_dir = $this->modules_dir . $module_info['slug'] . '/';

        // Remove existing module directory if it exists
        if (is_dir($module_dir)) {
            $this->cleanup_temp_dir($module_dir);
        }

        // Create the module directory
        if (!wp_mkdir_p($module_dir)) {
            $this->cleanup_temp_dir($temp_dir);
            return new \WP_Error('create_dir_error', __('Failed to create module directory', 'ymodules'));
        }

        // Copy files from temporary directory to final location
        if (!$this->copy_directory($temp_dir, $module_dir)) {
            $this->cleanup_temp_dir($temp_dir);
            $this->cleanup_temp_dir($module_dir);
            return new \WP_Error('copy_error', __('Failed to copy module files', 'ymodules'));
        }

        // Clean up temporary directory
        $this->cleanup_temp_dir($temp_dir);

        return $module_info;
    }

    /**
     * Recursively copies a directory
     * 
     * @param string $source Source directory path
     * @param string $destination Destination directory path
     * @return bool True on success, false on failure
     */
    public function copy_directory($source, $destination) {
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
            // Skip dots
            if ($file === '.' || $file === '..') {
                continue;
            }

            $source_path = $source . '/' . $file;
            $dest_path = $destination . '/' . $file;

            if (is_dir($source_path)) {
                // Recursively copy subdirectories
                if (!$this->copy_directory($source_path, $dest_path)) {
                    return false;
                }
            } else {
                // Copy files
                if (!@copy($source_path, $dest_path)) {
                    return false;
                }
            }
        }

        $dir->close();
        return true;
    }

    /**
     * Validates module structure and requirements
     * 
     * @param string $dir Directory to validate
     * @return array|WP_Error Module information on success, WP_Error on failure
     */
    private function validate_module_structure($dir) {
        // Handle single directory inside ZIP
        $items = scandir($dir);
        $items = array_diff($items, ['.', '..']);
        
        if (count($items) === 1 && is_dir($dir . '/' . reset($items))) {
            // Use the single directory as module root
            $dir = $dir . '/' . reset($items) . '/';
        }

        // Check for required files
        // 1. module.json in root
        $json_path = $dir . 'module.json';
        if (!file_exists($json_path)) {
            return new \WP_Error('missing_file', __('Missing required file: module.json', 'ymodules'));
        }

        // 2. module.php in src directory (lowercase only)
        $module_php_path = $dir . 'src/module.php';
        if (!file_exists($module_php_path)) {
            return new \WP_Error('missing_file', __('Missing required file: src/module.php', 'ymodules'));
        }

        // Parse and validate module.json
        $json_content = file_get_contents($json_path);
        $module_info = json_decode($json_content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error('invalid_json', __('Invalid module.json file', 'ymodules'));
        }

        // Validate required fields
        $required_fields = ['name', 'version', 'description', 'author'];
        foreach ($required_fields as $field) {
            if (!isset($module_info[$field])) {
                return new \WP_Error(
                    'missing_field', 
                    sprintf(__('Missing required field in module.json: %s', 'ymodules'), $field)
                );
            }
        }

        // Add slug to module info
        $module_info['slug'] = sanitize_title($module_info['name']);

        return $module_info;
    }

    /**
     * Gets list of installed modules using direct directory reading
     * 
     * @return array Array of module information
     */
    public function get_installed_modules() {
        $modules = [];
        
        // Get all directories in the modules folder, excluding special entries
        $module_dirs = array_filter(
            glob($this->modules_dir . '*', GLOB_ONLYDIR),
            function($dir) {
                $basename = basename($dir);
                // Skip temporary directories and hidden directories
                return strpos($basename, 'temp_') !== 0 && strpos($basename, '.') !== 0;
            }
        );

        if (empty($module_dirs)) {
            return $modules;
        }

        foreach ($module_dirs as $dir) {
            $slug = basename($dir);
            $info = $this->get_module_info($slug);
            
            if (!is_wp_error($info)) {
                // Add active status to module info
                $active_modules = get_option('ymodules_active_modules', []);
                $info['active'] = in_array($slug, $active_modules);
                $info['slug'] = $slug;
                $modules[] = $info;
            } else {
                // Log the error but continue processing other modules
                error_log(sprintf('YModules: Failed to load module info for %s: %s', 
                    $slug, $info->get_error_message()));
            }
        }

        return $modules;
    }

    /**
     * Activates a module by its slug
     * 
     * @param string $slug Module slug
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function activate_module($slug) {
        // Validate module existence
        if (!$this->module_exists($slug)) {
            return new \WP_Error('module_not_found', __('Module not found', 'ymodules'));
        }
        
        // Get module information
        $module_info = $this->get_module_info($slug);
        if (is_wp_error($module_info)) {
            return $module_info;
        }
        
        // Get active modules list
        $active_modules = get_option('ymodules_active_modules', []);
        
        // Check if module is already active
        if (in_array($slug, $active_modules)) {
            return true; // Already active
        }
        
        // Try to load the module file
        $module_file = $this->find_module_file($slug);
        if (!$module_file) {
            return new \WP_Error('module_file_not_found', __('Module file not found', 'ymodules'));
        }
        
        // Include the module file to verify it loads without errors
        try {
            // Check file properties before including
            if (!is_readable($module_file)) {
                return new \WP_Error('file_not_readable', __('Module file is not readable', 'ymodules'));
            }
            
            if (filesize($module_file) <= 0) {
                return new \WP_Error('empty_file', __('Module file is empty', 'ymodules'));
            }
            
            // Include the module file
            include_once $module_file;
            
            // Verify the module class exists
            $namespace = isset($module_info['namespace']) ? $module_info['namespace'] : 'YModules\\' . ucfirst($slug);
            $class = $namespace . '\\Module';
            
            error_log('YModules Debug: Trying to load module class: ' . $class);
            
            if (!class_exists($class)) {
                error_log('YModules Debug: Module class not found: ' . $class);
                // Dump all declared classes to see what's available
                error_log('YModules Debug: Declared classes: ' . implode(', ', get_declared_classes()));
                return new \WP_Error('class_not_found', sprintf(__('Module class %s not found', 'ymodules'), $class));
            }
            
            // Verify required methods exist
            if (!method_exists($class, 'init')) {
                error_log('YModules Debug: init method not found in class ' . $class);
                return new \WP_Error('method_not_found', sprintf(__('Required method %s::init() not found', 'ymodules'), $class));
            }
            
            // Add module to active list
            $active_modules[] = $slug;
            update_option('ymodules_active_modules', $active_modules);
            
            // Call init method to initialize the module
            error_log('YModules Debug: Calling init method on class ' . $class);
            $result = call_user_func([$class, 'init']);
            error_log('YModules Debug: Init method result: ' . (is_object($result) ? get_class($result) : gettype($result)));
            
            // If admin_init method exists, add a hook to call it on admin_init
            if (method_exists($class, 'admin_init')) {
                error_log('YModules Debug: Registering admin_init hook for class ' . $class);
                add_action('admin_init', function() use ($class) {
                    error_log('YModules Debug: Executing admin_init for class ' . $class);
                    call_user_func([$class, 'admin_init']);
                });
            }
            
            return true;
            
        } catch (\Exception $e) {
            // Log the exception and return error
            error_log('YModules: Exception activating module ' . $slug . ': ' . $e->getMessage());
            return new \WP_Error('activation_exception', $e->getMessage());
        }
    }
    
    /**
     * Deactivates a module
     * 
     * @param string $slug Module slug
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function deactivate_module($slug) {
        // Validate module existence
        if (!$this->module_exists($slug)) {
            return new \WP_Error('module_not_found', __('Module not found', 'ymodules'));
        }
        
        // Get active modules list
        $active_modules = get_option('ymodules_active_modules', []);
        
        // Check if module is active
        if (!in_array($slug, $active_modules)) {
            return true; // Already inactive
        }
        
        // Remove module from active list
        $active_modules = array_diff($active_modules, [$slug]);
        update_option('ymodules_active_modules', $active_modules);
        
        return true;
    }
    
    /**
     * Deletes a module
     * 
     * @param string $slug Module slug
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function delete_module($slug) {
        // Validate module existence
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
    
    /**
     * Checks if a module exists
     * 
     * @param string $slug Module slug
     * @return bool True if module exists, false otherwise
     */
    public function module_exists($slug) {
        return is_dir($this->get_module_directory($slug));
    }
    
    /**
     * Gets the directory path for a module
     * 
     * @param string $slug Module slug
     * @return string Module directory path
     */
    public function get_module_directory($slug) {
        return $this->modules_dir . $slug . '/';
    }
    
    /**
     * Gets module information from module.json file
     * 
     * @param string $slug Module slug
     * @return array|WP_Error Module information on success, WP_Error on failure
     */
    public function get_module_info($slug) {
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
        
        $json_content = file_get_contents($module_file);
        $module_info = json_decode($json_content, true);
        
        if (!$module_info) {
            return new \WP_Error('invalid_module_info', __('Invalid module information', 'ymodules'));
        }
        
        $module_info['slug'] = $slug;
        return $module_info;
    }
    
    /**
     * Finds the module's main PHP file with support for PSR-4 style autoloading structures
     * 
     * @param string $slug Module slug
     * @return string|false Path to module file or false if not found
     */
    public function find_module_file($slug) {
        $module_dir = $this->get_module_directory($slug);
        
        error_log('YModules Debug: Searching for module file in directory: ' . $module_dir);
        
        // Check common file locations following PSR conventions
        $possible_locations = [
            // Direct module file in src directory (most common)
            $module_dir . 'src/module.php',
            $module_dir . 'src/Module.php',
            
            // PSR-4 style with class name matching directory
            $module_dir . 'src/' . ucfirst($slug) . '.php',
            
            // Root module file (less common)
            $module_dir . 'module.php',
            $module_dir . 'Module.php',
            
            // Legacy or alternative locations
            $module_dir . 'index.php',
            $module_dir . 'init.php'
        ];
        
        foreach ($possible_locations as $location) {
            error_log('YModules Debug: Checking location: ' . $location . ' - ' . (file_exists($location) ? 'EXISTS' : 'NOT FOUND') . ' - ' . (is_readable($location) ? 'READABLE' : 'NOT READABLE'));
            
            if (file_exists($location) && is_readable($location)) {
                error_log('YModules Debug: Found module file at: ' . $location);
                return $location;
            }
        }
        
        // If no specific file is found, check in src directory for any PHP file
        $php_files = glob($module_dir . 'src/*.php');
        if (!empty($php_files)) {
            error_log('YModules Debug: Found alternative PHP file at: ' . $php_files[0]);
            return $php_files[0];
        }
        
        error_log('YModules Debug: No module file found for ' . $slug);
        return false;
    }

    /**
     * Recursively cleans up a directory
     * 
     * @param string $dir Directory to clean
     */
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

    /**
     * Lists all files in a directory recursively for debugging purposes
     * 
     * @param string $dir Directory to list
     * @return array Array of file paths
     */
    private function list_directory_contents($dir) {
        $results = [];
        $files = scandir($dir);
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $path = $dir . '/' . $file;
            
            if (is_dir($path)) {
                $results[] = $path . '/ (directory)';
                $results = array_merge($results, $this->list_directory_contents($path));
            } else {
                $results[] = $path . ' (' . filesize($path) . ' bytes)';
            }
        }
        
        return $results;
    }
} 