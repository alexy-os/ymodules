<?php
namespace YModules\Welcome;

class Module {
    private static $instance = null;
    
    /**
     * Initialize the module
     */
    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        self::$instance->setup();
        return self::$instance;
    }
    
    /**
     * Initialize the admin-specific functionality
     */
    public static function admin_init() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        self::$instance->setup_admin();
        return self::$instance;
    }
    
    /**
     * Setup module functionality
     */
    private function setup() {
        // Nothing to set up for frontend
    }
    
    /**
     * Setup admin functionality
     */
    private function setup_admin() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Welcome Page', 'ymodules'),
            __('Welcome', 'ymodules'),
            'manage_options',
            'welcome-module',
            [$this, 'render_admin_page'],
            'dashicons-welcome-learn-more',
            30
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our plugin's page
        if ('toplevel_page_welcome-module' !== $hook) {
            return;
        }
        
        // Use Tailwind CDN
        wp_enqueue_script(
            'tailwind-cdn',
            'https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4',
            [],
            null,
            true
        );
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Welcome to YModules', 'ymodules'); ?></h1>
            
            <div class="bg-white p-6 mt-4 rounded-lg shadow-md">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">
                    <?php echo esc_html__('Getting Started', 'ymodules'); ?>
                </h2>
                
                <p class="mb-4 text-gray-700">
                    <?php echo esc_html__('This is a simple welcome module for demonstration purposes.', 'ymodules'); ?>
                </p>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
                    <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                        <h3 class="text-lg font-semibold text-blue-700 mb-2">
                            <?php echo esc_html__('Module Features', 'ymodules'); ?>
                        </h3>
                        <ul class="list-disc list-inside text-blue-600">
                            <li><?php echo esc_html__('Simple admin page', 'ymodules'); ?></li>
                            <li><?php echo esc_html__('Tailwind styling', 'ymodules'); ?></li>
                            <li><?php echo esc_html__('WordPress integration', 'ymodules'); ?></li>
                        </ul>
                    </div>
                    
                    <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                        <h3 class="text-lg font-semibold text-green-700 mb-2">
                            <?php echo esc_html__('Next Steps', 'ymodules'); ?>
                        </h3>
                        <ul class="list-disc list-inside text-green-600">
                            <li><?php echo esc_html__('Explore module structure', 'ymodules'); ?></li>
                            <li><?php echo esc_html__('Customize this page', 'ymodules'); ?></li>
                            <li><?php echo esc_html__('Add more functionality', 'ymodules'); ?></li>
                        </ul>
                    </div>
                </div>
                
                <div class="mt-8 p-4 bg-yellow-50 border border-yellow-200 rounded">
                    <h3 class="text-lg font-semibold text-yellow-700 mb-2">
                        <?php echo esc_html__('Documentation', 'ymodules'); ?>
                    </h3>
                    <p class="text-yellow-600">
                        <?php echo esc_html__('This module demonstrates a simple admin page with Tailwind styling. Feel free to modify it to suit your needs.', 'ymodules'); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }
} 