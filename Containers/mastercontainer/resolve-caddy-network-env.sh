#!/bin/bash

# Resolves the IP ranges ("subnets", IPv4 and IPv6 (if present)) of AIO's docker network ("nextcloud-aio") and
# writes them to an env file that the caddy dinit services source on (re)start (see start-caddy.sh).
# Matching the whole subnet instead of a single container IP to cover IP changes if a container is restarted
# and gets a new IP from the same subnet.

NEW_AIO_SUBNETS="$(docker network inspect nextcloud-aio --format '{{range .IPAM.Config}}{{.Subnet}} {{end}}' 2>/dev/null | xargs)"

# Don't write anything if no IPs where available (too early during container start?).
if test -z "$NEW_AIO_SUBNETS"; then
    echo "$(basename "$0"): Error: No IP subnets found"
    exit 1
fi

if [ ! -f "$CADDY_ENV_FILE" ]; then
    touch "$CADDY_ENV_FILE"
else
    . "$CADDY_ENV_FILE"
    if test "$AIO_SUBNETS" = "$NEW_AIO_SUBNETS"; then
        # Same subnets, nothing to do.
        exit 0
    fi
fi

# Write atomically (via rename) so a concurrent source never reads a partial file.
printf '%s\n' "export AIO_SUBNETS='$NEW_AIO_SUBNETS'" > "$CADDY_ENV_FILE.tmp"
chmod 644 "$CADDY_ENV_FILE.tmp"
mv -f "$CADDY_ENV_FILE.tmp" "$CADDY_ENV_FILE"
echo "changed"
