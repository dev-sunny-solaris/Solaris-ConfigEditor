# Solaris Config Editor

A powerful PHP configuration file editor that allows you to programmatically read and modify PHP configuration files while preserving formatting and structure. Built with [nikic/php-parser](https://github.com/nikic/PHP-Parser).

## Features

- 📝 **Read & Modify** PHP config files programmatically
- 🔧 **Preserve Formatting** - Maintains original code formatting and structure
- 🎯 **Dot Notation** - Access nested arrays using dot notation (e.g., `database.connections.mysql.host`)
- 🔄 **Merge Config** - Merge configuration files intelligently
- 🚀 **Laravel Support** - Works seamlessly with Laravel 12+
- 🐘 **PHP 8.2+** - Modern PHP with typed properties and union types
- ⚙️ **Raw Code** - Support for raw PHP expressions via `setRaw()` and `addRaw()`

## Requirements

- PHP 8.2 or higher
- nikic/php-parser ^4.13 or ^5.0

## Installation

Install the package via Composer:

```bash
composer require solaris/config-editor
```

## Quick Start

### Basic Usage

```php
use Solaris\ConfigEditor\ConfigEditor;

// Load a config file
$config = new ConfigEditor('config/database.php');

// Set a value
$config->set('connections.mysql.host', '127.0.0.1');
$config->set('connections.mysql.port', 3306);

// Get a value (has method)
if ($config->has('connections.mysql.username')) {
    // Do something
}

// Add a new key (throws exception if key exists)
$config->add('new_setting', 'value');

// Delete a key
$config->delete('connections.mysql');

// Save changes
$config->save();
```

### Working with Raw PHP Code

For complex values like closures or PHP expressions:

```php
// Set raw PHP code
$config->setRaw('cache.stores.redis.client', 'env("REDIS_CLIENT", "phpredis")');

// Add raw PHP code
$config->addRaw('logging.channels.custom', 'Log::channel("custom")');

// Push raw value to array
$config->pushRaw('providers', 'AppServiceProvider::class');
```

### Array Operations

```php
// Add value to existing array
$config->push('providers', 'MyServiceProvider::class');

// Push raw PHP expression to array
$config->pushRaw('middleware', 'TrustProxies::class');
```

### Merging Configurations

```php
$config = new ConfigEditor('config/app.php');

// Merge another config file
$config->merge('config/app.local.php')
    ->save();
```

### Fluent Interface

Most methods return `$this`, allowing method chaining:

```php
$config = new ConfigEditor('config/database.php');

$config
    ->set('connections.mysql.host', 'localhost')
    ->set('connections.mysql.database', 'myapp')
    ->set('connections.mysql.prefix', 'app_')
    ->merge('config/database.local.php')
    ->save();
```

## API Reference

### Constructor

```php
new ConfigEditor(string $path)
```

Loads a PHP configuration file. Path can be with or without `.php` extension.

**Throws:** `RuntimeException` if file not found or is not a valid config file.

### Methods

#### `set(string $key, mixed $value): void`

Set a configuration value. Creates intermediate arrays if needed.

#### `add(string $key, mixed $value): void`

Add a new configuration key. Throws exception if key already exists.

#### `setRaw(string $key, string $code): self`

Set a value using raw PHP code.

#### `addRaw(string $key, string $code): self`

Add a new value using raw PHP code.

#### `push(string $key, mixed $value): void`

Add a value to an existing array.

#### `pushRaw(string $key, string $code): self`

Add a raw PHP expression to an existing array.

#### `delete(string $key): void`

Delete a configuration key.

#### `has(string $key): bool`

Check if a configuration key exists.

#### `merge(string $path): self`

Merge another configuration file. Returns self for chaining.

#### `save(?string $filename = null): self`

Save changes to file. If filename is provided, saves to that file instead.

## Laravel Integration

This package works seamlessly with Laravel's configuration files:

```php
use Solaris\ConfigEditor\ConfigEditor;

// Edit Laravel config file
$config = new ConfigEditor(config_path('database.php'));

$config
    ->set('default', 'pgsql')
    ->set('connections.pgsql.host', env('DB_HOST', 'localhost'))
    ->save();
```

### Laravel Service Provider (Optional)

You can create a service provider to register this as a singleton:

```php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Solaris\ConfigEditor\ConfigEditor;

class ConfigEditorProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('config-editor', function ($app) {
            return new ConfigEditor(config_path('app.php'));
        });
    }
}
```

Then use it in your application:

```php
app('config-editor')->set('key', 'value')->save();
```

## Examples

### Update Database Configuration

```php
$config = new ConfigEditor('config/database.php');

$config
    ->set('connections.mysql.host', 'db.example.com')
    ->set('connections.mysql.username', 'dbuser')
    ->set('connections.mysql.password', 'secret')
    ->set('connections.mysql.database', 'production_db')
    ->save();
```

### Add New Service Provider

```php
$config = new ConfigEditor('config/app.php');

$config->push('providers', 'App\\Providers\\CustomServiceProvider::class');
$config->save();
```

### Merge Environment-Specific Config

```php
$config = new ConfigEditor('config/app.php');

if (env('APP_ENV') === 'production') {
    $config->merge('config/app.production.php');
}

$config->save();
```

## Testing

Run the test suite:

```bash
composer test
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for release notes and version history.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## Support

For issues and questions, please use the [GitHub Issues](https://github.com/solaris/solaris-config-editor/issues) page.

---

Made with ❤️ by Solaris
