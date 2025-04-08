<?php
namespace YModules;

use YModules\Core\AdapterFactory;
use YModules\Core\PlatformAdapterInterface;

/**
 * YModules Main Class
 * 
 * Main entry point for the YModules framework
 * that manages the platform adapter and provides
 * a simple API for working with modules
 */
class YModules {
    /** @var YModules Singleton instance */
    private static $instance = null;
    
    /** @var PlatformAdapterInterface Platform adapter instance */
    private $adapter;
    
    /** @var string Version number */
    const VERSION = '1.0.0';
    
    /**
     * Private constructor to prevent direct instantiation
     * 
     * @param PlatformAdapterInterface $adapter Platform adapter
     */
    private function __construct(PlatformAdapterInterface $adapter) {
        $this->adapter = $adapter;
    }
    
    /**
     * Gets the singleton instance
     * 
     * @param array $options Initialization options
     * @return YModules Instance
     */
    public static function getInstance(array $options = []) {
        if (self::$instance === null) {
            // Create adapter using factory
            $adapter = AdapterFactory::create($options);
            
            // Create instance with adapter
            self::$instance = new self($adapter);
            
            // Initialize adapter
            $adapter->initialize();
            
            // Auto-activate welcome module if no modules are active
            self::$instance->ensureWelcomeModuleActive();
        }
        
        return self::$instance;
    }
    
    /**
     * Gets the platform adapter
     * 
     * @return PlatformAdapterInterface Platform adapter
     */
    public function getAdapter() {
        return $this->adapter;
    }
    
    /**
     * Activates a module
     * 
     * @param string $slug Module slug
     * @return bool|mixed Result of activation
     */
    public function activateModule($slug) {
        return $this->adapter->activateModule($slug);
    }
    
    /**
     * Deactivates a module
     * 
     * @param string $slug Module slug
     * @return bool|mixed Result of deactivation
     */
    public function deactivateModule($slug) {
        return $this->adapter->deactivateModule($slug);
    }
    
    /**
     * Installs a module from a file
     * 
     * @param array $file File data
     * @return bool|mixed Result of installation
     */
    public function installModule($file) {
        return $this->adapter->installModule($file);
    }
    
    /**
     * Deletes a module
     * 
     * @param string $slug Module slug
     * @return bool|mixed Result of deletion
     */
    public function deleteModule($slug) {
        return $this->adapter->deleteModule($slug);
    }
    
    /**
     * Gets a list of installed modules
     * 
     * @return array Modules list
     */
    public function getModules() {
        // Get module loader from adapter to get modules
        $moduleLoader = $this->adapter->getModuleLoader();
        
        if ($moduleLoader) {
            return $moduleLoader->getInstalledModules();
        }
        
        return [];
    }
    
    /**
     * Gets a list of active modules
     * 
     * @return array Active modules
     */
    public function getActiveModules() {
        return $this->adapter->getActiveModules();
    }
    
    /**
     * Logs a message
     * 
     * @param string $message Message to log
     * @param string $level Log level
     */
    public function log($message, $level = 'info') {
        $this->adapter->log($message, $level);
    }
    
    /**
     * Ensures that the welcome module is active
     * Activates it if it exists and no modules are active
     */
    private function ensureWelcomeModuleActive() {
        $active_modules = $this->getActiveModules();
        
        // If no modules are active and welcome module exists, activate it
        if (empty($active_modules)) {
            $moduleLoader = $this->adapter->getModuleLoader();
            
            if ($moduleLoader->moduleExists('welcome-module')) {
                $this->adapter->log('No active modules found. Attempting to activate welcome module.', 'info');
                $this->activateModule('welcome-module');
            }
        }
    }
} 