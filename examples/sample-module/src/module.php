<?php
namespace YModules\Sample;

class Module {
    private static $instance = null;

    private function __construct() {
        // Private constructor to prevent direct instantiation
    }

    public static function init() {
        error_log('YModules Sample: init() called');
        
        if (null === self::$instance) {
            self::$instance = new self();
            error_log('YModules Sample: instance created');
        }
        
        // Initialize module functionality
        add_action('init', [self::$instance, 'setup']);
        error_log('YModules Sample: added setup to init hook');
    }

    public static function admin_init() {
        error_log('YModules Sample: admin_init() called');
        
        if (null === self::$instance) {
            self::$instance = new self();
            error_log('YModules Sample: instance created in admin_init');
        }
        
        // Initialize admin-specific functionality
        add_action('admin_init', [self::$instance, 'setup_admin']);
        error_log('YModules Sample: added setup_admin to admin_init hook');
    }

    public function setup() {
        error_log('YModules Sample: setup() called');
        
        // Register custom post types, taxonomies, etc.
        $this->register_post_types();
        
        // Register shortcodes
        $this->register_shortcodes();
        
        // Add other initialization code
        $this->setup_hooks();
        
        // Register and enqueue frontend assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        
        error_log('YModules Sample: setup complete');
        
        // Экстренная проверка: зарегистрирован ли тип записи
        $post_types = get_post_types();
        error_log('YModules Sample: Available post types: ' . implode(', ', $post_types));
        
        // Экстренная проверка: работает ли шорткод
        add_action('wp_footer', function() {
            error_log('YModules Sample: Testing shortcode in footer');
            $output = do_shortcode('[sample_shortcode title="Test"]Shortcode content[/sample_shortcode]');
            error_log('YModules Sample: Shortcode output length: ' . strlen($output));
        });
    }

    public function setup_admin() {
        error_log('YModules Sample: setup_admin() called');
        
        // Add admin-specific initialization code
        add_action('admin_menu', [$this, 'add_menu_pages']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_init', [$this, 'register_settings']);
        
        // Add metaboxes for sample post type
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_sample_type', [$this, 'save_post_meta'], 10, 3);
        
        error_log('YModules Sample: setup_admin complete');
        
        // Экстренная проверка для страницы редактирования
        add_action('current_screen', function($screen) {
            error_log('YModules Sample: Current screen: ' . $screen->id);
            
            // Проверяем, на странице редактирования sample_type
            if ($screen->id === 'sample_type') {
                error_log('YModules Sample: On sample_type edit screen');
                
                // Добавляем тестовое содержимое
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success"><p>Sample Module is active and working on this page!</p></div>';
                });
            }
        });
    }

    public function register_settings() {
        // Register setting
        register_setting('ymodules_sample_options', 'ymodules_sample_option');
        register_setting('ymodules_sample_options', 'ymodules_sample_textarea');
        register_setting('ymodules_sample_options', 'ymodules_sample_select');
        
        // Add settings section
        add_settings_section(
            'ymodules_sample_section',
            __('Sample Module Settings', 'ymodules'),
            [$this, 'settings_section_callback'],
            'ymodules_sample_options'
        );
        
        // Add settings fields
        add_settings_field(
            'ymodules_sample_option',
            __('Sample Option', 'ymodules'),
            [$this, 'option_field_callback'],
            'ymodules_sample_options',
            'ymodules_sample_section'
        );
        
        add_settings_field(
            'ymodules_sample_textarea',
            __('Sample Textarea', 'ymodules'),
            [$this, 'textarea_field_callback'],
            'ymodules_sample_options',
            'ymodules_sample_section'
        );
        
        add_settings_field(
            'ymodules_sample_select',
            __('Sample Select', 'ymodules'),
            [$this, 'select_field_callback'],
            'ymodules_sample_options',
            'ymodules_sample_section'
        );
    }
    
    public function settings_section_callback() {
        echo '<p>' . __('Configure settings for the Sample Module.', 'ymodules') . '</p>';
    }
    
    public function option_field_callback() {
        $value = get_option('ymodules_sample_option', '');
        echo '<input type="text" name="ymodules_sample_option" value="' . esc_attr($value) . '" class="regular-text">';
        echo '<p class="description">' . __('This is a sample option field.', 'ymodules') . '</p>';
    }
    
    public function textarea_field_callback() {
        $value = get_option('ymodules_sample_textarea', '');
        echo '<textarea name="ymodules_sample_textarea" rows="5" cols="50" class="large-text">' . esc_textarea($value) . '</textarea>';
    }
    
    public function select_field_callback() {
        $value = get_option('ymodules_sample_select', 'option1');
        echo '<select name="ymodules_sample_select">';
        echo '<option value="option1" ' . selected($value, 'option1', false) . '>' . __('Option 1', 'ymodules') . '</option>';
        echo '<option value="option2" ' . selected($value, 'option2', false) . '>' . __('Option 2', 'ymodules') . '</option>';
        echo '<option value="option3" ' . selected($value, 'option3', false) . '>' . __('Option 3', 'ymodules') . '</option>';
        echo '</select>';
    }
    
    private function register_post_types() {
        error_log('YModules Sample: Registering post type sample_type');
        
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
        
        // Проверка, зарегистрирован ли тип записи
        $post_type_object = get_post_type_object('sample_type');
        error_log('YModules Sample: Post type registration result: ' . ($post_type_object ? 'Success' : 'Failed'));
    }

    private function register_shortcodes() {
        error_log('YModules Sample: Registering shortcode: sample_shortcode');
        
        add_shortcode('sample_shortcode', [$this, 'render_shortcode']);
        
        // Проверка, зарегистрирован ли шорткод
        global $shortcode_tags;
        error_log('YModules Sample: Shortcode registered: ' . (isset($shortcode_tags['sample_shortcode']) ? 'Yes' : 'No'));
    }

    private function setup_hooks() {
        add_filter('the_content', [$this, 'filter_content']);
        add_filter('the_title', [$this, 'filter_title'], 10, 2);
        add_action('widgets_init', [$this, 'register_widgets']);
        add_action('wp_footer', [$this, 'add_footer_content']);
        
        // Добавляем хук для отображения контента в сайдбаре
        add_action('get_sidebar', [$this, 'add_sidebar_content']);
        
        // Добавляем фильтр для шаблона single.php
        add_filter('single_template', [$this, 'filter_single_template']);
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
        // Only load assets on the sample module settings page 
        // or when editing sample_type post
        $is_sample_page = 'ymodules_page_ymodules-sample' === $hook;
        $is_sample_post = false;
        
        // Check if we're editing a sample_type post
        global $typenow;
        if ($typenow == 'sample_type') {
            $is_sample_post = true;
        }
        
        // If we're not on any relevant pages, don't load assets
        if (!$is_sample_page && !$is_sample_post) {
            return;
        }

        // Вместо загрузки файла, встраиваем стили прямо в страницу
        add_action('admin_head', [$this, 'output_admin_css']);
    }
    
    public function output_admin_css() {
        echo '<style type="text/css">
        /* Settings page styles */
        .ymodules-sample-admin .form-table th {
            padding: 20px 10px 20px 0;
        }
        
        .ymodules-sample-admin .form-table td {
            padding: 15px 10px;
        }
        
        /* Custom styles for the metaboxes */
        .ymodules-sample-metabox .inside {
            padding: 15px;
        }
        
        /* Sample type specific styles */
        .post-type-sample_type .postbox {
            margin-bottom: 20px;
        }
        </style>';
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

    public function filter_title($title, $post_id = null) {
        // If we're in the main query and this is a sample_type
        if (is_singular('sample_type') && in_the_loop() && is_main_query()) {
            // Get post meta
            $sample_meta = get_post_meta(get_the_ID(), '_sample_module_meta', true);
            $sample_text = isset($sample_meta['text']) ? $sample_meta['text'] : '';
            
            // Only modify if we have custom meta
            if (!empty($sample_text)) {
                // Add a subtitle with the custom meta
                $title .= '<div class="sample-module-subtitle">' . esc_html($sample_text) . '</div>';
            }
        }
        
        return $title;
    }

    public function filter_content($content) {
        if (is_singular('sample_type')) {
            // Add custom content for sample post type
            $custom_content = $this->get_custom_content();
            
            // Auto-insert the shortcode after the title/before content
            // Для более надежной работы, напрямую вызываем метод рендеринга шорткода
            ob_start();
            $atts = [
                'title' => 'Auto-generated shortcode',
                'type' => 'custom'
            ];
            $shortcode_content = "This shortcode was automatically inserted by the Sample Module.";
            include dirname(__FILE__) . '/../templates/shortcode.php';
            $shortcode_output = ob_get_clean();
            
            // Combine everything
            $content = $custom_content . $shortcode_output . $content;
            
            // Для диагностики
            error_log('Sample module content filter applied. Content length: ' . strlen($content));
        }
        return $content;
    }

    public function render_admin_page() {
        // Make sure we add nonce and proper settings form
        echo '<div class="wrap">';
        echo '<h1>' . __('Sample Module Settings', 'ymodules') . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('ymodules_sample_options');
        do_settings_sections('ymodules_sample_options');
        submit_button();
        echo '</form>';
        echo '</div>';
    }

    private function get_custom_content() {
        ob_start();
        include __DIR__ . '/../templates/custom-content.php';
        return ob_get_clean();
    }

    public function add_meta_boxes() {
        add_meta_box(
            'sample_module_meta',
            __('Sample Module Information', 'ymodules'),
            [$this, 'render_meta_box'],
            'sample_type',
            'normal',
            'default'
        );
    }
    
    public function render_meta_box($post) {
        // Add nonce for security
        wp_nonce_field('sample_module_meta_nonce', 'sample_module_meta_nonce');
        
        // Get current values
        $sample_meta = get_post_meta($post->ID, '_sample_module_meta', true);
        $sample_text = isset($sample_meta['text']) ? $sample_meta['text'] : '';
        $sample_select = isset($sample_meta['select']) ? $sample_meta['select'] : 'option1';
        
        // Output the form fields
        echo '<div class="ymodules-sample-metabox">';
        
        echo '<p>';
        echo '<label for="sample_module_text">' . __('Sample Text Field', 'ymodules') . '</label><br>';
        echo '<input type="text" id="sample_module_text" name="sample_module_meta[text]" value="' . esc_attr($sample_text) . '" class="widefat">';
        echo '</p>';
        
        echo '<p>';
        echo '<label for="sample_module_select">' . __('Sample Select Field', 'ymodules') . '</label><br>';
        echo '<select id="sample_module_select" name="sample_module_meta[select]" class="widefat">';
        echo '<option value="option1" ' . selected($sample_select, 'option1', false) . '>' . __('Option 1', 'ymodules') . '</option>';
        echo '<option value="option2" ' . selected($sample_select, 'option2', false) . '>' . __('Option 2', 'ymodules') . '</option>';
        echo '<option value="option3" ' . selected($sample_select, 'option3', false) . '>' . __('Option 3', 'ymodules') . '</option>';
        echo '</select>';
        echo '</p>';
        
        echo '</div>';
    }
    
    public function save_post_meta($post_id, $post, $update) {
        // Check if our nonce is set
        if (!isset($_POST['sample_module_meta_nonce'])) {
            return;
        }
        
        // Verify that the nonce is valid
        if (!wp_verify_nonce($_POST['sample_module_meta_nonce'], 'sample_module_meta_nonce')) {
            return;
        }
        
        // If this is an autosave, don't save
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check the user's permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Sanitize and save the data
        if (isset($_POST['sample_module_meta'])) {
            $sample_meta = [
                'text' => sanitize_text_field($_POST['sample_module_meta']['text']),
                'select' => sanitize_text_field($_POST['sample_module_meta']['select']),
            ];
            
            update_post_meta($post_id, '_sample_module_meta', $sample_meta);
        }
    }

    public function enqueue_frontend_assets() {
        // Only load on single sample_type posts or if shortcode is used
        global $post;
        $should_load = is_singular('sample_type');
        
        // Check for shortcode in content
        if (!$should_load && is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'sample_shortcode')) {
            $should_load = true;
        }
        
        if ($should_load) {
            // Вместо загрузки файла, встраиваем стили прямо в страницу
            add_action('wp_head', [$this, 'output_frontend_css']);
        }
    }
    
    public function output_frontend_css() {
        echo '<style type="text/css">
        /* Title and subtitle */
        .sample-module-subtitle {
            font-size: 1rem;
            color: #666;
            margin-top: 0.5rem;
            font-style: italic;
            display: block;
            clear: both;
        }
        
        /* Custom content section */
        .ymodules-sample-custom-content {
            margin-bottom: 2rem;
            border-radius: 0.375rem;
            overflow: hidden;
            background: #f9fafb;
            padding: 20px;
            border: 1px solid #e5e7eb;
        }
        
        /* Shortcode styles */
        .ymodules-sample-shortcode {
            margin: 1.5rem 0;
            border-radius: 0.375rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            background: white;
            padding: 20px;
            border: 1px solid #e5e7eb;
        }
        
        .ymodules-sample-shortcode h3 {
            font-size: 1.25rem;
            font-weight: 500;
            margin: 0 0 15px 0;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 10px;
        }
        
        /* Footer styles */
        .ymodules-sample-footer {
            margin-top: 3rem;
            padding: 1rem 0;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 0.875rem;
            color: #6b7280;
            clear: both;
        }
        
        .ymodules-sample-footer a {
            color: #4f46e5;
            text-decoration: none;
        }
        
        .ymodules-sample-footer a:hover {
            text-decoration: underline;
        }
        
        /* Sidebar styles */
        .ymodules-sample-sidebar {
            margin-bottom: 2rem;
            padding: 15px;
            background-color: #f9fafb;
            border-radius: 0.375rem;
            border: 1px solid #e5e7eb;
        }
        
        .ymodules-sample-sidebar-title {
            font-size: 1.25rem;
            margin-top: 0;
            margin-bottom: 15px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 10px;
        }
        
        /* Sidebar widget styles */
        .ymodules-sample-widget {
            margin-bottom: 1.5rem;
            padding: 15px;
            background-color: white;
            border-radius: 0.375rem;
            border: 1px solid #e5e7eb;
        }
        
        .ymodules-sample-widget h4 {
            font-size: 1.125rem;
            font-weight: 500;
            margin-top: 0;
            margin-bottom: 10px;
            color: #111827;
        }
        
        /* Integrate sidebar into content for themes without sidebar */
        .ymodules-sample-content-sidebar {
            margin-top: 30px;
            clear: both;
        }
        </style>';
    }

    public function register_widgets() {
        // Register a sidebar for sample_type
        register_sidebar([
            'name'          => __('Sample Module Sidebar', 'ymodules'),
            'id'            => 'ymodules-sample-sidebar',
            'description'   => __('Sidebar for Sample Module content', 'ymodules'),
            'before_widget' => '<div class="ymodules-sample-widget">',
            'after_widget'  => '</div>',
            'before_title'  => '<h3 class="ymodules-sample-widget-title">',
            'after_title'   => '</h3>',
        ]);
    }
    
    public function add_footer_content() {
        // Only add footer content on single sample_type pages
        if (!is_singular('sample_type')) {
            return;
        }
        
        // Get the current post data
        $post_id = get_the_ID();
        $post_title = get_the_title();
        $post_url = get_permalink();
        
        // Output footer content
        echo '<div class="ymodules-sample-footer">';
        echo '<div class="ymodules-sample-footer-content">';
        echo '<p>' . __('You are viewing a Sample Item: ', 'ymodules') . '<a href="' . esc_url($post_url) . '">' . esc_html($post_title) . '</a></p>';
        echo '</div>';
        echo '</div>';
    }

    public function filter_single_template($template) {
        // Проверяем, что мы на странице sample_type
        if (is_singular('sample_type')) {
            error_log('Sample module filtering template: ' . $template);
            
            // Выводим содержимое сайдбара прямо внутри контента
            add_action('the_content', [$this, 'append_sidebar_to_content'], 20);
        }
        
        return $template;
    }
    
    public function append_sidebar_to_content($content) {
        // Только один раз добавляем сайдбар
        static $sidebar_added = false;
        
        if ($sidebar_added) {
            return $content;
        }
        
        $sidebar_added = true;
        
        // Добавляем содержимое сайдбара
        $sidebar_content = $this->get_sidebar_content();
        
        // Добавляем сайдбар в конец содержимого
        $content .= '<div class="ymodules-sample-content-sidebar">' . $sidebar_content . '</div>';
        
        return $content;
    }
    
    public function add_sidebar_content() {
        // Выводим содержимое сайдбара только на странице sample_type
        if (is_singular('sample_type')) {
            echo $this->get_sidebar_content();
        }
    }
    
    private function get_sidebar_content() {
        // Получаем контент для сайдбара
        ob_start();
        
        echo '<div class="ymodules-sample-sidebar">';
        echo '<h3 class="ymodules-sample-sidebar-title">' . __('Sample Module Sidebar', 'ymodules') . '</h3>';
        
        // Выводим некоторые полезные данные
        echo '<div class="ymodules-sample-widget">';
        echo '<h4>' . __('Post Information', 'ymodules') . '</h4>';
        echo '<p>' . __('Post ID: ', 'ymodules') . get_the_ID() . '</p>';
        echo '<p>' . __('Author: ', 'ymodules') . get_the_author() . '</p>';
        echo '<p>' . __('Date: ', 'ymodules') . get_the_date() . '</p>';
        echo '</div>';
        
        // Выводим настройки модуля
        $option = get_option('ymodules_sample_option', '');
        if (!empty($option)) {
            echo '<div class="ymodules-sample-widget">';
            echo '<h4>' . __('Module Settings', 'ymodules') . '</h4>';
            echo '<p>' . __('Option: ', 'ymodules') . esc_html($option) . '</p>';
            echo '</div>';
        }
        
        // Выводим метаданные записи
        $sample_meta = get_post_meta(get_the_ID(), '_sample_module_meta', true);
        if (!empty($sample_meta)) {
            echo '<div class="ymodules-sample-widget">';
            echo '<h4>' . __('Post Meta Data', 'ymodules') . '</h4>';
            
            if (!empty($sample_meta['text'])) {
                echo '<p>' . __('Text: ', 'ymodules') . esc_html($sample_meta['text']) . '</p>';
            }
            
            if (!empty($sample_meta['select'])) {
                echo '<p>' . __('Select: ', 'ymodules') . esc_html($sample_meta['select']) . '</p>';
            }
            
            echo '</div>';
        }
        
        echo '</div>';
        
        return ob_get_clean();
    }
} 