<?php

$domain = $_GET['domain'] ?? '';

if (strpos($domain, '.') === false) { 
    http_response_code(400); 
} elseif (strpos($domain, '/') !== false) { 
    http_response_code(400);  
} elseif (strpos($domain, ':') !== false) { 
    http_response_code(400);  
} elseif (!filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) { 
    http_response_code(400); 
} elseif (filter_var($domain, FILTER_VALIDATE_IP)) { 
    http_response_code(400); 
} else {
    // Commented because logging is disabled as otherwise all attempts will be logged which spams the logs
    // error_log($domain . ' was accepted as valid domain.');
    http_response_code(200);
}
