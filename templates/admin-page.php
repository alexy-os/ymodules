<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap ymodules-admin">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-2xl font-semibold text-gray-900"><?php _e('YModules', 'ymodules'); ?></h1>
            <div class="flex items-center space-x-4">
                <button type="button" id="ymodules-upload-btn" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <?php _e('Upload Module', 'ymodules'); ?>
                </button>
            </div>
        </div>

        <!-- Upload Modal -->
        <div id="ymodules-upload-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full">
            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Upload Module</h3>
                    <div class="mt-2 px-7 py-3">
                        <form id="ymodules-upload-form" class="space-y-4">
                            <div class="flex items-center justify-center w-full">
                                <label for="module-file" class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100">
                                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                        <svg class="w-8 h-8 mb-4 text-gray-500" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 16">
                                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 13h3a3 3 0 0 0 0-6h-.025A5.56 5.56 0 0 0 16 6.5 5.5 5.5 0 0 0 5.207 5.021C5.137 5.017 5.071 5 5 5a4 4 0 0 0 0 8h2.167M10 15V6m0 0L8 8m2-2 2 2"/>
                                        </svg>
                                        <p class="mb-2 text-sm text-gray-500"><span class="font-semibold">Click to upload</span> or drag and drop</p>
                                        <p class="text-xs text-gray-500">ZIP files only</p>
                                    </div>
                                    <input id="module-file" type="file" class="hidden" accept=".zip" />
                                </label>
                            </div>
                            <div id="file-info" class="hidden">
                                <div class="flex items-center p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50">
                                    <svg class="flex-shrink-0 inline w-4 h-4 me-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5Zm3.707 8.207-4 4a1 1 0 0 1-1.414 0l-2-2a1 1 0 0 1 1.414-1.414L9 10.586l3.293-3.293a1 1 0 0 1 1.414 1.414Z"/>
                                    </svg>
                                    <span class="font-medium">File selected:</span>
                                    <span id="selected-file-name" class="ml-2"></span>
                                </div>
                            </div>
                            <div class="flex justify-end space-x-3">
                                <button type="button" class="ymodules-close-modal px-4 py-2 bg-gray-200 text-gray-800 text-base font-medium rounded-md shadow-sm hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-300">
                                    Cancel
                                </button>
                                <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-base font-medium rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    Upload
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modules Grid -->
        <div id="ymodules-grid" class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Modules will be loaded here dynamically -->
        </div>

        <!-- Module Details Modal -->
        <div id="ymodules-details-modal" class="hidden fixed inset-0 bg-gray-500 bg-opacity-75 overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
                    <div class="absolute right-0 top-0 pr-4 pt-4">
                        <button type="button" class="ymodules-close-modal rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none">
                            <span class="sr-only"><?php _e('Close', 'ymodules'); ?></span>
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="sm:flex sm:items-start">
                        <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                            <h3 class="text-lg font-semibold leading-6 text-gray-900" id="modal-module-name"></h3>
                            <div class="mt-4 space-y-4">
                                <div>
                                    <h4 class="text-sm font-medium text-gray-500"><?php _e('Description', 'ymodules'); ?></h4>
                                    <p class="mt-1 text-sm text-gray-900" id="modal-module-description"></p>
                                </div>
                                <div>
                                    <h4 class="text-sm font-medium text-gray-500"><?php _e('Version', 'ymodules'); ?></h4>
                                    <p class="mt-1 text-sm text-gray-900" id="modal-module-version"></p>
                                </div>
                                <div>
                                    <h4 class="text-sm font-medium text-gray-500"><?php _e('Author', 'ymodules'); ?></h4>
                                    <p class="mt-1 text-sm text-gray-900" id="modal-module-author"></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div> 