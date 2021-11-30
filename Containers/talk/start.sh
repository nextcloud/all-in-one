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
    echo "You need to provide the JANUS_API_KEY."
    exit 1
fi

# Turn
cat << TURN_CONF > "/etc/turnserver.conf"
listening-port=3478
fingerprint
use-auth-secret
static-auth-secret=$TURN_SECRET
realm=$NC_DOMAIN
total-quota=100
bps-capacity=0
stale-nonce
no-multicast-peers
simple-log
pidfile=/var/tmp/turnserver.pid
TURN_CONF

# Janus
sed -i "s|#turn_rest_api_key.*|turn_rest_api_key = $JANUS_API_KEY|" /etc/janus/janus.jcfg
sed -i "s|#full_trickle|full_trickle|g" /etc/janus/janus.jcfg
sed -i 's|#interface.*|interface = "lo"|g' /etc/janus/janus.transport.websockets.jcfg
sed -i 's|#ws_interface.*|ws_interface = "lo"|g' /etc/janus/janus.transport.websockets.jcfg

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
allowed = ${NC_DOMAIN}
allowall = false
secret = ${SIGNALING_SECRET}
timeout = 10
connectionsperhost = 8
[nats]
url = nats://127.0.0.1:4222
[mcu]
type = janus
url = ws://127.0.0.1:8188
[turn]
apikey = ${JANUS_API_KEY}
secret = ${TURN_SECRET}
servers = turn:$NC_DOMAIN:3478?transport=tcp,turn:$NC_DOMAIN:3478?transport=udp
SIGNALING_CONF

exec "$@"
