# Charrua Maintenance Helper

WordPress plugin that assists with routine site maintenance tasks. Designed for agencies and freelancers who manage multiple WordPress sites on a monthly basis.

## Features

### Plugin Monitor

Detects plugins that become unexpectedly deactivated during update processes.

When WordPress updates a plugin, it temporarily deactivates it, replaces the files, and reactivates it. If the reactivation fails (due to a fatal error, incompatibility, or other issue), the plugin remains inactive. This module catches those cases.

How it works:

- Before any plugin update begins, a snapshot of currently active plugins is stored in a short-lived transient (5 minutes TTL).
- After the update process completes, the module compares the snapshot with the current list of active plugins.
- Any plugin that was active before the update but inactive after is flagged as an alert.
- Alerts are displayed on the Plugin Monitor admin page, where they can be individually reviewed or dismissed in bulk.

This detection runs only during update operations. It adds no overhead to normal admin page loads.

### Activity Log

Keeps a chronological record of plugin and theme events, including which user triggered them.

Tracked events:

- Plugin activated
- Plugin deactivated
- Plugin installed
- Plugin updated
- Plugin deleted
- Theme activated (switched)
- Theme updated
- Theme installed

Each entry records the event type, object name, object author, version (including previous version for updates), the user who triggered it, their IP address, the execution source, and a timestamp. Entries older than 90 days are automatically cleaned up via a daily WP-Cron task.

The log is accessible from its own admin page with pagination and filtering by object type (plugin or theme). The user column links directly to the WordPress user profile.

#### Source Detection

Each event is tagged with the execution context that triggered it:

- **Admin** -- Action performed from the WordPress admin panel.
- **WP-CLI** -- Action triggered via WP-CLI command.
- **Cron (auto-update)** -- Automatic update scheduled by WordPress.
- **REST API (external)** -- External service such as ManageWP, MainWP, or Jetpack.
- **AJAX** -- AJAX request (used by some management plugins).

This helps distinguish manual maintenance from automated or remote actions.

#### Zip Upload Detection

When a plugin is replaced by uploading a zip file through the WordPress admin, the log correctly identifies it as an update (not a fresh install), records the previous and new versions, and marks the details as "Manual zip upload".

## Admin Interface

The plugin adds a "Maintenance" menu in the WordPress admin sidebar with two subpages:

- **Activity Log** (default) -- Browse the event history with filters and pagination.
- **Plugin Monitor** -- View and dismiss deactivation alerts.

Both pages require the `manage_options` capability.

## Requirements

- WordPress 6.0 or higher
- PHP 7.4 or higher

## Development

### Releasing a New Version

1. Bump the version in four places:
   - `Version:` header in `charrua-maintenance-helper.php`
   - `CHARRUA_MH_VERSION` constant in `charrua-maintenance-helper.php`
   - `DB_VERSION` constant in `includes/class-database.php`
   - `version` in `package.json`
2. Commit and push to `main`.
3. Create a GitHub Release with a tag matching the version (e.g. `v1.4.0`).

A GitHub Actions workflow (`.github/workflows/release.yml`) triggers automatically on every published release: it installs dependencies, builds the zip, and attaches it as a release asset. WordPress sites running the plugin will detect the new release and show the update in the Plugins page.

### Building the Zip Locally

```bash
npm run plugin-zip
```

Output: `release/charrua-maintenance-helper.zip`

## Auto-Update from GitHub

The plugin checks for new releases on its GitHub repository and offers updates through the standard WordPress update mechanism. When a new release is published on GitHub with a `.zip` asset attached, WordPress will detect it and show the update in the Plugins page just like any plugin hosted on wordpress.org.

No configuration is required. The update checker runs automatically using the [plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker) library.

## Installation

1. Upload the `charrua-maintenance-helper` folder to `wp-content/plugins/`.
2. Activate the plugin through the WordPress admin.
3. Navigate to Maintenance in the admin sidebar.

## Uninstallation

When the plugin is deleted through the WordPress admin, all associated data is removed:

- Custom database table (`wp_charrua_mh_activity_log`)
- Options (`charrua_mh_db_version`, `charrua_mh_monitor_alerts`)
- Transients
- Scheduled cron events

## Technical Notes

- The plugin uses a modular architecture. Each feature is self-contained in its own class under `includes/modules/`.
- All database operations use `$wpdb->prepare()` for SQL safety.
- Admin actions are protected with nonce verification and capability checks.
- The activity log table is created using `dbDelta()` for safe initial creation and future schema upgrades.
- The database schema version is tracked to support migrations in future releases.

## File Structure

```
charrua-maintenance-helper.php    Main plugin bootstrap
includes/
  class-loader.php                Hook registration and module loading
  class-database.php              Table management, activation, cleanup
  modules/
    class-activity-log.php        Activity logging module
    class-plugin-monitor.php      Deactivation detection module
admin/
  class-admin.php                 Admin menu and request handling
  views/
    plugin-monitor.php            Plugin Monitor page template
    activity-log.php              Activity Log page template
uninstall.php                     Clean data removal on uninstall
```

## Author

Daniel Pereyra Costas @ Charrua
https://charrua.es
