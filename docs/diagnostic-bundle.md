# Gloskin diagnostic bundle

`Download Diagnostic` is a read-only admin tool under **Gloskin Content**. It is visible and callable only when the current user has `manage_options` and the exact, case-sensitive WordPress `user_login` is `namaste`.

The button progressively uses authenticated `admin-ajax.php` so generation has a live loading state and bounded retry for network, HTTP 429, and HTTP 5xx failures. The same nonce-protected `admin-post.php` action remains the no-JavaScript fallback. There is no public, `nopriv`, REST, CLI, or cron entry point.

The temporary ZIP contains:

- `README.txt` and `manifest.json`
- safe environment and site-structure reports
- allowlisted Gloskin editorial records and metadata
- Promo selection and exclusion diagnostics
- allowlisted migration/finalizer state
- catalog-only WooCommerce data
- referenced-media metadata without media binaries
- bounded first-party source snapshots and file hashes
- runtime health and same-origin route checks

The exporter excludes credentials, salts, environment variables, raw users/usermeta, logs, complete options/postmeta, orders, customers, addresses, payments, refunds, sessions, form submissions, consultation inboxes, private messages, and media binaries. Suspicious structured keys and high-confidence credential patterns are redacted in the diagnostic copy only.

Safety limits are 1 MB per textual source, 20 MB total source, 50 MB final ZIP, and 20 route checks. ZIP files are generated only in the WordPress temporary directory, streamed directly, and deleted on success or failure with both `finally` cleanup and a shutdown fallback. Review the archive before sharing it with trusted maintainers.
