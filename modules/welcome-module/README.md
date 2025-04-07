# Welcome Module

A simple YModules module that displays a welcome page in the WordPress admin.

## Features

- Adds a "Welcome" admin menu item
- Displays a clean, Tailwind-styled admin page
- Demonstrates basic YModules structure and functionality

## Installation

1. Upload the module through the YModules interface
2. Activate the module
3. Navigate to the "Welcome" menu item in the WordPress admin

## Structure

```
welcome-module/
├── module.json - Module metadata
├── README.md - Documentation 
└── src/
    └── module.php - Main module code
```

## Customization

Feel free to customize the welcome page by editing the `render_admin_page` method in `src/module.php`.

## Requirements

- WordPress 5.0+
- YModules plugin
- Tailwind CSS (loaded via CDN) 