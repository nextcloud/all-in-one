<?php
if (getenv('HTTP_PROXY')) {
    $CONFIG['proxy'] = getenv('HTTP_PROXY');
}
if (getenv('HTTPS_PROXY')) {
    $CONFIG['proxy'] = getenv('HTTPS_PROXY');
}
if (getenv('PROXY_USER_PASSWORD')) {
    $CONFIG['proxyuserpwd'] = getenv('PROXY_USER_PASSWORD');
}
if (getenv('NO_PROXY')) {
    $CONFIG['proxyexclude'] = explode(',', getenv('NO_PROXY'));
}
