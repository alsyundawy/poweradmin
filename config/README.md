# Poweradmin Configuration

This directory contains the configuration files for Poweradmin.

## Important Version Information

- **Version 4.0.0**: Support for both old (`inc/config.inc.php`) and new (`config/settings.php`) configuration formats
- **Next Major Release**: Support for old configuration format will be completely removed

## Configuration Files

- `settings.defaults.php`: Sample configuration file with all available settings and their default values
- `settings.php`: Your actual configuration (copy from sample and modify)

## How to Configure Poweradmin

1. Copy the sample configuration file:
   ```
   cp settings.defaults.php settings.php
   ```

2. Edit `settings.php` to match your environment:
   ```
   nano settings.php
   ```

3. At minimum, you should configure:
   - Database connection details
   - Security settings (especially the session key)
   - DNS nameserver information

## Migration from Old Configuration

If you're migrating from an earlier version of Poweradmin that used `inc/config.inc.php`, you can use the migration script:

```
php scripts/migrate-config.php
```

This will:
1. Read your existing configuration from `inc/config.inc.php`
2. Convert it to the new format
3. Save it as `config/settings.php`

It is recommended to migrate to the new configuration format as soon as possible, as support for the old format will be completely removed in the next major release.

## Configuration Structure

The configuration is organized into logical groups:

- `database`: Database connection settings
- `security`: Security-related settings
- `interface`: User interface settings
- `dns`: DNS-related settings
- `mail`: Email configuration
- `dnssec`: DNSSEC settings
- `pdns_api`: PowerDNS API configuration
- `logging`: Logging settings
- `ldap`: LDAP authentication settings
- `misc`: Miscellaneous settings

## Example

```php
// Database configuration
'database' => [
    'host' => 'localhost',
    'port' => '3306',
    'user' => 'poweradmin',
    'password' => 'your_secure_password',
    'name' => 'powerdns',
    'type' => 'mysql',
],

// Interface settings
'interface' => [
    'language' => 'en_EN',
    'theme' => 'ignite',
    'rows_per_page' => 15,
],
```