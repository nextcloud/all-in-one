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
