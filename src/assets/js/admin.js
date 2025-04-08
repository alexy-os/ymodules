/**
 * YModules Admin Interface
 * 
 * Manages the admin interface for YModules including:
 * - Module installation (drag & drop, file selection)
 * - Module management (activation, deactivation, deletion)
 * - Modal dialogs for module operations
 * 
 * Following the Y Modules Manifesto principles:
 * - Zero Redundancy: Optimized code with minimal duplication
 * - Minimal Requests: Direct PHP rendering with minimal AJAX
 * - Maximal Performance: Efficient DOM operations and event handling
 */
jQuery(document).ready(function($) {
    // Guard to prevent multiple initialization
    if (window._ymodules_initialized) {
        return;
    }
    
    // Mark as initialized
    window._ymodules_initialized = true;
    
    /**
     * Modal management
     */
    function openModal(modalId) {
        $(modalId).removeClass('hidden');
    }

    function closeModal(modalId) {
        $(modalId).addClass('hidden');
    }

    // Close modal and reset form
    $('.ymodules-close-modal').on('click', function() {
        const modal = $(this).closest('.fixed');
        closeModal(modal);
        // Reset file input and file info when closing modal
        $('#module-file').val('');
        $('#file-info').addClass('hidden');
    });

    // Open upload modal
    $('#ymodules-upload-btn').on('click', function() {
        openModal('#ymodules-upload-modal');
    });

    /**
     * File upload handling
     */
    // Handle file selection
    $('#module-file').on('change', function() {
        const file = this.files[0];
        if (file) {
            // Validate ZIP file
            if (!file.name.toLowerCase().endsWith('.zip')) {
                alert('Please select a ZIP file');
                $(this).val('');
                $('#file-info').addClass('hidden');
                return;
            }
            
            $('#selected-file-name').text(file.name);
            $('#file-info').removeClass('hidden');
        } else {
            $('#file-info').addClass('hidden');
        }
    });

    /**
     * Drag and drop functionality
     */
    const dropZone = $('.border-dashed');
    
    // Only setup drag and drop if the drop zone exists
    if (dropZone.length > 0) {
        // Prevent default drag events
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.on(eventName, preventDefaults);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        // Handle drag events styling
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.on(eventName, highlight);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.on(eventName, unhighlight);
        });

        function highlight() {
            dropZone.addClass('border-indigo-500 bg-indigo-50');
        }

        function unhighlight() {
            dropZone.removeClass('border-indigo-500 bg-indigo-50');
        }

        // Handle file drop
        dropZone.on('drop', function(e) {
            e.preventDefault();
            
            const file = e.originalEvent.dataTransfer.files[0];
            if (file) {
                // Validate ZIP file
                if (!file.name.toLowerCase().endsWith('.zip')) {
                    alert('Please select a ZIP file');
                    return;
                }
                
                $('#module-file')[0].files = e.originalEvent.dataTransfer.files;
                $('#selected-file-name').text(file.name);
                $('#file-info').removeClass('hidden');
            }
        });
    }

    /**
     * Module upload form submission
     */
    $('#ymodules-upload-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData();
        const fileInput = $('#module-file')[0];
        
        // Validate file selection
        if (fileInput.files.length === 0) {
            alert('Please select a file to upload');
            return;
        }

        // Prepare form data
        formData.append('action', 'ymodules_upload_module');
        formData.append('nonce', ymodulesAdmin.nonce);
        formData.append('module', fileInput.files[0]);

        // Send AJAX request
        $.ajax({
            url: ymodulesAdmin.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
                // Show loading state
                $('#ymodules-upload-form button[type="submit"]').prop('disabled', true).html(
                    '<svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">' +
                    '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>' +
                    '<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>' +
                    '</svg> Uploading...'
                );
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    const successHtml = `
                        <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                                <div>
                                    <span class="font-medium">Success!</span> ${response.data.message}
                                </div>
                            </div>
                        </div>
                    `;
                    
                    $('#file-info').html(successHtml);
                    
                    // Reload page after delay
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                } else {
                    // Show error message
                    const errorHtml = `
                        <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2h-1V9z" clip-rule="evenodd"></path>
                                </svg>
                                <div>
                                    <span class="font-medium">Error!</span> ${response.data.message || 'Upload failed'}
                                </div>
                            </div>
                        </div>
                    `;
                    
                    $('#file-info').html(errorHtml).removeClass('hidden');
                }
            },
            error: function() {
                // Show error message for AJAX failure
                const errorHtml = `
                    <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2h-1V9z" clip-rule="evenodd"></path>
                            </svg>
                            <div>
                                <span class="font-medium">Error!</span> Upload failed. Please try again.
                            </div>
                        </div>
                    </div>
                `;
                
                $('#file-info').html(errorHtml).removeClass('hidden');
            },
            complete: function() {
                // Reset button state
                $('#ymodules-upload-form button[type="submit"]').prop('disabled', false).html('Upload');
            }
        });
    });

    /**
     * Module details view
     */
    $(document).on('click', '.view-module-details', function() {
        const module = $(this).data('module');
        
        $('#modal-module-name').text(module.name);
        $('#modal-module-description').text(module.description);
        $('#modal-module-version').text(module.version);
        $('#modal-module-author').text(module.author);
        
        openModal('#ymodules-details-modal');
    });

    /**
     * Module operations: Activation, Deactivation, Deletion
     */
    // Handle module activation
    $(document).on('click', '.activate-module', function() {
        const slug = $(this).data('slug');
        
        if (!slug) {
            alert('Module slug not found');
            return;
        }
        
        if (!confirm('Are you sure you want to activate this module?')) {
            return;
        }
        
        executeModuleOperation('ymodules_activate_module', slug, 'Failed to activate module');
    });

    // Handle module deactivation
    $(document).on('click', '.deactivate-module', function() {
        const slug = $(this).data('slug');
        
        if (!slug) {
            alert('Module slug not found');
            return;
        }
        
        if (!confirm('Are you sure you want to deactivate this module?')) {
            return;
        }
        
        executeModuleOperation('ymodules_deactivate_module', slug, 'Failed to deactivate module');
    });

    // Handle module deletion
    $(document).on('click', '.delete-module', function() {
        const slug = $(this).data('slug');
        
        if (!slug) {
            alert('Module slug not found');
            return;
        }
        
        if (!confirm('Are you sure you want to delete this module? This action cannot be undone.')) {
            return;
        }
        
        executeModuleOperation('ymodules_delete_module', slug, 'Failed to delete module');
    });

    /**
     * Common function for module operations to reduce redundancy
     * 
     * @param {string} action AJAX action to perform
     * @param {string} slug Module slug
     * @param {string} errorMessage Error message to show on failure
     */
    function executeModuleOperation(action, slug, errorMessage) {
        $.ajax({
            url: ymodulesAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: action,
                nonce: ymodulesAdmin.nonce,
                slug: slug
            },
            success: function(response) {
                if (response.success) {
                    // Reload page to reflect changes
                    window.location.reload();
                } else {
                    alert(response.data.message || errorMessage);
                }
            },
            error: function() {
                alert(errorMessage + '. Please try again.');
            }
        });
    }
}); 