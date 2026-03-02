# CLAUDE.md — WP Puller

## Project overview

WP Puller is a WordPress plugin that automatically updates themes and plugins from GitHub repositories. It supports public and private repos, webhook-based real-time updates, branch testing, encrypted token management, and automatic backups.

The installable plugin lives in the `wp-puller/` directory. The repo root contains the plugin directory plus repo-level files (README, LICENSE).

## Tech stack

- **PHP 7.4+** — WordPress plugin API, AJAX handlers, REST API endpoints
- **JavaScript (jQuery)** — card-based admin UI in `wp-puller/assets/js/admin.js`
- **CSS** — admin styles in `wp-puller/assets/css/admin.css`
- **WordPress APIs** — Options API, AJAX, REST API, Filesystem API, Transients

## Directory structure

```
wp-puller/                   # Plugin root (this is the installable directory)
├── wp-puller.php            # Main plugin file, constants, activation hooks
├── uninstall.php            # Cleanup on uninstall
├── assets/
│   ├── js/admin.js          # All admin UI JavaScript
│   └── css/admin.css        # All admin UI styles
├── includes/
│   ├── class-wp-puller.php        # Main singleton class
│   ├── class-admin.php            # Admin menu, AJAX handlers, script enqueuing
│   ├── class-asset-updater.php    # Update logic, validation, installation
│   ├── class-github-api.php       # GitHub API wrapper
│   ├── class-webhook-handler.php  # Webhook processing via REST API
│   ├── class-backup.php           # Backup creation/restore
│   └── class-logger.php           # Activity logging
├── templates/
│   └── admin-page.php             # PHP template for admin UI
└── languages/
    └── wp-puller.pot              # Translation template
```

## Version management

Version is defined in three places that must stay in sync:

1. `wp-puller/wp-puller.php` — plugin header comment (`Version: X.Y.Z`)
2. `wp-puller/wp-puller.php` — PHP constant (`WP_PULLER_VERSION`)
3. `wp-puller/includes/class-wp-puller.php` — class property (`$version`)

**With every commit, bump the version** (patch for fixes, minor for features) in all three locations.

## Build artifact

**With every commit, include an updated zip file** of the plugin directory:

```bash
cd /home/user/wp-puller && zip -r wp-puller.zip wp-puller/
```

This produces `wp-puller.zip` at the repo root, which is the installable WordPress plugin archive. Always regenerate and stage the zip before committing.

## Key conventions

- The admin menu is registered as a top-level page via `add_menu_page()` (hook: `toplevel_page_wp-puller`).
- All AJAX actions are prefixed with `wp_puller_` and registered in `class-admin.php`.
- Assets use the `wp-puller-` CSS class prefix throughout.
- The plugin uses a singleton pattern — access via `wp_puller()`.
- GitHub tokens are AES-256-CBC encrypted using the WordPress `AUTH_KEY` salt.
- All user input is sanitized/escaped per WordPress coding standards.
