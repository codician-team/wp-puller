=== WP Puller ===
Contributors: cnbrkdmrci
Tags: github, deployment, theme, updater, webhook
Requires at least: 5.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically update WordPress themes from GitHub with webhook-based deployments.

== Description ==

WP Puller connects your WordPress theme to a GitHub repository so pushes can be deployed quickly from the WordPress admin.

Features include:

* Webhook-based updates from GitHub push events.
* Public and private repository support.
* Automatic backups before each update.
* Branch and subdirectory deployment support.

= External services =

This plugin uses third-party services to perform its core functionality:

* `https://api.github.com` (GitHub API)
    * Purpose: Reads repository metadata and downloads update zipballs for the configured repository.
    * Data sent: Repository owner/name, selected branch/tag, and (for private repositories) the configured GitHub token in an authorization header.
    * Triggered when: Testing repository connection, checking for updates, and downloading updates.
    * Service terms/privacy: https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement

* `https://www.cloudflare.com/ips-v4` and `https://www.cloudflare.com/ips-v6`
    * Purpose: Refreshes trusted Cloudflare proxy IP ranges used for webhook source validation, with bundled ranges as fallback.
    * Data sent: None beyond a standard outbound HTTP GET request from your server.
    * Triggered when: The cached trusted-proxy ranges expire and the plugin refreshes them.
    * Service terms/privacy: https://www.cloudflare.com/privacypolicy/

== Installation ==

1. In WordPress admin, go to Plugins > Add New > Upload Plugin.
2. Upload the plugin zip and activate **WP Puller**.
3. Open **WP Puller** in the admin menu and enter your GitHub repository URL.
4. Configure optional branch/path settings and save.
5. (Optional) Add the provided webhook URL and secret in your GitHub repository settings for automatic push deployments.

== Frequently Asked Questions ==

= Does WP Puller support private repositories? =

Yes. Add a GitHub Personal Access Token with repository read access in plugin settings.

= What happens if an update fails? =

WP Puller creates backups before updates so you can restore a previous version.

= Can I update plugins with WP Puller? =

Not yet. WP Puller currently targets WordPress themes.

== Screenshots ==

1. Plugin settings screen with repository configuration.
2. Connected status and manual update controls.
3. Backup and restore panel.

== Changelog ==

= 1.0.8 =

* Current stable release.
* Added Cloudflare trusted-proxy range refresh with bundled fallback ranges.
* Security and hardening improvements for update and webhook paths.
