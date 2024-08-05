<?php
if (getenv('HTTP_PROXY') !== false) {
    $CONFIG['proxy'] = getenv('HTTP_PROXY');
}
if (getenv('HTTPS_PROXY') !== false) {
    $CONFIG['proxy'] = getenv('HTTPS_PROXY');
}
if (getenv('PROXY_USER_PASSWORD') !== false) {
    $CONFIG['proxyuserpwd'] = getenv('PROXY_USER_PASSWORD');
}
if (getenv('NO_PROXY') !== false) {
    $CONFIG['proxyexclude'] = explode(',', getenv('NO_PROXY'));
}
