# YModules Core Framework

YModules is a modular extension system for PHP applications, following the Y Modules Manifesto principles of Zero Redundancy, Minimal Requests, and Maximal Performance.

## Integration with Different Platforms

YModules can be integrated with various PHP frameworks and CMS platforms:

### WordPress

YModules is already set up as a WordPress plugin. Simply install and activate the plugin.

```php
// Example: manually initializing YModules in a WordPress theme or plugin
require_once '/path/to/ymodules/autoload.php';

$ymodules = \YModules\YModules::getInstance([
    'plugin_file' => __FILE__ // Path to your main plugin file
]);
```

### Laravel

```php
// Example: using YModules in a Laravel application
require_once '/path/to/ymodules/autoload.php';

// In a service provider:
public function register()
{
    $this->app->singleton(\YModules\YModules::class, function ($app) {
        return \YModules\YModules::getInstance([
            'base_path' => base_path(),
            'app' => $app
        ]);
    });
}
```

### Symfony

```php
// Example: using YModules in a Symfony application
require_once '/path/to/ymodules/autoload.php';

// In a bundle or service:
$ymodules = \YModules\YModules::getInstance([
    'kernel' => $kernel,
    'container' => $container
]);
```

### Custom PHP Application

```php
// Example: using YModules in a custom PHP application
require_once '/path/to/ymodules/autoload.php';

$ymodules = \YModules\YModules::getInstance([
    'base_path' => __DIR__,
    'modules_path' => __DIR__ . '/modules',
    'assets_path' => __DIR__ . '/assets'
]);
```

## Working with Modules

### Creating a Module

A YModules module consists of a directory with:

1. A `module.json` file with metadata:
   ```json
   {
       "name": "Example Module",
       "version": "1.0.0",
       "description": "An example module",
       "author": "YModules",
       "namespace": "YModules\\Example"
   }
   ```

2. A `src/module.php` file with the module class:
   ```php
   <?php
   namespace YModules\Example;
   
   class Module {
       /**
        * Initialize the module
        */
       public static function init() {
           // Module initialization code
           return new self();
       }
       
       /**
        * Admin initialization (optional)
        */
       public static function admin_init() {
           // Admin-specific initialization
       }
   }
   ```

### Module Operations

```php
// Get the YModules instance
$ymodules = \YModules\YModules::getInstance();

// Get all modules
$modules = $ymodules->getModules();

// Get active modules
$active_modules = $ymodules->getActiveModules();

// Activate a module
$ymodules->activateModule('example-module');

// Deactivate a module
$ymodules->deactivateModule('example-module');

// Delete a module
$ymodules->deleteModule('example-module');
```

## Creating Custom Adapters

To support a new platform, create a class that implements `\YModules\Core\PlatformAdapterInterface` and add it to the adapter factory.

```php
<?php
namespace YModules\Adapters\Custom;

use YModules\Core\PlatformAdapterInterface;
use YModules\Core\ModuleLoader;

class CustomAdapter implements PlatformAdapterInterface {
    // Implement the interface methods
}
```

Then update the `AdapterFactory` to support your new platform:

```php
// In AdapterFactory::createForPlatform:
case 'custom':
    return new \YModules\Adapters\Custom\CustomAdapter($options);
```

## Principles

- **Zero Redundancy**: No superfluous code, libraries, or dependencies
- **Minimal Requests**: Only essential interactions with databases, servers, APIs, and browsers
- **Maximal Performance**: Optimization for speed in both perception and reality

---

Follow the Y Modules Manifesto for best practices when developing modules and extending the core framework. 