# Contributing to WP Puller

Thanks for your interest in improving WP Puller! This document explains how we
work so that contributions are easy to review and merge.

WP Puller is a WordPress plugin that updates a theme (or plugin) from a GitHub
repository, with webhook-based real-time updates and encrypted token storage.
Because it touches authentication, webhooks, encryption, and the filesystem, we
hold contributions to a high security bar — see [Security](#security) below.

## Getting started

1. Fork the repository and create a branch off `main`:
   ```bash
   git checkout -b fix/short-description main
   ```
2. Install the development tooling (PHP 7.4+ and Composer required):
   ```bash
   composer install
   ```
3. Make your change, then run the checks described below.
4. Open a pull request against `main` and fill out the PR template.

The plugin code lives in `wp-puller/`. The repository also ships a built
`wp-puller.zip`; **do not** rebuild or commit it in feature PRs unless that is
the explicit purpose of the PR — release artifacts are handled separately.

## Keep pull requests focused

One logical change per PR. Small, scoped PRs get reviewed and merged quickly;
large, multi-concern PRs (rewrites, sweeping refactors) are hard to review
safely and tend to stall. If you are planning a large change or a change in
product direction, please open an issue to discuss it first so we can agree on
an approach and a way to split it into reviewable pieces.

## Coding standards

We follow the [WordPress Coding Standards](https://github.com/WordPress/WordPress-Coding-Standards)
(WPCS) via PHP_CodeSniffer. The ruleset lives in `phpcs.xml.dist`.

```bash
composer run lint       # report violations
composer run lint:fix   # auto-fix what can be fixed (phpcbf)
php -l path/to/file.php  # syntax check a single file
```

> **Note on the current baseline.** The existing codebase predates this
> ruleset and carries a backlog of (mostly auto-fixable) formatting
> violations. The WPCS job in CI is therefore **advisory** for now: it reports
> violations but does not block merges. Please do not introduce *new*
> violations, and run `composer run lint:fix` on the files you touch. Once the
> backlog is cleared in a dedicated cleanup PR, the WPCS job will become a
> required gate.

Other conventions:

- Internationalise all user-facing strings with the `wp-puller` text domain.
- Prefix global functions/classes/options with `wp_puller` / `WP_Puller`.
- Escape on output (`esc_html`, `esc_attr`, `esc_url`) and sanitise on input.

## Continuous integration

Every push and pull request runs the [`CI`](.github/workflows/ci.yml) workflow:

- **PHP Lint** — `php -l` on every PHP file across PHP 7.4–8.3. This is a
  **required** check; PRs cannot merge while it is failing.
- **WordPress Coding Standards** — PHPCS/WPCS, currently advisory (see above).

## Security

WP Puller handles GitHub tokens, signed webhooks, AES-256-CBC encryption, and
writes to the WordPress filesystem. When changing security-sensitive code,
please preserve the project's hardening rules:

- **Webhooks**: verify the `X-Hub-Signature-256` HMAC signature *before* acting
  on any event (including `ping`). Keep the IP-based rate limiting in place.
- **Encryption**: tokens are stored with authenticated encryption (the `v2:`
  format: AES-256-CBC + HMAC-SHA256). Do not weaken this or reintroduce a
  hardcoded fallback key.
- **HTTP**: use `wp_safe_remote_*` for outbound requests.
- **Filesystem**: guard against path traversal (`..`) in user-supplied paths;
  do not suppress filesystem errors with `@`.
- **Secrets**: never log tokens, secrets, or token prefixes, and never echo
  them unmasked in the admin UI.

If you discover a security vulnerability, please **do not** open a public issue.
Instead, report it privately to the maintainers (see the repository's security
policy / contact) so it can be fixed before disclosure.

## Reporting bugs and requesting features

Open an issue with:

- WordPress and PHP versions
- Steps to reproduce (for bugs), expected vs. actual behaviour
- Relevant log output (with any secrets redacted)

Thanks again for contributing! 🎉
