<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$pluginRoot = $root . '/plugin/gloskin-site-core';
$deploy = (string) file_get_contents($root . '/.cpanel.yml');

$forbidden = array(
    "wp-login.php",
    "add_action( 'login_init'",
    "add_filter( 'login_url'",
    "add_filter( 'authenticate'",
    "add_action( 'set_auth_cookie'",
    "add_action( 'set_logged_in_cookie'",
    "option_active_plugins",
    "site_option_active_sitewide_plugins",
    "wordpress_test_cookie",
    "TEST_COOKIE",
    "wp_set_auth_cookie(",
    "wp_signon(",
);

$violations = array();
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pluginRoot, FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }
    $contents = (string) file_get_contents($file->getPathname());
    foreach ($forbidden as $needle) {
        if (strpos($contents, $needle) !== false) {
            $violations[] = str_replace($root . '/', '', $file->getPathname()) . ' contains forbidden Core-auth ownership token: ' . $needle;
        }
    }
}

$kernel = (string) file_get_contents($pluginRoot . '/includes/class-gloskin-site-core-kernel.php');
if (strpos($kernel, 'is_auth_request') !== false || strpos($kernel, "'/masuk'") !== false) {
    $violations[] = 'Gloskin kernel must not branch on WordPress Core authentication paths.';
}

if (strpos($deploy, 'cp -f mu-plugins/000-markas-auth-guard.php') !== false) {
    $violations[] = 'Deployment must never install the retired Core-auth MU guard.';
}
if (strpos($deploy, 'rm -f $MUPATH/000-markas-auth-guard.php') === false) {
    $violations[] = 'Deployment must remove the retired Core-auth MU guard from hosts that received it.';
}

if ($violations) {
    fwrite(STDERR, "Core auth boundary contract failed:\n- " . implode("\n- ", $violations) . "\n");
    exit(1);
}

echo "core-auth-boundary-contract.php: OK (WordPress Core owns authentication)\n";
