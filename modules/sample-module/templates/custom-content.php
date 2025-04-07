<?php
if (!defined('ABSPATH')) {
    exit;
}

$custom_option = get_option('ymodules_sample_option', '');
$custom_textarea = get_option('ymodules_sample_textarea', '');
$custom_select = get_option('ymodules_sample_select', 'option1');
?>

<div class="ymodules-sample-custom-content">
    <div class="bg-gray-50 overflow-hidden sm:rounded-lg mb-8">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                <?php _e('Additional Information', 'ymodules'); ?>
            </h3>

            <?php if (!empty($custom_option)): ?>
                <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-4">
                    <div class="px-4 py-5 sm:px-6">
                        <h4 class="text-sm font-medium text-gray-500">
                            <?php _e('Custom Option', 'ymodules'); ?>
                        </h4>
                        <p class="mt-1 text-sm text-gray-900">
                            <?php echo esc_html($custom_option); ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($custom_textarea)): ?>
                <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-4">
                    <div class="px-4 py-5 sm:px-6">
                        <h4 class="text-sm font-medium text-gray-500">
                            <?php _e('Custom Textarea Content', 'ymodules'); ?>
                        </h4>
                        <div class="mt-1 text-sm text-gray-900 prose max-w-none">
                            <?php echo wp_kses_post(wpautop($custom_textarea)); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6">
                    <h4 class="text-sm font-medium text-gray-500">
                        <?php _e('Selected Option', 'ymodules'); ?>
                    </h4>
                    <p class="mt-1 text-sm text-gray-900">
                        <?php
                        switch ($custom_select) {
                            case 'option1':
                                _e('Option 1 is selected', 'ymodules');
                                break;
                            case 'option2':
                                _e('Option 2 is selected', 'ymodules');
                                break;
                            case 'option3':
                                _e('Option 3 is selected', 'ymodules');
                                break;
                            default:
                                _e('No option selected', 'ymodules');
                        }
                        ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div> 