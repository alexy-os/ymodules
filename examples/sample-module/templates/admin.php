<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap ymodules-sample-admin">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-2xl font-semibold text-gray-900"><?php _e('Sample Module Settings', 'ymodules'); ?></h1>
        </div>

        <div class="bg-white shadow rounded-lg p-6">
            <form method="post" action="options.php" class="space-y-6">
                <?php settings_fields('ymodules_sample_options'); ?>
                <?php do_settings_sections('ymodules_sample_options'); ?>

                <div>
                    <label for="sample_option" class="block text-sm font-medium text-gray-700">
                        <?php _e('Sample Option', 'ymodules'); ?>
                    </label>
                    <div class="mt-1">
                        <input type="text" name="ymodules_sample[option]" id="sample_option" 
                               value="<?php echo esc_attr(get_option('ymodules_sample_option', '')); ?>"
                               class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md">
                    </div>
                    <p class="mt-2 text-sm text-gray-500">
                        <?php _e('This is a sample option field.', 'ymodules'); ?>
                    </p>
                </div>

                <div>
                    <label for="sample_textarea" class="block text-sm font-medium text-gray-700">
                        <?php _e('Sample Textarea', 'ymodules'); ?>
                    </label>
                    <div class="mt-1">
                        <textarea name="ymodules_sample[textarea]" id="sample_textarea" rows="4"
                                  class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md"><?php 
                            echo esc_textarea(get_option('ymodules_sample_textarea', '')); 
                        ?></textarea>
                    </div>
                </div>

                <div>
                    <label for="sample_select" class="block text-sm font-medium text-gray-700">
                        <?php _e('Sample Select', 'ymodules'); ?>
                    </label>
                    <select name="ymodules_sample[select]" id="sample_select"
                            class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                        <option value="option1" <?php selected(get_option('ymodules_sample_select'), 'option1'); ?>>
                            <?php _e('Option 1', 'ymodules'); ?>
                        </option>
                        <option value="option2" <?php selected(get_option('ymodules_sample_select'), 'option2'); ?>>
                            <?php _e('Option 2', 'ymodules'); ?>
                        </option>
                        <option value="option3" <?php selected(get_option('ymodules_sample_select'), 'option3'); ?>>
                            <?php _e('Option 3', 'ymodules'); ?>
                        </option>
                    </select>
                </div>

                <div class="pt-5">
                    <div class="flex justify-end">
                        <button type="submit" class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <?php _e('Save Changes', 'ymodules'); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div> 