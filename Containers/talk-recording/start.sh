#!/bin/bash

# Variables
if [ -z "$NC_DOMAIN" ]; then
    echo "You need to provide the NC_DOMAIN."
    exit 1
elif [ -z "$SIGNALING_SECRET" ]; then
    echo "You need to provide the SIGNALING_SECRET."
    exit 1
elif [ -z "$RECORDING_SECRET" ]; then
    echo "You need to provide the RECORDING_SECRET."
    exit 1
fi

set -x
IPv4_ADDRESS_TALK="$(dig nextcloud-aio-talk A +short)"
set +x

# TODO: Check if using IP of signaling container is enough or if nc_domain/standalone-signaling is enough
cat << RECORDING_CONF > "/etc/recording.conf"
[logs]
level = 30

[http]
listen = 0.0.0.0:1234

[backend]
allowall = false
# Not sure if the secret is needed here if we set it in backend-id
# secret = ${RECORDING_SECRET}
backends = backend-id
skipverify = false
maxmessagesize = 1024
videowidth = 1920
videoheight = 1080
directory = /tmp

[backend-id]
url = https://${NC_DOMAIN}
secret = ${RECORDING_SECRET}
skipverify = false

[signaling]
# Not sure if the secret is needed here if we set it in signaling-id
# internalsecret = ${SIGNALING_SECRET}
signalings = signaling-id

[signaling-id]
url = https://${NC_DOMAIN}/standalone-signaling/
internalsecret = ${SIGNALING_SECRET}

[ffmpeg]
# outputaudio = -c:a libopus
# outputvideo = -c:v libvpx -deadline:v realtime -crf 10 -b:v 1M
extensionaudio = .ogg
extensionvideo = .webm
RECORDING_CONF

exec "$@"
