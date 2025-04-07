jQuery(document).ready(function($) {
    // Modal handling
    function openModal(modalId) {
        $(modalId).removeClass('hidden');
    }

    function closeModal(modalId) {
        $(modalId).addClass('hidden');
    }

    // Close modal when clicking outside
    $('.ymodules-close-modal').on('click', function() {
        const modal = $(this).closest('.fixed');
        closeModal(modal);
    });

    // Open upload modal
    $('#ymodules-upload-btn').on('click', function() {
        openModal('#ymodules-upload-modal');
    });

    // Handle file selection
    $('#module-file').on('change', function() {
        const file = this.files[0];
        if (file) {
            $('#selected-file-name').text(file.name);
            $('#file-info').removeClass('hidden');
        } else {
            $('#file-info').addClass('hidden');
        }
    });

    // Handle drag and drop
    $('.border-dashed').on('dragover', function(e) {
        e.preventDefault();
        $(this).addClass('border-blue-500 bg-blue-50');
    }).on('dragleave', function(e) {
        e.preventDefault();
        $(this).removeClass('border-blue-500 bg-blue-50');
    }).on('drop', function(e) {
        e.preventDefault();
        $(this).removeClass('border-blue-500 bg-blue-50');
        
        const file = e.originalEvent.dataTransfer.files[0];
        if (file && file.name.endsWith('.zip')) {
            $('#module-file')[0].files = e.originalEvent.dataTransfer.files;
            $('#selected-file-name').text(file.name);
            $('#file-info').removeClass('hidden');
        }
    });

    // Handle file upload
    $('#ymodules-upload-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData();
        const fileInput = $('#module-file')[0];
        
        if (fileInput.files.length === 0) {
            alert('Please select a file to upload');
            return;
        }

        formData.append('action', 'ymodules_upload_module');
        formData.append('nonce', ymodulesAdmin.nonce);
        formData.append('module', fileInput.files[0]);

        console.log('Uploading file:', fileInput.files[0]);
        console.log('AJAX URL:', ymodulesAdmin.ajaxUrl);
        console.log('Nonce:', ymodulesAdmin.nonce);

        $.ajax({
            url: ymodulesAdmin.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function(xhr) {
                console.log('Sending request...');
                // Show loading state
                $('#ymodules-upload-form button[type="submit"]').prop('disabled', true).html(
                    '<svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">' +
                    '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>' +
                    '<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>' +
                    '</svg> Uploading...'
                );
            },
            success: function(response) {
                console.log('Upload success:', response);
                if (response.success) {
                    alert(response.data.message || 'Module uploaded successfully');
                    window.location.reload();
                } else {
                    alert(response.data.message || 'Upload failed');
                }
            },
            error: function(xhr, status, error) {
                console.error('Upload error:', {
                    status: status,
                    error: error,
                    response: xhr.responseText
                });
                alert('Upload failed. Please try again.');
            },
            complete: function() {
                console.log('Upload complete');
                // Reset button state
                $('#ymodules-upload-form button[type="submit"]').prop('disabled', false).html('Upload');
            }
        });
    });

    // Cache for modules list
    let modulesCache = null;
    let lastModulesUpdate = 0;
    const CACHE_DURATION = 30000; // 30 seconds

    // Load modules on page load
    loadModules();

    function loadModules(forceReload = false) {
        const now = Date.now();
        
        // Return cached data if available and not expired
        if (!forceReload && modulesCache && (now - lastModulesUpdate) < CACHE_DURATION) {
            updateModulesList(modulesCache);
            return;
        }

        $.ajax({
            url: ymodulesAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ymodules_get_modules',
                nonce: ymodulesAdmin.nonce
            },
            success: function(response) {
                console.log('Modules response:', response);
                
                if (response.success) {
                    // Ensure we have an array of modules
                    const modules = Array.isArray(response.data) ? response.data : 
                                  (response.data.modules ? response.data.modules : []);
                    
                    console.log('Processed modules:', modules);
                    
                    modulesCache = modules;
                    lastModulesUpdate = now;
                    updateModulesList(modules);
                } else {
                    console.error('Failed to load modules:', response.data.message);
                    updateModulesList([]); // Show empty state
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading modules:', {
                    status: status,
                    error: error,
                    response: xhr.responseText
                });
                updateModulesList([]); // Show empty state
            }
        });
    }

    function updateModulesList(modules) {
        console.log('Updating modules list with:', modules);
        
        const $grid = $('#ymodules-grid');
        $grid.empty();

        if (!Array.isArray(modules) || modules.length === 0) {
            $grid.html('<div class="text-center py-8 text-gray-500">No modules installed</div>');
            return;
        }

        modules.forEach(function(module) {
            console.log('Processing module:', module);
            
            const card = $(`
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <h3 class="text-sm font-medium text-gray-900 truncate">${module.name || 'Unnamed Module'}</h3>
                                <p class="text-sm text-gray-500">v${module.version || '1.0.0'}</p>
                            </div>
                            <div class="ml-4 flex-shrink-0">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                    module.active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                                }">
                                    ${module.active ? 'Active' : 'Inactive'}
                                </span>
                            </div>
                        </div>
                        <div class="mt-4">
                            <p class="text-sm text-gray-500 line-clamp-2">${module.description || 'No description available'}</p>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-5 py-3">
                        <div class="text-sm flex justify-between">
                            <button type="button" class="font-medium text-indigo-600 hover:text-indigo-500 view-module-details" data-module='${JSON.stringify(module)}'>
                                View details
                            </button>
                            <div class="space-x-2">
                                ${module.active ? 
                                    `<button type="button" class="font-medium text-gray-600 hover:text-gray-800 deactivate-module" data-slug="${module.slug}">
                                        Deactivate
                                    </button>` : 
                                    `<button type="button" class="font-medium text-green-600 hover:text-green-800 activate-module" data-slug="${module.slug}">
                                        Activate
                                    </button>`
                                }
                                <button type="button" class="font-medium text-red-600 hover:text-red-800 delete-module" data-slug="${module.slug}">
                                    Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `);

            $grid.append(card);
        });
    }

    // Handle module details view
    $(document).on('click', '.view-module-details', function() {
        const module = $(this).data('module');
        
        $('#modal-module-name').text(module.name);
        $('#modal-module-description').text(module.description);
        $('#modal-module-version').text(module.version);
        $('#modal-module-author').text(module.author);
        
        openModal('#ymodules-details-modal');
    });

    // Drag and drop handling
    const dropZone = $('.border-dashed');
    
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.on(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.on(eventName, highlight, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.on(eventName, unhighlight, false);
    });

    function highlight(e) {
        dropZone.addClass('border-indigo-500 bg-indigo-50');
    }

    function unhighlight(e) {
        dropZone.removeClass('border-indigo-500 bg-indigo-50');
    }

    dropZone.on('drop', handleDrop, false);

    function handleDrop(e) {
        const dt = e.originalEvent.dataTransfer;
        const files = dt.files;

        $('#module-file')[0].files = files;
    }

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
        
        $.ajax({
            url: ymodulesAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ymodules_activate_module',
                nonce: ymodulesAdmin.nonce,
                slug: slug
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message || 'Module activated successfully');
                    window.location.reload();
                } else {
                    alert(response.data.message || 'Failed to activate module');
                }
            },
            error: function() {
                alert('Failed to activate module. Please try again.');
            }
        });
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
        
        $.ajax({
            url: ymodulesAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ymodules_deactivate_module',
                nonce: ymodulesAdmin.nonce,
                slug: slug
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message || 'Module deactivated successfully');
                    window.location.reload();
                } else {
                    alert(response.data.message || 'Failed to deactivate module');
                }
            },
            error: function() {
                alert('Failed to deactivate module. Please try again.');
            }
        });
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
        
        $.ajax({
            url: ymodulesAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ymodules_delete_module',
                nonce: ymodulesAdmin.nonce,
                slug: slug
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message || 'Module deleted successfully');
                    window.location.reload();
                } else {
                    alert(response.data.message || 'Failed to delete module');
                }
            },
            error: function() {
                alert('Failed to delete module. Please try again.');
            }
        });
    });
}); 