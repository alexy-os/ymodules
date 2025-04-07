# Sample Module for YModules

## Overview
Sample Module is a demonstration module for the YModules framework that showcases common WordPress functionality and integration patterns. This module serves as both a working example and a template for developing custom modules.

## Features

### Custom Post Type
- Registers a `sample_type` custom post type with standard fields (title, editor, thumbnail)
- Fully compatible with the WordPress Block Editor (Gutenberg)
- Accessible via the REST API for headless implementations

### Shortcode Integration
- Provides the `[sample_shortcode]` shortcode for embedding module content anywhere
- Supports customization through attributes:
  - `title`: Displays a custom header
  - `type`: Changes display mode (set to "custom" to show additional information)
- Allows content to be placed between shortcode tags for flexible content display

### Content Enhancement
- Automatically enhances `sample_type` post content with additional information
- Displays module settings in a clean, responsive interface
- Uses modern design patterns with Tailwind CSS styling

### Admin Integration
- Adds a dedicated settings page under the YModules menu
- Provides sample form fields for demonstration:
  - Text input
  - Textarea
  - Select dropdown
- Demonstrates proper WordPress settings API usage

## Configuration Options

### Sample Option
A simple text field for storing basic information.

### Sample Textarea
A larger text area for storing formatted content, which is automatically processed with `wpautop()` for proper paragraph formatting.

### Sample Select
A dropdown selection with three options to demonstrate state management.

## Usage Examples

### Basic Shortcode
```
[sample_shortcode title="My Sample Content"]
This is some content that will be displayed in the shortcode output.
[/sample_shortcode]
```

### Custom Type Shortcode
```
[sample_shortcode title="Advanced Example" type="custom"]
This will display both this content and the custom option value if set.
[/sample_shortcode]
```

### Template Integration
```php
<?php
// Display custom content anywhere in your theme
echo do_shortcode('[sample_shortcode title="Programmatic Example"]Content here[/sample_shortcode]');
?>
```

## Development

This module demonstrates best practices for WordPress development:

- Namespaced PHP classes
- Singleton pattern for module initialization
- Proper hook usage for WordPress integration
- Separation of logic and presentation
- Modern CSS with Tailwind
- Proper escaping and sanitization

## Requirements
- WordPress 5.8+
- YModules framework
- PHP 7.4+
