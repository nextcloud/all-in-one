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
level = 20

[http]
listen = 0.0.0.0:1234

[backend]
allowall = false
secret = ${RECORDING_SECRET}
url = https://${NC_DOMAIN}
skipverify = false
maxmessagesize = 1024
videowidth = 1920
videoheight = 1080
directory = /tmp

[signaling]
internalsecret = ${SIGNALING_SECRET}
url = http://${IPv4_ADDRESS_TALK}:8081

[ffmpeg]
outputaudio = -c:a libopus
outputvideo = -c:v libvpx -deadline:v realtime -crf 10 -b:v 1M
extensionaudio = .ogg
extensionvideo = .webm
RECORDING_CONF

exec "$@"
