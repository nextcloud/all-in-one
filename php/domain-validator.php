<?php
declare(strict_types=1);

$domain = '';
if (isset($_GET['domain']) && is_string($_GET['domain'])) {
    $domain = $_GET['domain'];
}

// The internal caddy instance on port 8080 accepts any hostname as it only issues self-signed
// certificates. It needs this endpoint to silence caddy's unprotected on-demand TLS warning.
if (($_SERVER['REQUEST_URI'] ?? '') === '/internal' || str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/internal?')) {
    http_response_code(200);
} elseif (!str_contains($domain, '.')) {
    http_response_code(400);
} elseif (str_contains($domain, '/')) { 
    http_response_code(400);
} elseif (str_contains($domain, ':')) { 
    http_response_code(400);
} elseif (filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) { 
    http_response_code(400);
} elseif (filter_var($domain, FILTER_VALIDATE_IP)) { 
    http_response_code(400);
} else {
    // Commented because logging is disabled as otherwise all attempts will be logged which spams the logs
    // error_log($domain . ' was accepted as valid domain.');
    http_response_code(200);
}
