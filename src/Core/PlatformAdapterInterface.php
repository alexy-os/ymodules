<?php
namespace YModules\Core;

/**
 * Platform Adapter Interface
 * 
 * Interface for platform-specific adapters that integrate
 * YModules with various CMS, frameworks, and PHP systems
 */
interface PlatformAdapterInterface {
    /**
     * Registers and initializes the YModules system with the platform
     * 
     * @return bool Success or failure
     */
    public function initialize();
    
    /**
     * Gets the list of active modules from platform storage
     * 
     * @return array List of active module slugs
     */
    public function getActiveModules();
    
    /**
     * Saves the list of active modules to platform storage
     * 
     * @param array $modules List of active module slugs
     * @return bool Success or failure
     */
    public function saveActiveModules(array $modules);
    
    /**
     * Registers admin interface for module management
     * 
     * @return bool Success or failure
     */
    public function registerAdminInterface();
    
    /**
     * Renders the admin interface for module management
     * 
     * @param mixed $context Additional context variables
     */
    public function renderAdminInterface($context = []);
    
    /**
     * Handles module activation
     * 
     * @param string $slug Module slug
     * @return bool|mixed Success or failure, or platform-specific result
     */
    public function activateModule($slug);
    
    /**
     * Handles module deactivation
     * 
     * @param string $slug Module slug
     * @return bool|mixed Success or failure, or platform-specific result
     */
    public function deactivateModule($slug);
    
    /**
     * Handles module installation from uploaded file
     * 
     * @param array $file Uploaded file data
     * @return bool|mixed Success or failure, or platform-specific result
     */
    public function installModule($file);
    
    /**
     * Handles module deletion
     * 
     * @param string $slug Module slug
     * @return bool|mixed Success or failure, or platform-specific result
     */
    public function deleteModule($slug);
    
    /**
     * Gets the absolute path to the platform installation
     * 
     * @return string Absolute path
     */
    public function getBasePath();
    
    /**
     * Gets the platform's assets URL
     * 
     * @param string $relative_path Relative path to asset
     * @return string Full URL to asset
     */
    public function getAssetUrl($relative_path);
    
    /**
     * Gets the upload directory path for temporary file storage
     * 
     * @return string Upload directory path
     */
    public function getUploadDir();
    
    /**
     * Logs a message to the platform's logging system
     * 
     * @param string $message Message to log
     * @param string $level Log level
     * @return void
     */
    public function log($message, $level = 'info');
    
    /**
     * Gets the module loader instance
     * 
     * @return \YModules\Core\ModuleLoader Module loader instance
     */
    public function getModuleLoader();
} 