<?php

$domain = $_GET['domain'] ?? '';

if (strpos($domain, '.') === false) { 
    http_response_code(400); 
} elseif (strpos($domain, '/') !== false) { 
    http_response_code(400);  
} elseif (strpos($domain, ':') !== false) { 
    http_response_code(400);  
} elseif (!filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) { 
    http_response_code(400); 
} elseif (filter_var($domain, FILTER_VALIDATE_IP)) { 
    http_response_code(400); 
} else {
    http_response_code(200);
}
