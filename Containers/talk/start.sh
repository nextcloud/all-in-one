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
lt-cred-mech
use-auth-secret
static-auth-secret=$TURN_SECRET
realm=$NC_DOMAIN
total-quota=100
bps-capacity=0
stale-nonce
no-loopback-peers
no-multicast-peers
simple-log
pidfile=/var/tmp/turnserver.pid
TURN_CONF

# Janus
set -x
sed -i "s|#turn_rest_api_key.*|turn_rest_api_key = $JANUS_API_KEY|" /etc/janus/janus.jcfg
sed -i "s|#full_trickle.*|full_trickle = true|g" /etc/janus/janus.jcfg
sed -i "s|#stun_server.*|stun_server = '127.0.0.1'|g" /etc/janus/janus.jcfg
sed -i "s|#stun_port.*|stun_port = 3478|g" /etc/janus/janus.jcfg
sed -i "s|#turn_port.*|turn_port = 3478|g" /etc/janus/janus.jcfg
sed -i "s|#turn_server.*|turn_server = '127.0.0.1'|g" /etc/janus/janus.jcfg
sed -i "s|#turn_type .*|turn_type = 'udp'|g" /etc/janus/janus.jcfg
sed -i "s|cert_pem|#cert_pem|g" /etc/janus/janus.jcfg
sed -i "s|cert_key|#cert_key|g" /etc/janus/janus.jcfg
sed -i "s|cert_pwd|#cert_pwd|g" /etc/janus/janus.jcfg
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
servers = turn:$NC_DOMAIN:3478?transport=tcp,turn:$NC_DOMAIN:3478?transport=udp
SIGNALING_CONF

exec "$@"
