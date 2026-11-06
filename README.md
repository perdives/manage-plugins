# Manage Plugins

A WordPress plugin that gives you fine-grained control over which plugins are allowed to run. Manage plugin activation through configuration with support for environment-based rules and global settings.

[![Download](https://img.shields.io/badge/Download-Latest%20Release-brightgreen)](https://github.com/perdives/manage-plugins/releases/latest)

## Features

- **Disable Plugins**: Prevent specific plugins from loading
- **Require Plugins**: Force specific plugins to activate (if installed)
- **Environment-Based Rules**: Different rules for production, staging, and development
- **Global Rules**: Apply rules across all environments
- **Early Execution**: Runs as an MU-plugin before all other plugins load, and installs its own MU-plugin loader on activation
- **Database-safe**: Rules are applied at runtime only; the stored list of active plugins is never changed
- **Zero UI**: Configuration through version-controllable JSON file
- **Graceful Fallback**: Works even if config file is missing

## Requirements

- PHP 7.4 or higher
- WordPress 5.0 or higher

## Why Use This Plugin?

Common use cases:

- **Prevent Debug Tools in Production**: Automatically disable Query Monitor, Debug Bar, etc. in production
- **Require Security Plugins**: Ensure Wordfence or similar plugins are always active in production
- **Environment-Specific Caching**: Disable caching plugins in development
- **Prevent Performance Issues**: Disable resource-intensive plugins in specific environments
- **Force Critical Plugins**: Ensure essential plugins can't be accidentally deactivated

## Installation

### Option 1: Composer (Recommended)

```bash
composer require perdives/manage-plugins
```

Then copy the MU-plugin loader to ensure early execution:

```bash
cp vendor/perdives/manage-plugins/mu-plugin-loader.php wp-content/mu-plugins/
```

### Option 2: Manual Installation

1. Download `manage-plugins-x.y.z.zip` from the [latest release](https://github.com/perdives/manage-plugins/releases/latest)
2. Upload it via **Plugins → Add New → Upload Plugin** (or extract to `wp-content/plugins/manage-plugins/`)
3. Activate it

On activation the plugin writes `wp-content/mu-plugins/manage-plugins-loader.php`, a small loader that makes it run as an MU-plugin. Deactivating removes the loader again. If the loader goes missing while the plugin is active, it is re-created on the next admin request. If `wp-content/mu-plugins/` isn't writable, an admin notice shows the file to create by hand.

The generated loader refers to `WP_PLUGIN_DIR` (no absolute paths), so it keeps working when the site is moved, and it does nothing when the plugin folder has been removed. When a manually copied `mu-plugin-loader.php` is present (Composer installs), no loader is generated.

### Why the MU-Plugin Loader?

WordPress loads plugins in this order:
1. **MU-plugins** (must-use plugins) - loaded first
2. **Regular plugins** - loaded second

To properly control which plugins load, this plugin **must run before** other plugins. The MU-plugin loader ensures this happens automatically. When the plugin is loaded as a regular plugin only, it applies no rules and shows an admin notice.

## Configuration

### 1. Create Configuration File

Create a file at `/config/managed-plugins.json` (in your project root, one level above `wp-content/`):

```bash
mkdir -p config
cp vendor/perdives/manage-plugins/managed-plugins.json.example config/managed-plugins.json
```

Or copy from the plugin directory if installed manually:

```bash
mkdir -p config
cp wp-content/plugins/manage-plugins/managed-plugins.json.example config/managed-plugins.json
```

### 2. Configure Rules

Edit `config/managed-plugins.json`:

```json
{
  "environments": {
    "production": {
      "disabled": [
        "debug-bar/debug-bar.php",
        "query-monitor/query-monitor.php"
      ],
      "required": [
        "wordfence/wordfence.php"
      ]
    },
    "development": {
      "disabled": [
        "wordfence/wordfence.php",
        "wp-rocket/wp-rocket.php"
      ],
      "required": [
        "query-monitor/query-monitor.php"
      ]
    }
  },
  "global": {
    "disabled": [
      "broken-plugin/plugin.php"
    ],
    "required": []
  }
}
```

### Configuration Structure

- **`environments`**: Environment-specific rules (production, staging, development, etc.)
  - **`disabled`**: Plugins to prevent from loading
  - **`required`**: Plugins to force activate (if installed)
- **`global`**: Rules that apply to ALL environments
  - **`disabled`**: Always prevent these plugins from loading
  - **`required`**: Always activate these plugins (if installed)

### Plugin Path Format

Use the format: `folder-name/main-file.php`

Examples:
- `akismet/akismet.php`
- `query-monitor/query-monitor.php`
- `wordfence/wordfence.php`

You can find plugin paths in your WordPress admin under **Plugins** or by checking the folder structure in `wp-content/plugins/`.

## Environment Detection

The plugin detects your environment by checking (in order):

1. **`WP_ENV` environment variable** (highest priority)
2. **`WP_ENVIRONMENT_TYPE` constant** in `wp-config.php`
3. **Deployer's `.environment` file** at `~/deploy/shared/.environment`
4. **Defaults to `production`** if none are set

### Setting Your Environment

**Option 1: Environment Variable (Recommended)**

```bash
export WP_ENV=development
```

**Option 2: wp-config.php Constant**

```php
define('WP_ENVIRONMENT_TYPE', 'staging');
```

**Option 3: Deployer Environment File**

```bash
echo "staging" > ~/deploy/shared/.environment
```

## How It Works

1. The MU-plugin loader runs before all regular plugins
2. The plugin reads your `managed-plugins.json` configuration
3. It detects the current environment
4. Before WordPress loads plugins, it filters the active plugins list:
   - **Removes disabled plugins** from the active list
   - **Adds required plugins** to the active list (if installed)
5. WordPress loads only the filtered plugins
6. When WordPress saves the active plugins list (for example when an admin activates another plugin), the stored state of managed plugins is restored first: disabled plugins that are really active stay active in the database, and required plugins that were only added at runtime are not written to it. Without this, any plugin (de)activation in an environment with rules would permanently persist those rules.

If a plugin is listed as both disabled and required, **required wins**.

## Error Handling

- **Missing config file**: No plugins are disabled/required (graceful fallback)
- **Invalid JSON**: Error logged; wp_die() in non-production environments
- **Production errors**: Logged but site continues to function
- **Non-production errors**: Displays error screen for immediate debugging

## Examples

### Disable Debug Tools in Production

```json
{
  "environments": {
    "production": {
      "disabled": [
        "debug-bar/debug-bar.php",
        "query-monitor/query-monitor.php",
        "developer/developer.php"
      ],
      "required": []
    }
  },
  "global": {
    "disabled": [],
    "required": []
  }
}
```

### Require Security Plugins

```json
{
  "environments": {
    "production": {
      "disabled": [],
      "required": [
        "wordfence/wordfence.php",
        "sucuri-scanner/sucuri.php"
      ]
    }
  },
  "global": {
    "disabled": [],
    "required": []
  }
}
```

### Different Tools Per Environment

```json
{
  "environments": {
    "production": {
      "disabled": [
        "query-monitor/query-monitor.php"
      ],
      "required": [
        "wordfence/wordfence.php",
        "wp-rocket/wp-rocket.php"
      ]
    },
    "development": {
      "disabled": [
        "wordfence/wordfence.php",
        "wp-rocket/wp-rocket.php"
      ],
      "required": [
        "query-monitor/query-monitor.php",
        "debug-bar/debug-bar.php"
      ]
    }
  },
  "global": {
    "disabled": [],
    "required": []
  }
}
```

## Troubleshooting

### Plugin Not Working

**Check the MU-plugin loader is in place:**

```bash
ls -la wp-content/mu-plugins/mu-plugin-loader.php
```

If missing, copy it:

```bash
cp vendor/perdives/manage-plugins/mu-plugin-loader.php wp-content/mu-plugins/
# or from plugin directory
cp wp-content/plugins/manage-plugins/mu-plugin-loader.php wp-content/mu-plugins/
```

### Disabled Plugins Still Loading

**Check your config file location:**

```bash
ls -la config/managed-plugins.json
```

Should be in your project root (one level above `wp-content/`), NOT inside `wp-content/`.

**Verify environment detection:**

Add to `wp-config.php` temporarily:

```php
error_log('Current environment: ' . getenv('WP_ENV'));
```

### Required Plugins Not Activating

**Ensure plugins are installed:**

Required plugins must be installed. The plugin won't download or install them automatically.

**Check plugin paths are correct:**

Compare your config with actual plugin paths in `wp-content/plugins/`.

## Development

### Installing Dependencies

```bash
composer install
```

### Code Standards

```bash
# Check code standards
composer phpcs:check

# Fix code standards
composer phpcs:fix
```

## Security

- No database modifications
- Read-only config file access
- Graceful error handling in production
- No remote code execution
- No user input processing

## License

This plugin is licensed under the GPL v2 or later.

## Support

For issues, questions, or contributions:

- **Issues**: [GitHub Issues](https://github.com/perdives/manage-plugins/issues)
- **Documentation**: This README
- **Source Code**: [GitHub Repository](https://github.com/perdives/manage-plugins)

## Credits

Developed by [Perdives](https://perdives.com)
