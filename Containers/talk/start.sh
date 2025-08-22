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

exec "$@"
