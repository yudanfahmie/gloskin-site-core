GLOSKIN CONTENT DIAGNOSTIC
===========================

Purpose: a read-only snapshot for diagnosing Gloskin content, routes, migrations, Promo, WooCommerce boundaries, media references, and first-party integration code.
Generated: 2026-08-20T15:50:16+07:00 (Asia/Jakarta)
Schema version: 1.1

Included: safe environment metadata, site structure, allowlisted editorial content/meta, Promo eligibility, migration state, catalog-only WooCommerce data, referenced media metadata, bounded code manifests/source, runtime health, and same-origin route checks.
Excluded: credentials, salts, environment variables, users/usermeta, authentication data, logs, database dumps, complete options/postmeta, orders, customers, addresses, payment/refund/session data, form submissions, consultation inboxes, private messages, and media binaries.

Redaction: suspicious structured keys and high-confidence credential patterns are replaced. Absolute WordPress paths are removed. Source files are copied into this ZIP only; deployed files are never changed.
Limits: 1 MB per source file, 20 MB total source snapshot, 50 MB final ZIP, and 20 same-origin route checks.
Known limitation: manifest.json lists itself as self-referential without its own size/hash, because embedding its final hash would recursively change that hash.

Safe sharing: review this bundle before sharing and provide it only to trusted maintainers. The exporter does not change website data and leaves no persistent archive.
