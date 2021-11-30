# Nextcloud All In One Beta
This is beta software and not production ready.
But feel free to use it at your own risk!
We expect there to be rough edges and potentially serious bugs.

Nextcloud AIO stands for Nextcloud All In One and provides easy deployment and maintenance with most features included in this one Nextcloud instance. 

Included are:
- Nextcloud
- Nextcloud Office
- High performance backend for Nextcloud Files
- High performance backend for Nextcloud Talk
- Backup solution (based on [BorgBackup](https://github.com/borgbackup/borg#what-is-borgbackup))

**Found a bug?** Please file an issue at https://github.com/nextcloud/all-in-one

## How to use this?
1. Install Docker on your Linux installations using:
```
curl -fsSL get.docker.com | sudo sh
```
2. Set a DNS host for your target IP address (e.g. `cloud.example.com`)
3. Run the following command:
```
sudo docker run -it \
--name nextcloud-aio-mastercontainer \
--restart always \
-p 80:80 \
-p 8080:8080 \
-p 8443:8443 \
--volume nextcloud_aio_mastercontainer:/mnt/docker-aio-config \
--volume /var/run/docker.sock:/var/run/docker.sock:ro \
nextcloud/all-in-one:latest
```
4. Connect to `https://hostname:8443` in your browser or `https://internal.ip.of.your.server:8080`

Explanation of used ports:

- `80`: redirects to Nextcloud (HTTP) (is used for getting the certificate via ACME http-challenge for mastercontainer)
- `8080`: Master Container Interface with self-signed certificate (HTTPS) (works always, also if only access via IP-address is possible, e.g. `https://internal.ip.address:8080/`)
- `8443`: Master Container Interface with valid automatic certificate via Let's Encrypt! (HTTPS) (Only works if you access the container via a public domain, e.g. `https://public.domain.com:8443/` and not via IP-address.)
