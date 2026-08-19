<?php
declare(strict_types=1);

/* Reuse the canonical runtime smoke WordPress shims before loading the
 * migration-to-render fixture. The fixture then adds only migration-specific
 * in-memory primitives. */
require_once __DIR__ . '/runtime-smoke-wordpress-stubs.php';

/* The integration fixture builds an in-memory copy of runtime-smoke.php and
 * searches for its literal `$plugin = ...` bootstrap line. Seed the local
 * variable with that literal so the fixture's double-quoted search string does
 * not interpolate to an empty value. This runner mutates no repository state. */
$plugin = '$plugin';

require __DIR__ . '/final-migration-render-fixture.php';
