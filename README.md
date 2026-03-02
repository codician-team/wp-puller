## WP Puller v2.0 — Multi-Asset Management

### What's New

**Manage unlimited themes and plugins from GitHub — all from one screen.**

WP Puller v2.0 is a ground-up rewrite. The old single-asset tabbed interface is gone, replaced by a card-based dashboard that lets you manage as many GitHub-connected themes and plugins as you need.

Thanks to https://github.com/codician-team for building the original version!

---

### Multi-Asset Support

- Add **unlimited themes and plugins**, each with its own GitHub repo, branch, and settings
- Each asset gets its own card showing name, version, commit, and connection status
- Automatic migration from v1.x — your existing configuration carries over

### Card-Based Admin Interface

- **Asset cards** in a responsive grid, each showing live status at a glance: version, current commit SHA, last check time, and connection state
- **Slide-out panels** for Settings, Branches, and Backups — one click from each card's footer icons
- **Bulk actions** in the header: Check All for Updates, Update All
- **Confirmation modals** for destructive actions (restore, delete, deploy, remove)
- **Notice bar** with auto-dismiss for success/error/warning feedback

### Shared Token Management

- Store multiple GitHub Personal Access Tokens in a shared, encrypted vault
- Reuse the same token across multiple assets — no need to paste it again
- Supports both fine-grained (`github_pat_`) and classic (`ghp_`) tokens
- AES-256-CBC encryption at rest using WordPress security salts
- Orphaned tokens auto-cleaned when the last asset using them is removed

### Branch Management

- **Branches panel** shows the 20 most recently active branches, sorted by commit date
- Fetches all branches (up to 1000 via GraphQL, 500 via REST) then sorts by recency — no more alphabetical guessing
- **Deploy** any branch for testing — backup created automatically before switching
- **Use for Updates** — promote a tested branch to be the configured updates branch
- **Compare** any branch against the deployed/configured branch: see commits ahead/behind, files changed with additions and deletions
- Configured branch shows a green "updates" badge; deployed branch highlighted in the table

### Webhook-Based Auto-Updates

- Single **global webhook endpoint** (`/wp-json/wp-puller/v1/webhook`) handles all assets
- GitHub push events are matched to configured assets by repo URL and branch
- HMAC-SHA256 signature verification with timing-safe comparison
- Per-asset auto-update toggle — enable or disable webhook-triggered updates individually
- **Webhook panel** accessible from the header with payload URL, secret, copy buttons, and step-by-step GitHub setup instructions
- One-click secret regeneration

### Backup System

- Automatic backup before every update (manual or webhook)
- Per-asset backup retention: 1–10 backups (configurable)
- One-click **restore** from the Backups panel
- Backup list shows name, creation date, file size, and detected version from asset headers
- Manual delete with confirmation

### Activity Log

- Last 20 events displayed with status indicator, timestamp, and source
- Logs show **asset name** and **semantic version** (e.g., "MyTheme updated successfully to 2.1.0")
- Events logged: updates (success/failure), backups created, restores, webhook events, signature failures
- Clear all logs with one click

### Update Checking

- Per-asset **Check for Updates** — shows current vs. latest version, commit SHAs, and update availability
- Detects version from asset headers (style.css for themes, main PHP file for plugins)
- **Check All** runs across every configured asset and displays results inline on each card

### Theme & Plugin Support

- **Themes**: validates `style.css` with Theme Name header, detects active theme
- **Plugins**: scans PHP files for Plugin Name header, checks active status
- **Subdirectory support**: set a path within the repo if the asset lives in a subfolder
- Archive validation before install — helpful error messages if structure is wrong

### Security

- All AJAX endpoints protected by WordPress nonces and `manage_options` capability checks
- Webhook signatures verified with HMAC-SHA256
- Tokens encrypted at rest, decrypted only on demand
- File operations use WordPress Filesystem API
- Backup directory protected with `.htaccess`

---

### Requirements

- WordPress 5.0+
- PHP 7.4+ with OpenSSL
- Writable `/wp-content/` directory
- GitHub PAT for private repositories (public repos work without one)

---

### Upgrade Notes

- **From v1.x**: Activate the updated plugin — your single-asset configuration will be automatically migrated to the new multi-asset format. No manual steps required.
- **Webhook URL unchanged**: If you already have a GitHub webhook configured, it will continue working.
- **Token re-encryption**: Your existing PAT will be migrated into the new encrypted token vault.
