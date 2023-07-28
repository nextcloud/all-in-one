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

if [ -z "$HPB_DOMAIN" ]; then
    export HPB_DOMAIN="$NC_DOMAIN"
fi

cat << RECORDING_CONF > "/conf/recording.conf"
[logs]
# 30 means Warning
level = 30

[http]
listen = 0.0.0.0:1234

[backend]
allowall = ${ALLOW_ALL}
# TODO: remove secret below when https://github.com/nextcloud/spreed/issues/9580 is fixed
secret = ${RECORDING_SECRET}
backends = backend-1
skipverify = ${SKIP_VERIFY}
maxmessagesize = 1024
videowidth = 1920
videoheight = 1080
directory = /tmp

[backend-1]
url = ${HPB_PROTOCOL}://${NC_DOMAIN}
secret = ${RECORDING_SECRET}
skipverify = ${SKIP_VERIFY}

[signaling]
signalings = signaling-1

[signaling-1]
url = ${HPB_PROTOCOL}://${HPB_DOMAIN}${HPB_PATH}
internalsecret = ${INTERNAL_SECRET}

[ffmpeg]
# outputaudio = -c:a libopus
# outputvideo = -c:v libvpx -deadline:v realtime -crf 10 -b:v 1M
extensionaudio = .ogg
extensionvideo = .webm
RECORDING_CONF

exec "$@"
