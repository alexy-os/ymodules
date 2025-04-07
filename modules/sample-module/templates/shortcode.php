<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="ymodules-sample-shortcode">
    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
        <?php if (!empty($atts['title'])): ?>
            <div class="px-4 py-5 sm:px-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                    <?php echo esc_html($atts['title']); ?>
                </h3>
            </div>
        <?php endif; ?>

        <div class="border-t border-gray-200">
            <div class="px-4 py-5 sm:p-6">
                <?php if (!empty($content)): ?>
                    <div class="prose max-w-none">
                        <?php echo wp_kses_post($content); ?>
                    </div>
                <?php else: ?>
                    <p class="text-sm text-gray-500">
                        <?php _e('No content provided for this shortcode.', 'ymodules'); ?>
                    </p>
                <?php endif; ?>

                <?php if ($atts['type'] === 'custom'): ?>
                    <div class="mt-4">
                        <?php
                        // Add custom type-specific content here
                        $custom_option = get_option('ymodules_sample_option', '');
                        if (!empty($custom_option)):
                        ?>
                            <div class="rounded-md bg-blue-50 p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-blue-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-blue-800">
                                            <?php _e('Custom Option Value', 'ymodules'); ?>
                                        </h3>
                                        <div class="mt-2 text-sm text-blue-700">
                                            <p><?php echo esc_html($custom_option); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div> 