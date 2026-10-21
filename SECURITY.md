# Security

## Implemented boundaries

- Fortify session authentication; verified e-mail for author/community/download actions; privileged administration requires password confirmation and confirmed TOTP in production.
- User role is never accepted at registration/profile update. Work and nested chapter/file ownership is authorized server-side. Public queries exclude drafts, future releases, archived works and suspended owners.
- Session cookies are encrypted, HTTP-only and secure in production. Password resets revoke database sessions and rotate remember tokens. Account deletion logs out before removing the user to prevent token cycling from recreating the record.
- CSRF protection remains enabled. Named rate limits protect authentication, searches, community actions, author tools and downloads. Production trusts only the configured host; reverse proxies must be explicitly listed.
- Bound SQL values and whitelisted sort/filter fields. Blade escaping for comments and user fields. Chapter/blog HTML is reduced to safe formatting with no attributes, active elements or external media.
- Covers are decoded, bounded by pixel count and re-encoded as JPEG. Documents have extension/content type/size checks, randomized private paths, a digest, quarantine and fail-closed malware scanning. Failed scans do not grant access.
- EPUB extraction never writes archive paths to the filesystem. Entry count, total declared size, compression ratio, traversal, external resources and XML entities are checked. Imports become drafts.
- Every download rechecks verified access, publication, rights confirmation and the author’s download switch. Original files are attachments with sandbox CSP. File paths are never public URLs.
- Optimistic chapter revisions reject stale edits with HTTP 409; transactions serialize chapter position allocation and restoration. Versions and moderation actions are recorded.
- CSP forbids external scripts, frames, plugins and external connections by default. Inline styles are permitted for the rich text editor and page flip library. No analytics tracker or external font CDN is installed.

## Operating requirements

Use the production configuration and `imary:doctor --production --scan`. Keep mail, TLS, packages, ClamAV definitions, queue workers and scheduled tasks healthy. Do not expose `storage/app/private`, database ports, PHP-FPM or scanner endpoints. Keep APP_KEY and backups secret. The supported production session driver is `database` so account/password operations can revoke sessions deterministically.

These controls do not certify a system as invulnerable. They do not implement DRM, guarantee detection of all malicious files, replace moderation, or provide legal validation of uploaded rights claims. A server deployment and browser acceptance review remain necessary.

## Reporting a problem

Use GitHub private vulnerability reporting when enabled by the repository owner, or the configured operator contact. Do not publish user data, reset tokens, recovery codes, environment files or exploitable details in public issues. There is no hard-coded security inbox or third-party incident service.
