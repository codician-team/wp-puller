# WP Puller

Auto-update WordPress themes from GitHub. Free and open source.

[![WordPress 5.0+](https://img.shields.io/badge/WordPress-5.0%2B-0073aa.svg)](https://wordpress.org/)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg)](https://php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

---

Push to GitHub → Theme updates automatically. No FTP, no manual uploads.

WP Puller connects your WordPress theme to a GitHub repository. When you push changes, a webhook triggers and your live site updates within seconds. Works with both public and private repositories.

**This is the free alternative to WP Pusher.**

---

## What It Does

- **Webhook-based deploys** - Push to GitHub, site updates automatically
- **Private repo support** - Connect with a GitHub Personal Access Token
- **Automatic backups** - Snapshot before every update, one-click restore
- **Subdirectory themes** - Theme doesn't need to be at repo root
- **Branch selection** - Deploy from main, staging, production, or any branch

---

## Install

1. Download `wp-puller.zip` from [Releases](../../releases)
2. WordPress admin → Plugins → Add New → Upload Plugin
3. Upload ZIP, activate

Or manually upload the `wp-puller` folder to `/wp-content/plugins/`.

---

## Setup

### Connect a Repository

1. Go to **WP Puller** in the admin sidebar
2. Enter your GitHub repo URL: `https://github.com/you/your-theme`
3. Select branch (usually `main`)
4. If your theme is in a subdirectory, enter the path (e.g., `theme/starter-theme`)
5. Click **Test Connection**, then **Save Settings**

### Set Up Webhook (for auto-updates)

1. Copy the **Payload URL** and **Secret** from WP Puller
2. GitHub repo → Settings → Webhooks → Add webhook
3. Paste the URL and secret
4. Content type: `application/json`
5. Events: Just the push event
6. Save

Now every push to your branch triggers an automatic update.

### Manual Updates

Don't want webhooks? Click **Check for Updates** then **Update Now** whenever you want to pull the latest.

---

## Private Repositories

For private repos, you need a GitHub Personal Access Token:

### Fine-grained token (recommended)

1. GitHub → Settings → Developer settings → Personal access tokens → Fine-grained tokens
2. Generate new token
3. Select only the repository you need
4. Permissions: **Contents** (read) and **Metadata** (read)
5. Generate, copy, paste into WP Puller

### Classic token

1. GitHub → Settings → Developer settings → Personal access tokens → Tokens (classic)
2. Generate new token
3. Select `repo` scope
4. Generate, copy, paste into WP Puller

Your token is encrypted with AES-256-CBC before storage.

---

## Repository Structure

Theme files can be at the root:

```
your-repo/
├── style.css
├── functions.php
├── index.php
└── ...
```

Or in a subdirectory (set "Theme Path" in settings):

```
your-repo/
├── other-stuff/
└── theme/starter-theme/    ← Theme Path: theme/starter-theme
    ├── style.css
    ├── functions.php
    └── ...
```

The theme needs a valid `style.css` with a `Theme Name` header.

---

## FAQ

**Is this actually free?**
Yes. MIT license, no premium tier, no feature gates.

**How does it compare to WP Pusher?**
Same core idea—deploy WordPress themes from GitHub. WP Puller is free and open source. WP Pusher has more features (plugins, GitLab, Bitbucket) but costs money.

**What if an update breaks my site?**
Restore from the Backups section. WP Puller keeps automatic backups before every update.

**Can I use this for plugins?**
Not yet. Theme-only for now.

**Does it work with GitLab/Bitbucket?**
GitHub only.

**Is my token secure?**
Encrypted at rest using your WordPress security salts. Never logged or transmitted except to GitHub.

---

## For Developers

### Hooks

```php
// After successful update
do_action( 'wp_puller_theme_updated', $commit_data, $source );

// After backup restore
do_action( 'wp_puller_theme_restored', $backup_name );

// On init
do_action( 'wp_puller_init' );
```

### Security

- Nonce verification on all AJAX
- `manage_options` capability required
- Webhook signatures verified (HMAC SHA-256)
- Tokens encrypted with AES-256-CBC
- All file operations via WP_Filesystem

---

## Requirements

- WordPress 5.0+
- PHP 7.4+
- OpenSSL extension (for token encryption)

---

## Contributing

Issues and PRs welcome. Fork it, make changes, submit a PR.

---

## License

MIT. Do whatever you want with it.
