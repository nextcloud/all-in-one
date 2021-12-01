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
2. Run the following command:

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
3. After the initial startup, you should be able to open the Nextcloud AIO Interface now on port 8080 of this server.<br>
E.g. https://internal.ip.of.this.server:8080<br>
If your server has port 80 and 8443 open and you point a domain to your server, you can get a valid certificate automatially by opening the Nextcloud AIO Interface via:<br>
https://your-domain-that-points-to-this-server.tld:8443
4. You should now see your login credentials. If not, please have a look at the FAQ section below.

Explanation of used ports:

- `80`: redirects to Nextcloud (HTTP) (is used for getting the certificate via ACME http-challenge for mastercontainer)
- `8080`: Master Container Interface with self-signed certificate (HTTPS) (works always, also if only access via IP-address is possible, e.g. `https://internal.ip.address:8080/`)
- `8443`: Master Container Interface with valid automatic certificate via Let's Encrypt! (HTTPS) (Only works if you access the container via a public domain, e.g. `https://public.domain.com:8443/` and not via IP-address.)

## FAQ
- **Is running Nextcloud AIO via Docker Compose supported?**<br>
    Unfortunately no, as you most likely run into many issues when trying to do so.
- **I don't see the initial screen with my login credentials. What to do?**<br>
    Please try to remove the mastercontainer first by running:
    ```
    sudo docker stop nextcloud-aio-mastercontainer; sudo docker rm nextcloud-aio-mastercontainer; sudo docker rm nextcloud_aio_mastercontainer
    ```
    Afterwards, install it again by running the above mentioned command again.
