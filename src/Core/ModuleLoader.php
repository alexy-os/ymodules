<?php
namespace YModules\Core;

/**
 * Core Module Loader
 * 
 * Platform-independent module loading system
 * that handles module discovery, validation, 
 * and initialization
 */
class ModuleLoader {
    /** @var string Base path for modules */
    private $modules_path;
    
    /** @var array Active modules list */
    private $active_modules = [];
    
    /** @var array Cache of module information */
    private $modules_cache = [];
    
    /** @var string Path to the base directory */
    private $base_path;

    /** @var string Path to assets directory */
    private $assets_path;
    
    /** @var array Allowed file extensions for module archives */
    protected $allowed_extensions = ['zip'];
    
    /** @var array Explicitly denied file extensions for security */
    protected $denied_extensions = [
        'phtml', 'php', 'php3', 'php4', 'php5', 'php6', 'php7', 'phps', 'cgi', 'pl', 'asp', 
        'aspx', 'shtml', 'shtm', 'htaccess', 'htpasswd', 'ini', 'log', 'sh', 'js', 'html', 
        'htm', 'css', 'sql', 'spl', 'scgi', 'fcgi'
    ];
    
    /**
     * Constructor
     * 
     * @param string $base_path Base path for Y Modules installation
     * @param string $modules_path Path to modules directory
     * @param string $assets_path Path to assets directory
     * @param array $active_modules List of active modules
     */
    public function __construct($base_path, $modules_path, $assets_path, array $active_modules = []) {
        $this->base_path = rtrim($base_path, '/') . '/';
        $this->modules_path = rtrim($modules_path, '/') . '/';
        $this->assets_path = rtrim($assets_path, '/') . '/';
        $this->active_modules = $active_modules;
        
        // Ensure modules directory exists
        if (!is_dir($this->modules_path)) {
            $this->makeDir($this->modules_path);
        }
    }
    
    /**
     * Creates a directory if it doesn't exist
     * 
     * @param string $path Directory path to create
     * @return bool True on success, false on failure
     */
    protected function makeDir($path) {
        if (is_dir($path)) {
            return true;
        }
        
        // Use mkdir recursively with proper permissions
        return @mkdir($path, 0755, true);
    }
    
    /**
     * Gets the modules directory path
     * 
     * @return string Modules directory path
     */
    public function getModulesPath() {
        return $this->modules_path;
    }
    
    /**
     * Gets the base path
     * 
     * @return string Base path
     */
    public function getBasePath() {
        return $this->base_path;
    }
    
    /**
     * Gets the assets path
     * 
     * @return string Assets path
     */
    public function getAssetsPath() {
        return $this->assets_path;
    }
    
    /**
     * Gets list of active modules
     * 
     * @return array Active modules
     */
    public function getActiveModules() {
        return $this->active_modules;
    }
    
    /**
     * Sets the list of active modules
     * 
     * @param array $modules List of active module slugs
     */
    public function setActiveModules(array $modules) {
        $this->active_modules = $modules;
    }
    
    /**
     * Loads all active modules
     * 
     * @return array Results of module loading
     */
    public function loadActiveModules() {
        $results = [
            'success' => [],
            'errors' => []
        ];
        
        if (empty($this->active_modules)) {
            return $results;
        }
        
        // Get all installed modules
        $all_modules = $this->getInstalledModules();
        $installed_slugs = array_keys($all_modules);
        
        // Load each active module that is actually installed
        foreach ($this->active_modules as $slug) {
            // Skip if module is not installed
            if (!in_array($slug, $installed_slugs)) {
                $results['errors'][$slug] = 'Module not installed';
                continue;
            }
            
            try {
                // Find and include the module file
                $module_file = $this->findModuleFile($slug);
                
                if ($module_file && is_readable($module_file)) {
                    include_once $module_file;
                    
                    // Get module info to determine namespace
                    $module_info = $all_modules[$slug];
                    
                    // Determine class name based on module info
                    $namespace = isset($module_info['namespace']) ? $module_info['namespace'] : 'YModules\\' . $this->pascalCase($slug);
                    $class = $namespace . '\\Module';
                    
                    // Check if class exists
                    if (class_exists($class)) {
                        // Initialize module
                        if (method_exists($class, 'init')) {
                            $instance = call_user_func([$class, 'init']);
                            $results['success'][$slug] = [
                                'instance' => $instance,
                                'class' => $class
                            ];
                        } else {
                            $results['errors'][$slug] = 'Missing init method';
                        }
                    } else {
                        $results['errors'][$slug] = "Module class {$class} not found";
                    }
                } else {
                    $results['errors'][$slug] = 'Module file not found or not readable';
                }
            } catch (\Exception $e) {
                $results['errors'][$slug] = $e->getMessage();
            }
        }
        
        return $results;
    }
    
    /**
     * Converts a string to PascalCase
     * 
     * @param string $string String to convert
     * @return string PascalCase string
     */
    protected function pascalCase($string) {
        $string = str_replace(['-', '_'], ' ', $string);
        $string = ucwords($string);
        return str_replace(' ', '', $string);
    }
    
    /**
     * Gets a list of all installed modules
     * 
     * @return array Array of module information with slug as key
     */
    public function getInstalledModules() {
        // Return cache if available
        if (!empty($this->modules_cache)) {
            return $this->modules_cache;
        }
        
        $modules = [];
        
        // Get all directories in the modules folder, excluding special entries
        $module_dirs = array_filter(
            glob($this->modules_path . '*', GLOB_ONLYDIR),
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
            $info = $this->getModuleInfo($slug);
            
            if ($info !== false) {
                // Add status and slug info
                $info['active'] = in_array($slug, $this->active_modules);
                $info['slug'] = $slug;
                $modules[$slug] = $info;
            }
        }
        
        // Cache modules
        $this->modules_cache = $modules;
        
        return $modules;
    }
    
    /**
     * Gets module information from module.json
     * 
     * @param string $slug Module slug
     * @return array|false Module information or false if not found
     */
    public function getModuleInfo($slug) {
        $module_dir = $this->getModuleDirectory($slug);
        
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
            return false;
        }
        
        $json_content = file_get_contents($module_file);
        $module_info = json_decode($json_content, true);
        
        if (!$module_info) {
            return false;
        }
        
        $module_info['slug'] = $slug;
        return $module_info;
    }
    
    /**
     * Gets the directory path for a module
     * 
     * @param string $slug Module slug
     * @return string Module directory path
     */
    public function getModuleDirectory($slug) {
        return $this->modules_path . $slug . '/';
    }
    
    /**
     * Checks if a module exists
     * 
     * @param string $slug Module slug
     * @return bool True if module exists, false otherwise
     */
    public function moduleExists($slug) {
        return is_dir($this->getModuleDirectory($slug));
    }
    
    /**
     * Finds a module's main PHP file
     * 
     * @param string $slug Module slug
     * @return string|false Path to module file or false if not found
     */
    public function findModuleFile($slug) {
        $module_dir = $this->getModuleDirectory($slug);
        
        // Check common file locations following PSR conventions
        $possible_locations = [
            // Direct module file in src directory (most common)
            $module_dir . 'src/module.php',
            $module_dir . 'src/Module.php',
            
            // PSR-4 style with class name matching directory
            $module_dir . 'src/' . $this->pascalCase($slug) . '.php',
            
            // Root module file (less common)
            $module_dir . 'module.php',
            $module_dir . 'Module.php',
            
            // Legacy or alternative locations
            $module_dir . 'index.php',
            $module_dir . 'init.php'
        ];
        
        foreach ($possible_locations as $location) {
            if (file_exists($location) && is_readable($location)) {
                return $location;
            }
        }
        
        // If no specific file is found, check in src directory for any PHP file
        $php_files = glob($module_dir . 'src/*.php');
        if (!empty($php_files)) {
            return $php_files[0];
        }
        
        return false;
    }
    
    /**
     * Activates a module
     * 
     * @param string $slug Module slug
     * @return bool True on success, false on failure
     */
    public function activateModule($slug) {
        // Validate module existence
        if (!$this->moduleExists($slug)) {
            return false;
        }
        
        // Check if module is already active
        if (in_array($slug, $this->active_modules)) {
            return true; // Already active
        }
        
        // Make sure we can load the module
        if (!$this->canLoadModule($slug)) {
            return false;
        }
        
        // Add to active modules list
        $this->active_modules[] = $slug;
        
        return true;
    }
    
    /**
     * Checks if a module can be loaded
     * 
     * @param string $slug Module slug
     * @return bool True if module can be loaded, false otherwise
     */
    protected function canLoadModule($slug) {
        // Try to load the module file
        $module_file = $this->findModuleFile($slug);
        if (!$module_file) {
            return false;
        }
        
        // Test file properties
        if (!is_readable($module_file) || filesize($module_file) <= 0) {
            return false;
        }
        
        // Include the module file to test
        try {
            include_once $module_file;
            
            // Get module info
            $module_info = $this->getModuleInfo($slug);
            if ($module_info === false) {
                return false;
            }
            
            // Verify the module class exists
            $namespace = isset($module_info['namespace']) ? $module_info['namespace'] : 'YModules\\' . $this->pascalCase($slug);
            $class = $namespace . '\\Module';
            
            if (!class_exists($class)) {
                return false;
            }
            
            // Verify required methods exist
            if (!method_exists($class, 'init')) {
                return false;
            }
            
            return true;
            
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Deactivates a module
     * 
     * @param string $slug Module slug
     * @return bool True on success, false on failure
     */
    public function deactivateModule($slug) {
        // Validate module existence
        if (!$this->moduleExists($slug)) {
            return false;
        }
        
        // Check if module is active
        if (!in_array($slug, $this->active_modules)) {
            return true; // Already inactive
        }
        
        // Remove module from active list
        $this->active_modules = array_diff($this->active_modules, [$slug]);
        
        return true;
    }
    
    /**
     * Installs a module from a ZIP file
     * 
     * @param string $zip_file Path to ZIP file
     * @return array|false Module information on success, false on failure
     */
    public function installModuleFromZip($zip_file) {
        // Validate ZIP extension availability
        if (!extension_loaded('zip')) {
            return false;
        }
        
        // Check if file exists and is readable
        if (!file_exists($zip_file) || !is_readable($zip_file)) {
            return false;
        }
        
        // Check file extension
        $file_info = pathinfo($zip_file);
        $extension = strtolower($file_info['extension']);
        
        // Validate file extension
        if (!in_array($extension, $this->allowed_extensions)) {
            return false;
        }
        
        if (in_array($extension, $this->denied_extensions)) {
            return false;
        }
        
        // Create temporary directory for extraction
        $temp_dir = $this->modules_path . 'temp_' . uniqid() . '/';
        if (!$this->makeDir($temp_dir)) {
            return false;
        }
        
        // Extract ZIP file
        $zip = new \ZipArchive();
        $zip_result = $zip->open($zip_file);
        
        if ($zip_result !== true) {
            $this->cleanupDir($temp_dir);
            return false;
        }
        
        // Extract ZIP contents to temporary directory
        if (!$zip->extractTo($temp_dir)) {
            $zip->close();
            $this->cleanupDir($temp_dir);
            return false;
        }
        
        $zip->close();
        
        // Validate module structure
        $module_info = $this->validateModuleStructure($temp_dir);
        if ($module_info === false) {
            $this->cleanupDir($temp_dir);
            return false;
        }
        
        // Prepare final module location
        $module_dir = $this->modules_path . $module_info['slug'] . '/';
        
        // Remove existing module directory if it exists
        if (is_dir($module_dir)) {
            $this->cleanupDir($module_dir);
        }
        
        // Create the module directory
        if (!$this->makeDir($module_dir)) {
            $this->cleanupDir($temp_dir);
            return false;
        }
        
        // Copy files from temporary directory to final location
        if (!$this->copyDirectory($temp_dir, $module_dir)) {
            $this->cleanupDir($temp_dir);
            $this->cleanupDir($module_dir);
            return false;
        }
        
        // Clean up temporary directory
        $this->cleanupDir($temp_dir);
        
        // Clear the modules cache
        $this->modules_cache = [];
        
        return $module_info;
    }
    
    /**
     * Validates module structure and requirements
     * 
     * @param string $dir Directory to validate
     * @return array|false Module information on success, false on failure
     */
    protected function validateModuleStructure($dir) {
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
            return false;
        }
        
        // 2. module.php in src directory (lowercase only)
        $module_php_path = $dir . 'src/module.php';
        if (!file_exists($module_php_path)) {
            return false;
        }
        
        // Parse and validate module.json
        $json_content = file_get_contents($json_path);
        $module_info = json_decode($json_content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        
        // Validate required fields
        $required_fields = ['name', 'version', 'description', 'author'];
        foreach ($required_fields as $field) {
            if (!isset($module_info[$field])) {
                return false;
            }
        }
        
        // Add slug to module info
        $module_info['slug'] = sanitize_title_with_dashes($module_info['name']);
        
        return $module_info;
    }
    
    /**
     * Sanitizes a string for use as a slug
     * 
     * @param string $title String to sanitize
     * @return string Sanitized string
     */
    protected function sanitize_title_with_dashes($title) {
        $title = strip_tags($title);
        // Preserve escaped octets
        $title = preg_replace('|%([a-fA-F0-9][a-fA-F0-9])|', '---$1---', $title);
        // Remove percent signs that are not part of an octet
        $title = str_replace('%', '', $title);
        // Restore octets
        $title = preg_replace('|---([a-fA-F0-9][a-fA-F0-9])---|', '%$1', $title);
        
        $title = strtolower($title);
        $title = preg_replace('/[^a-z0-9_\-]/', '-', $title);
        $title = preg_replace('/-+/', '-', $title);
        $title = trim($title, '-');
        
        return $title;
    }
    
    /**
     * Recursively copies a directory
     * 
     * @param string $source Source directory path
     * @param string $destination Destination directory path
     * @return bool True on success, false on failure
     */
    protected function copyDirectory($source, $destination) {
        if (!is_dir($source)) {
            return false;
        }
        
        if (!is_dir($destination)) {
            if (!$this->makeDir($destination)) {
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
                if (!$this->copyDirectory($source_path, $dest_path)) {
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
     * Recursively cleans up a directory
     * 
     * @param string $dir Directory to clean
     * @return bool Success or failure
     */
    protected function cleanupDir($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->cleanupDir($path);
            } else {
                if (file_exists($path)) {
                    @unlink($path);
                }
            }
        }
        
        return @rmdir($dir);
    }
    
    /**
     * Deletes a module
     * 
     * @param string $slug Module slug
     * @return bool Success or failure
     */
    public function deleteModule($slug) {
        // Validate module existence
        if (!$this->moduleExists($slug)) {
            return false;
        }
        
        // First deactivate the module
        $this->deactivateModule($slug);
        
        // Delete the module directory
        $module_dir = $this->getModuleDirectory($slug);
        if (is_dir($module_dir)) {
            return $this->cleanupDir($module_dir);
        }
        
        return true;
    }
} 