#!/bin/bash

# Variables
if [ -z "$NC_DOMAIN" ]; then
    echo "You need to provide the NC_DOMAIN."
    exit 1
elif [ -z "$TURN_SECRET" ]; then
    echo "You need to provide the TURN_SECRET."
    exit 1
elif [ -z "$JANUS_API_KEY" ]; then
    echo "You need to provide the JANUS_API_KEY."
    exit 1
elif [ -z "$SIGNALING_SECRET" ]; then
    echo "You need to provide the SIGNALING_SECRET."
    exit 1
fi

set -x
IPv4_ADDRESS="$(dig nextcloud-aio-talk A +short)"
set +x

# Turn
cat << TURN_CONF > "/etc/turnserver.conf"
listening-port=$TALK_PORT
fingerprint
lt-cred-mech
use-auth-secret
static-auth-secret=$TURN_SECRET
realm=$NC_DOMAIN
total-quota=0
bps-capacity=0
stale-nonce
no-multicast-peers
simple-log
pidfile=/var/tmp/turnserver.pid
no-tls
no-dtls
userdb=/var/lib/turn/turndb
# Based on https://nextcloud-talk.readthedocs.io/en/latest/TURN/#turn-server-and-internal-networks
allowed-peer-ip=$IPv4_ADDRESS
denied-peer-ip=0.0.0.0-0.255.255.255
denied-peer-ip=10.0.0.0-10.255.255.255
denied-peer-ip=100.64.0.0-100.127.255.255
denied-peer-ip=127.0.0.0-127.255.255.255
denied-peer-ip=169.254.0.0-169.254.255.255
denied-peer-ip=172.16.0.0-172.31.255.255
denied-peer-ip=192.0.0.0-192.0.0.255
denied-peer-ip=192.0.2.0-192.0.2.255
denied-peer-ip=192.88.99.0-192.88.99.255
denied-peer-ip=192.168.0.0-192.168.255.255
denied-peer-ip=198.18.0.0-198.19.255.255
denied-peer-ip=198.51.100.0-198.51.100.255
denied-peer-ip=203.0.113.0-203.0.113.255
denied-peer-ip=240.0.0.0-255.255.255.255
TURN_CONF

# Janus
set -x
sed -i "s|#turn_rest_api_key.*|turn_rest_api_key = \"$JANUS_API_KEY\"|" /etc/janus/janus.jcfg
sed -i "s|#full_trickle.*|full_trickle = true|g" /etc/janus/janus.jcfg
sed -i 's|#stun_server.*|stun_server = "127.0.0.1"|g' /etc/janus/janus.jcfg
sed -i "s|#stun_port.*|stun_port = $TALK_PORT|g" /etc/janus/janus.jcfg
sed -i "s|#turn_port.*|turn_port = $TALK_PORT|g" /etc/janus/janus.jcfg
sed -i 's|#turn_server.*|turn_server = "127.0.0.1"|g' /etc/janus/janus.jcfg
sed -i 's|#turn_type .*|turn_type = "udp"|g' /etc/janus/janus.jcfg
sed -i 's|#ice_ignore_list .*|ice_ignore_list = "udp"|g' /etc/janus/janus.jcfg
sed -i 's|#interface.*|interface = "lo"|g' /etc/janus/janus.transport.websockets.jcfg
sed -i 's|#ws_interface.*|ws_interface = "lo"|g' /etc/janus/janus.transport.websockets.jcfg
sed -i 's|certfile =|#certfile =|g' /etc/janus/janus.transport.mqtt.jcfg
sed -i 's|keyfile =|#keyfile =|g' /etc/janus/janus.transport.mqtt.jcfg
set +x

# Signling
cat << SIGNALING_CONF > "/etc/signaling/server.conf"
[http]
listen = 0.0.0.0:8081

[app]
debug = false

[sessions]
hashkey = $(openssl rand -hex 16)
blockkey = $(openssl rand -hex 16)

[clients]
internalsecret = $(openssl rand -hex 16)

[backend]
backends = backend-1
allowall = false
timeout = 10
connectionsperhost = 8

[backend-1]
url = https://${NC_DOMAIN}
secret = ${SIGNALING_SECRET}

[nats]
url = nats://127.0.0.1:4222

[mcu]
type = janus
url = ws://127.0.0.1:8188

[turn]
apikey = ${JANUS_API_KEY}
secret = ${TURN_SECRET}
servers = turn:$NC_DOMAIN:$TALK_PORT?transport=tcp,turn:$NC_DOMAIN:$TALK_PORT?transport=udp
SIGNALING_CONF

exec "$@"
