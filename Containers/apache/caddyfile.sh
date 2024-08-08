#!/bin/bash


function loop {
  readarray -t sorted < <(echo "$3" | tr "$2" '\n' | sort -r)
  for i in "${sorted[@]}"; do
    "template_loop_$1" "$i"
  done

}

function template_loop_route {
  IFS=',' read -ra array <<< "$1"
  TARGET="${array[0]}"
  ROUTE="${array[1]}"
  if [ "${array[2]}" == "1" ]; then
    URI_STRIP_PREFIX="uri strip_prefix $ROUTE"
  fi

  cat << CADDY

    route $ROUTE/* {
        $URI_STRIP_PREFIX
        reverse_proxy $TARGET
    }
CADDY
}

if [ -n "$APACHE_PORT" ] && [ "$APACHE_PORT" != "443" ]; then
    TRUSTED_PROXIES="trusted_proxies static private_ranges"
    AUTO_HTTPS="auto_https off"
    TARGET="http://:$APACHE_PORT"
else
    IPv4_ADDRESS="$(dig "$APACHE_HOST" A +short +search | head -1 | sed 's|[0-9]\+$|0/16|')"
    TRUSTED_PROXIES="trusted_proxies static $IPv4_ADDRESS"
    AUTO_HTTPS="auto_https disable_redirects"
    TARGET="https://$NC_DOMAIN:443"
fi

if [ -n "$ADDITIONAL_TRUSTED_DOMAIN" ]; then
    ADDITIONAL_TARGET="https://$ADDITIONAL_TRUSTED_DOMAIN:443"
fi

cat << CADDY
{
    $AUTO_HTTPS

    storage file_system {
        root /mnt/data/caddy
    }

    servers {
        $TRUSTED_PROXIES
    }

    log {
        level ERROR
    }
}

$ADDITIONAL_TARGET
$TARGET {
    header -Server
    header -X-Powered-By
$(loop route ';' "$CADDY_ROUTES")
    route {
        header Strict-Transport-Security max-age=31536000;
        reverse_proxy localhost:8000
    }
    redir /.well-known/carddav /remote.php/dav/ 301
    redir /.well-known/caldav /remote.php/dav/ 301

    tls {
        issuer acme {
            disable_http_challenge
        }
    }
}

CADDY
