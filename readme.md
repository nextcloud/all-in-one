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
2. Make sure to pull the latest image:

```
sudo docker pull nextcloud/all-in-one:latest
```
3. Run the following command in order to start the container:

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
4. After the initial startup, you should be able to open the Nextcloud AIO Interface now on port 8080 of this server.<br>
E.g. https://internal.ip.of.this.server:8080<br>
If your server has port 80 and 8443 open and you point a domain to your server, you can get a valid certificate automatially by opening the Nextcloud AIO Interface via:<br>
https://your-domain-that-points-to-this-server.tld:8443

## FAQ
### Which ports are mandatory to be open?
Only those (if you acces the Mastercontainer Interface internally via port 8080):
- `443/TCP` for the Nextcloud container
- `3478/TCP` and `3478/UPD` for the Talk container

### Explanation of used ports:
- `8080/TCP`: Mastercontainer Interface with self-signed certificate (works always, also if only access via IP-address is possible, e.g. `https://internal.ip.address:8080/`)
- `80/TCP`: redirects to Nextcloud (is used for getting the certificate via ACME http-challenge for the Mastercontainer)
- `8443/TCP`: Mastercontainer Interface with valid certificate (only works if port 80 and 8443 are open and you point a domain to your server. It generates a valid certificate then automatically and access via e.g. `https://public.domain.com:8443/` is possible.)
- `443/TCP`: will be used by the Nextcloud container later on and needs to be open
- `3478/TCP` and `3478/UPD`: will be used by the Turnserver inside the Talk container and needs to be open

### Backup solution
Nextcloud AIO provides a local backup solution based on BorgBackup. These backups act as a local restore point in case the installation gets corrupted. 

It is recommended to create a backup before any container update. By doing this, you will be safe regarding any possible complication during updates because you will be able to restore the whole instance with basically one click. 

If you connect an external drive to your host, and choose the backup directory to be on that drive, you are also kind of save against drive failures of the drive where the docker volumes are stored on. 

Backups can be created and restored in the AIO interface using the buttons `Create Backup` and `Restore last backup`. Additionally, a backup check is provided that checks the integrity of your backups but it shouldn't be needed in most situations. 

The backups itself get encrypted with an encryption key that gets shown to you in the AIO interface. Please save that at a safe place as you will not be able to restore from backup without this key.

Note that this implementation does not provide remote backups, for this you can use the [backup app](https://apps.nextcloud.com/apps/backup). 
