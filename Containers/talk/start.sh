#!/bin/bash

# Variables
if [ -z "$NC_DOMAIN" ]; then
    echo "You need to provide the NC_DOMAIN."
    exit 1
elif [ -z "$TURN_SECRET" ]; then
    echo "You need to provide the TURN_SECRET."
    exit 1
elif [ -z "$SIGNALING_SECRET" ]; then
    echo "You need to provide the SIGNALING_SECRET."
    exit 1
fi

# Turn: https://github.com/coturn/coturn/blob/master/examples/etc/turnserver.conf
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
TURN_CONF

# Janus
set -x
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
SIGNALING_CONF

exec "$@"
