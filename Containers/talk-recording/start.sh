#!/bin/bash

# Variables
if [ -z "$NC_DOMAIN" ]; then
    echo "You need to provide the NC_DOMAIN."
    exit 1
elif [ -z "$RECORDING_SECRET" ]; then
    echo "You need to provide the RECORDING_SECRET."
    exit 1
elif [ -z "$INTERNAL_SECRET" ]; then
    echo "You need to provide the INTERNAL_SECRET."
    exit 1
fi

cat << RECORDING_CONF > "/etc/recording.conf"
[logs]
# 30 means Warning
level = 30

[http]
listen = 0.0.0.0:1234

[backend]
allowall = false
# TODO: remove secret below when https://github.com/nextcloud/spreed/issues/9580 is fixed
secret = ${RECORDING_SECRET}
backends = backend-1
skipverify = false
maxmessagesize = 1024
videowidth = 1920
videoheight = 1080
directory = /tmp

[backend-1]
url = https://${NC_DOMAIN}
secret = ${RECORDING_SECRET}
skipverify = false

[signaling]
signalings = signaling-1

[signaling-1]
url = https://${NC_DOMAIN}/standalone-signaling/
internalsecret = ${INTERNAL_SECRET}

[ffmpeg]
# outputaudio = -c:a libopus
# outputvideo = -c:v libvpx -deadline:v realtime -crf 10 -b:v 1M
extensionaudio = .ogg
extensionvideo = .webm
RECORDING_CONF

exec "$@"
