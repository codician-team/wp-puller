<!--
  Thanks for contributing to WP Puller! Please fill out the sections below.
  Keep PRs focused: one logical change per PR is much easier to review and merge.
-->

## Summary

<!-- What does this PR do, and why? -->

## Type of change

- [ ] Bug fix (non-breaking change that fixes an issue)
- [ ] New feature (non-breaking change that adds functionality)
- [ ] Security fix
- [ ] Breaking change (fix or feature that changes existing behaviour)
- [ ] Refactor / chore (no functional change)
- [ ] Documentation

## Related issues

<!-- e.g. "Closes #123". If there is no issue, briefly explain the motivation. -->

## How was this tested?

<!--
  Describe the steps you took to verify the change. For example:
  - WordPress version(s) and PHP version(s) tested
  - Steps to reproduce the original behaviour and confirm the fix
  - `php -l` / `composer run lint` output
-->

## Checklist

- [ ] My change is focused on a single concern (large rewrites are split into reviewable parts).
- [ ] `php -l` passes on all changed files.
- [ ] I ran `composer run lint` and did not introduce new coding-standards violations.
- [ ] I have not committed secrets, tokens, or rebuilt binaries (e.g. `wp-puller.zip`) unless explicitly intended.
- [ ] Security-sensitive code (auth, webhooks, encryption, file I/O, HTTP requests) has been reviewed for the project's hardening rules — see CONTRIBUTING.md.
- [ ] User-facing strings are internationalised with the `wp-puller` text domain.

## Screenshots / notes (optional)
