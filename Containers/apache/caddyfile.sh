#!/bin/bash






function loop {
  readarray -t sorted < <(echo "$3" | tr "$2" '\n' | sort -r)
  for i in "${sorted[@]}"; do
    "template_loop_$1" "$i"
  done

}

function template_nextcloud_route() {
  cat << CADDY

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
CADDY
}



function template_loop_route {
  IFS=',' read -ra array <<< "$1"
  ROUTE="${array[0]}"
  URI_STRIP_PREFIX="${array[1]}"
  TARGET_HOST="${array[2]}"
  TARGET_PORT="${array[3]}"

  cat << CADDY

    route $(test -z "$ROUTE" || echo "$ROUTE/* "){
        $([ "$URI_STRIP_PREFIX" == "1" ] && echo "uri strip_prefix $ROUTE")
        reverse_proxy $TARGET_HOST:$TARGET_PORT
    }
CADDY
}



function template_loop_subdomain {
  IFS='|' read -ra array <<< "$1"
  SUBDOMAIN="${array[0]}"
  ROUTES="${array[1]}"

  if [ -z "$TRUSTED_DOMAINS" ] && [ -n "$SUBDOMAIN" ]; then
    # Ignore subdomains if in proxy mode
    return 0
  fi

  cat << CADDY

$(echo "$TRUSTED_DOMAINS" | tr ',' '\n' | sed "s/.*/$PROTOCOL:\/\/$SUBDOMAIN&:$APACHE_PORT/" | sed '$ ! s/$/,/') {
    header -Server
    header -X-Powered-By
$(loop route ';' "$ROUTES")
$(test -z "$SUBDOMAIN" && template_nextcloud_route)
}
CADDY
}

function template_caddyfile {
  if [ -z "$TRUSTED_DOMAINS" ]; then
      IPv4_ADDRESS="private_ranges"
      PROTOCOL="http"
  else
      IPv4_ADDRESS="$(dig "$APACHE_HOST" A +short +search | head -1 | sed 's|[0-9]\+$|0/16|')"
      PROTOCOL="https"
  fi

cat << CADDY
{
    auto_https $(test -z "$TRUSTED_DOMAINS" && echo "off" || echo "disable_redirects")

    storage file_system {
        root /mnt/data/caddy
    }

    servers {
        trusted_proxies static $IPv4_ADDRESS
    }

    log {
        level ERROR
    }
}

$(loop subdomain '@' "$CADDY_ROUTES")

CADDY
}

template_caddyfile
