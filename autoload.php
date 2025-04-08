<?php
/**
 * YModules Autoloader
 * 
 * Simple PSR-4 style autoloader for YModules
 */

if (!function_exists('ymodules_autoloader')) {
    /**
     * YModules Autoloader function
     */
    function ymodules_autoloader($class) {
        // Only handle YModules namespace
        $prefix = 'YModules\\';
        
        // Return if the class doesn't use YModules namespace
        if (strpos($class, $prefix) !== 0) {
            return;
        }
        
        // Get the relative class name
        $relative_class = substr($class, strlen($prefix));
        
        // Replace namespace separators with directory separators
        $file = __DIR__ . '/src/' . str_replace('\\', '/', $relative_class) . '.php';
        
        // Include the file if it exists
        if (file_exists($file)) {
            require $file;
        }
    }
    
    // Register the autoloader
    spl_autoload_register('ymodules_autoloader');
}

// Load core files
require_once __DIR__ . '/src/Core/PlatformAdapterInterface.php';
require_once __DIR__ . '/src/Core/ModuleLoader.php';
require_once __DIR__ . '/src/Core/AdapterFactory.php';
require_once __DIR__ . '/src/YModules.php'; 