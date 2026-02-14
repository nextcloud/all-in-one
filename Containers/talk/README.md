# Nextcloud All-In-One Talk Container

## Variables

| Name | Description | Required | Default |
| ---- | ----------- | -------- | ------- |
| `NC_DOMAIN` | Your Nextcloud domain | *true* | n/a |
| `TALK_HOST` | Your talk host domain | *true* | n/a |
| `TALK_PORT` | Your talk host's STUN port | *true* | n/a |
| `TALK_TLS_PORT` | Your talk host's STUNS port | *false* | n/a |
| `TALK_TLS_CRT` | Your talk host's STUNS certificate file path (map it as a volume) | *true* if `TALK_TLS_PORT` is defined, else *false* | n/a |
| `TALK_TLS_KEY` | Your talk host's STUNS certificate key file path (map it as a volume) | *true* if `TALK_TLS_PORT` is defined, else *false* | n/a |
| `TURN_SECRET` | The turn server secret | *true* | n/a |
| `SIGNALING_SECRET` | The signaling server secret, that you'll also need to paste in Nextcloud's High Performance Backend configuration | *true* | n/a |
| `INTERNAL_SECRET` | The internal secret | *true* | n/a |
| `TALK_RELAY_MIN_PORT` | The minimum udp port range | *false* | 49152 |
| `TALK_RELAY_MAX_PORT` | The maximum udp port range | *false* | 65535 |

## Using systemd quadlets

`aio-talk` can be launched as a systemd quadlet:

```
[Unit]
Description=Nextcloud High Performance Server
After=local-fs.target

[Service]
Restart=on-abnormal

[Container]
ContainerName=nextcloud-aio-talk
Image=ghcr.io/nextcloud-releases/aio-talk:latest
Environment=NC_DOMAIN=your.nextcloud.domain
Environment=TALK_HOST=your.nextcloud.hpb.domain
Environment=TALK_PORT=3478
PublishPort=8081:8081
PublishPort=8443:8443
PublishPort=3478:3478
PublishPort=3478:3478/udp
Environment=TZ=Europe/Lisbon
# Probably safer to use a EnvironmentFile=/some/path but...
Environment=TURN_SECRET=PASTE_YOUR_TURN_SECRET_HERE
Environment=SIGNALING_SECRET=PASTE_YOUR_SIGNALING_SECRET_HERE
Environment=INTERNAL_SECRET=PASTE_YOUR_INTERNAL_SECRET_HERE

[Install]
WantedBy=multi-user.target default.target
```
