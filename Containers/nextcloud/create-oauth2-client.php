<?php

declare(strict_types=1);

/**
 * Creates a Nextcloud OAuth2 client and prints its credentials to stdout.
 *
 * Usage: php create-oauth2-client.php <name> <redirect-uri>
 *
 * Output (two lines):
 *   <client_id>
 *   <client_secret>
 *
 * Any existing client with the same name is deleted first so that the
 * operation is idempotent (stale clients whose secret has been lost are
 * replaced by a fresh one).
 */

if ($argc !== 3) {
    fwrite(STDERR, "Usage: php create-oauth2-client.php <name> <redirect-uri>\n");
    exit(1);
}

$name = $argv[1];
$redirectUri = $argv[2];

define('OC_CONSOLE', 1);
require_once '/var/www/html/lib/base.php';

\OC_App::loadApp('oauth2');

$container = \OC::getContainer();
/** @var \OCA\OAuth2\Db\ClientMapper $mapper */
$mapper = $container->get(\OCA\OAuth2\Db\ClientMapper::class);
/** @var \OCP\Security\ICrypto $crypto */
$crypto = $container->get(\OCP\Security\ICrypto::class);
/** @var \OCP\Security\ISecureRandom $random */
$random = $container->get(\OCP\Security\ISecureRandom::class);

// Delete any stale client with the same name that might have lost its secret.
foreach ($mapper->getClients() as $existing) {
    if ($existing->getName() === $name) {
        $mapper->delete($existing);
    }
}

$client = new \OCA\OAuth2\Db\Client();
$client->setName($name);
$client->setRedirectUri($redirectUri);

$secret = $random->generate(64, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789');
$client->setSecret(bin2hex($crypto->calculateHMAC($secret)));

$clientId = $random->generate(64, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789');
$client->setClientIdentifier($clientId);

$mapper->insert($client);

echo $clientId . "\n";
echo $secret . "\n";
