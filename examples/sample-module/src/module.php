<?php
namespace YModules\Sample;

class Module {
    private static $instance = null;

    private function __construct() {
        // Private constructor to prevent direct instantiation
    }

    public static function init() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        
        // Initialize module functionality
        add_action('init', [self::$instance, 'setup']);
    }

    public static function admin_init() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        
        // Initialize admin-specific functionality
        add_action('admin_init', [self::$instance, 'setup_admin']);
    }

    public function setup() {
        // Register custom post types, taxonomies, etc.
        $this->register_post_types();
        
        // Register shortcodes
        $this->register_shortcodes();
        
        // Add other initialization code
        $this->setup_hooks();
    }

    public function setup_admin() {
        // Add admin-specific initialization code
        add_action('admin_menu', [$this, 'add_menu_pages']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    private function register_post_types() {
        register_post_type('sample_type', [
            'labels' => [
                'name' => __('Sample Items', 'ymodules'),
                'singular_name' => __('Sample Item', 'ymodules'),
            ],
            'public' => true,
            'has_archive' => true,
            'supports' => ['title', 'editor', 'thumbnail'],
            'menu_icon' => 'dashicons-admin-post',
            'show_in_rest' => true,
        ]);
    }

    private function register_shortcodes() {
        add_shortcode('sample_shortcode', [$this, 'render_shortcode']);
    }

    private function setup_hooks() {
        add_filter('the_content', [$this, 'filter_content']);
    }

    public function add_menu_pages() {
        add_submenu_page(
            'ymodules',
            __('Sample Module', 'ymodules'),
            __('Sample Module', 'ymodules'),
            'manage_options',
            'ymodules-sample',
            [$this, 'render_admin_page']
        );
    }

    public function enqueue_admin_assets($hook) {
        if ('ymodules_page_ymodules-sample' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'ymodules-sample-admin',
            plugin_dir_url(__FILE__) . '../assets/css/admin.css',
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'ymodules-sample-admin',
            plugin_dir_url(__FILE__) . '../assets/js/admin.js',
            ['jquery'],
            '1.0.0',
            true
        );
    }

    public function render_shortcode($atts, $content = null) {
        $atts = shortcode_atts([
            'title' => '',
            'type' => 'default'
        ], $atts);

        ob_start();
        include __DIR__ . '/../templates/shortcode.php';
        return ob_get_clean();
    }

    public function filter_content($content) {
        if (is_singular('sample_type')) {
            // Add custom content for sample post type
            $custom_content = $this->get_custom_content();
            $content = $custom_content . $content;
        }
        return $content;
    }

    public function render_admin_page() {
        include __DIR__ . '/../templates/admin.php';
    }

    private function get_custom_content() {
        ob_start();
        include __DIR__ . '/../templates/custom-content.php';
        return ob_get_clean();
    }
} 