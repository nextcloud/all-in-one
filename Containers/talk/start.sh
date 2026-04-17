#!/bin/bash

# Variables
if [ -z "$NC_DOMAIN" ]; then
    echo "You need to provide the NC_DOMAIN."
    exit 1
elif [ -z "$TALK_PORT" ]; then
    echo "You need to provide the TALK_PORT."
    exit 1
elif [ -z "$TURN_SECRET" ]; then
    echo "You need to provide the TURN_SECRET."
    exit 1
elif [ -z "$SIGNALING_SECRET" ]; then
    echo "You need to provide the SIGNALING_SECRET."
    exit 1
elif [ -z "$INTERNAL_SECRET" ]; then
    echo "You need to provide the INTERNAL_SECRET."
    exit 1
fi

# Trust additional CA certificates, if the user provided NEXTCLOUD_TRUSTED_CACERTS_DIR
# The container is read-only, so we build a custom bundle in /tmp (tmpfs) and
# point Go's TLS stack to it via SSL_CERT_FILE.
if mountpoint -q /usr/local/share/ca-certificates; then
    echo "Trusting additional CA certificates..."
    set -x
    cp /etc/ssl/certs/ca-certificates.crt /tmp/ca-certificates.crt
    for cert in /usr/local/share/ca-certificates/*; do
        if [ -f "$cert" ]; then
            cat "$cert" >> /tmp/ca-certificates.crt
        fi
    done
    export SSL_CERT_FILE=/tmp/ca-certificates.crt
    set +x
fi

set -x
IPv4_ADDRESS_TALK_RELAY="$(hostname -i | grep -oP '[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+' | head -1)"
# shellcheck disable=SC2153
IPv4_ADDRESS_TALK="$(dig "$TALK_HOST" IN A +short +search | grep '^[0-9.]\+$' | sort | head -n1)"
# shellcheck disable=SC2153
IPv6_ADDRESS_TALK="$(dig "$TALK_HOST" AAAA +short +search | grep '^[0-9a-f:]\+$' | sort | head -n1)"
set +x

if [ -n "$IPv4_ADDRESS_TALK" ] && [ "$IPv4_ADDRESS_TALK_RELAY" = "$IPv4_ADDRESS_TALK" ]; then
    IPv4_ADDRESS_TALK=""
fi

set -x
IP_BINDING="::"
if grep -q "1" /sys/module/ipv6/parameters/disable \
|| grep -q "1" /proc/sys/net/ipv6/conf/all/disable_ipv6 \
|| grep -q "1" /proc/sys/net/ipv6/conf/default/disable_ipv6; then
    IP_BINDING="0.0.0.0"
fi
set +x

# Turn
cat << TURN_CONF > "/conf/eturnal.yml"
eturnal:
  listen:
    - ip: "$IP_BINDING"
      port: $TALK_PORT
      transport: udp
    - ip: "$IP_BINDING"
      port: $TALK_PORT
      transport: tcp
  log_dir: stdout
  log_level: warning
  secret: "$TURN_SECRET"
  relay_ipv4_addr: "$IPv4_ADDRESS_TALK_RELAY"
  relay_ipv6_addr: "$IPv6_ADDRESS_TALK"
  blacklist_peers:
  - recommended
  whitelist_peers:
  - 127.0.0.1
  - ::1
  - "$IPv4_ADDRESS_TALK_RELAY"
  - "$IPv4_ADDRESS_TALK"
  - "$IPv6_ADDRESS_TALK"
TURN_CONF

# Remove empty lines so that the config is not invalid
sed -i '/""/d' /conf/eturnal.yml

if [ -z "$TALK_MAX_STREAM_BITRATE" ]; then
    TALK_MAX_STREAM_BITRATE=1048576
fi

if [ -z "$TALK_MAX_SCREEN_BITRATE" ]; then
    TALK_MAX_SCREEN_BITRATE=2097152
fi

# Signling
cat << SIGNALING_CONF > "/conf/signaling.conf"
[http]
listen = 0.0.0.0:8081

[app]
debug = false

[sessions]
hashkey = $(openssl rand -hex 16)
blockkey = $(openssl rand -hex 16)

[clients]
internalsecret = ${INTERNAL_SECRET}

[backend]
backends = backend-1
allowall = false
timeout = 10
connectionsperhost = 8
skipverify = ${SKIP_CERT_VERIFY}

[backend-1]
urls = https://${NC_DOMAIN}
secret = ${SIGNALING_SECRET}
maxstreambitrate = ${TALK_MAX_STREAM_BITRATE}
maxscreenbitrate = ${TALK_MAX_SCREEN_BITRATE}

[nats]
url = nats://127.0.0.1:4222

[mcu]
type = janus
url = ws://127.0.0.1:8188
maxstreambitrate = ${TALK_MAX_STREAM_BITRATE}
maxscreenbitrate = ${TALK_MAX_SCREEN_BITRATE}
SIGNALING_CONF

# Configure Janus to use the local TURN server for its own relay candidates.
# Ephemeral TURN credentials (TURN REST API pattern):
#   username = "<expiry_unix_timestamp>:<random_hex>"  (valid for 3 months)
#   password  = base64(HMAC-SHA1(TURN_SECRET, username))
# eturnal validates both the HMAC and the embedded expiry on every Allocate,
# so a captured credential stops working after at most 3 months.
JANUS_TURN_USER="$(( $(date +%s) + 7776000 )):$(openssl rand -hex 16)"
JANUS_TURN_PWD="$(printf '%s' "$JANUS_TURN_USER" | openssl dgst -sha1 -hmac "$TURN_SECRET" -binary | openssl base64)"

if [ -z "$TURN_DOMAIN" ]; then
    TURN_DOMAIN="$NC_DOMAIN"
fi

# Build janus.jcfg: strip the entire nat block from the original and append a
# clean minimal one that points at the TURN server.
{
    sed '/^nat:/,/^}/d' /usr/local/etc/janus/janus.jcfg
    cat << NAT_CONF
nat: {
	turn_server = "$TURN_DOMAIN"
	turn_port = $TALK_PORT
	turn_type = "udp"
	turn_user = "$JANUS_TURN_USER"
	turn_pwd = "$JANUS_TURN_PWD"
    # The ice ignore list is set by janus by default, so also do this here
    ice_ignore_list = "vmnet"
}
NAT_CONF
} > /conf/janus.jcfg

exec "$@"
