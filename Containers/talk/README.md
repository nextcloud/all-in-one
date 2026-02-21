# Nextcloud All-In-One Talk Container

## Variables

| Name | Description | Required | Default |
| ---- | ----------- | -------- | ------- |
| `NC_DOMAIN` | Your Nextcloud domain | *true* | n/a |
| `TALK_HOST` | Your talk host domain | *true* | n/a |
| `TALK_PORT` | Your talk host's STUN port | *false* | 3478 |
| `TALK_TLS_PORT` | Your talk host's STUNS port. It won't be activated if you don't provide a certificate and key with the following two variables. | *false* | 5349 |
| `TALK_TLS_CRT` | Your talk host's STUNS certificate file path (map it as a volume) | *true* if you want TLS activated | n/a |
| `TALK_TLS_KEY` | Your talk host's STUNS certificate key file path (map it as a volume) | *true* if you want TLS activated | n/a |
| `TALK_HTTP_PORT` | Your talk host's HTTP port | *false* | 8081 |
| `TALK_HTTP_READ_TIMEOUT` | HTTP server's read timeout. | *false* | 15 |
| `TALK_HTTP_WRITE_TIMEOUT` | HTTP server's write timeout. | *false* | 30 |
| `TALK_HTTPS_PORT` | Your talk host's HTTPS port. It won't be activated if you don't provide a certificate and key with the following two variables. | *false* | 8443 |
| `TALK_HTTPS_CRT` | Your talk host's HTTPS certificate file path (map it as a volume) | *true* if you want HTTPS activated | n/a |
| `TALK_HTTPS_KEY` | Your talk host's HTTPS certificate key file path (map it as a volume) | *true* if you want HTTPS activated | n/a |
| `TALK_HTTPS_READ_TIMEOUT` | HTTPS server's read timeout. | *false* | 15 |
| `TALK_HTTPS_WRITE_TIMEOUT` | HTTPS server's write timeout. | *false* | 30 |
| `TURN_SECRET` | The turn server secret | *true* | n/a |
| `SIGNALING_SECRET` | The signaling server secret, that you'll also need to paste in Nextcloud's High Performance Backend configuration | *true* | n/a |
| `INTERNAL_SECRET` | The internal secret | *true* | n/a |
| `TALK_RELAY_MIN_PORT` | The minimum udp port range | *false* | 49152 |
| `TALK_RELAY_MAX_PORT` | The maximum udp port range | *false* | 65535 |
