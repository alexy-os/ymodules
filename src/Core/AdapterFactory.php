<?php
namespace YModules\Core;

/**
 * Adapter Factory
 * 
 * Factory class that creates and returns the appropriate adapter
 * for the current platform or for a specified platform
 */
class AdapterFactory {
    /**
     * Creates an adapter for the current platform
     * 
     * @param array $options Platform-specific options
     * @return PlatformAdapterInterface Platform adapter
     * @throws \RuntimeException If no suitable adapter is found
     */
    public static function create(array $options = []) {
        // Detect platform
        $platform = self::detectPlatform();
        
        // Create adapter for detected platform
        return self::createForPlatform($platform, $options);
    }
    
    /**
     * Creates an adapter for a specified platform
     * 
     * @param string $platform Platform identifier
     * @param array $options Platform-specific options
     * @return PlatformAdapterInterface Platform adapter
     * @throws \RuntimeException If the platform is not supported
     */
    public static function createForPlatform($platform, array $options = []) {
        switch ($platform) {
            case 'wordpress':
                // WordPress adapter requires the main plugin file
                if (!isset($options['plugin_file'])) {
                    throw new \InvalidArgumentException('WordPress adapter requires plugin_file option');
                }
                
                // Include and instantiate the adapter
                require_once __DIR__ . '/../Adapters/WordPress/WordPressAdapter.php';
                return new \YModules\Adapters\WordPress\WordPressAdapter($options['plugin_file']);
                
            case 'laravel':
                // Placeholder for future Laravel adapter
                throw new \RuntimeException('Laravel adapter not implemented yet');
                
            case 'symfony':
                // Placeholder for future Symfony adapter
                throw new \RuntimeException('Symfony adapter not implemented yet');
                
            case 'generic':
                // Placeholder for future generic PHP adapter
                throw new \RuntimeException('Generic PHP adapter not implemented yet');
                
            default:
                throw new \RuntimeException("No adapter available for platform: {$platform}");
        }
    }
    
    /**
     * Detects the current platform
     * 
     * @return string Platform identifier
     */
    public static function detectPlatform() {
        // Check for WordPress
        if (defined('ABSPATH') && function_exists('add_action')) {
            return 'wordpress';
        }
        
        // Check for Laravel
        if (defined('LARAVEL_START') || class_exists('Illuminate\Foundation\Application')) {
            return 'laravel';
        }
        
        // Check for Symfony
        if (class_exists('Symfony\Component\HttpKernel\Kernel')) {
            return 'symfony';
        }
        
        // Default to generic PHP
        return 'generic';
    }
} 