<?php
// Test helper: point the running mastercontainer's deSEC integration at a local mock by
// writing the (config-only) desec_api_base / desec_update_url keys into configuration.json.
// Run inside the mastercontainer via `docker exec` from the Playwright CI workflows; there
// is no env override for these on purpose (see ConfigurationManager::$desecApiBase).
//
// Writing configuration.json makes Setup::CanBeInstalled() return false, so /setup no longer
// renders the initial-password page and no master password is generated. To keep the deSEC
// Playwright test able to log in, this helper also seeds a known master password (passed via
// the AIO_TEST_PASSWORD env var) that the test then uses directly. The password is stored in
// plaintext, which matches how AIO compares it (AuthManager::CheckCredentials uses
// hash_equals against the plaintext config value).
//
// Re-running this helper also RESETS the deSEC-specific state (domain, desec_email and the
// DESEC_TOKEN / DESEC_PASSWORD secrets) so that a deSEC flow which already registered a domain
// in a previous test does not bleed into the next test (where the registration UI only renders
// while no domain is set). The Playwright deSEC scenarios are therefore each run as a separate
// CI step with a re-seed in between (see the Playwright workflows). Other config and secrets
// are preserved.
//
// Usage: AIO_TEST_PASSWORD=... php seed-desec-mock-config.php <api_base> <update_url>

declare(strict_types=1);

$apiBase   = $argv[1] ?? '';
$updateUrl = $argv[2] ?? '';
if ($apiBase === '' || $updateUrl === '') {
    fwrite(STDERR, "usage: php seed-desec-mock-config.php <api_base> <update_url>\n");
    exit(1);
}

$password = getenv('AIO_TEST_PASSWORD');
if ($password === false || $password === '') {
    fwrite(STDERR, "AIO_TEST_PASSWORD env var must be set to the master password to seed\n");
    exit(1);
}

$file = '/mnt/docker-aio-config/data/configuration.json';
$config = is_file($file) ? json_decode((string)file_get_contents($file), true) : [];
if (!is_array($config)) {
    $config = [];
}
$config['desec_api_base']   = $apiBase;
$config['desec_update_url'] = $updateUrl;
$config['password']         = $password;

// Reset any deSEC state from a previous test run so the registration UI renders again.
unset($config['domain'], $config['desec_email']);
if (isset($config['secrets']) && is_array($config['secrets'])) {
    unset($config['secrets']['DESEC_TOKEN'], $config['secrets']['DESEC_PASSWORD']);
}

file_put_contents($file, json_encode($config, JSON_PRETTY_PRINT));
echo "Seeded deSEC mock config into $file\n";
