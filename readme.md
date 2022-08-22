# Nextcloud All In One
Nextcloud AIO stands for Nextcloud All In One and provides easy deployment and maintenance with most features included in this one Nextcloud instance. 

Included are:
- Nextcloud
- Nextcloud Office
- High performance backend for Nextcloud Files
- High performance backend for Nextcloud Talk
- Backup solution (based on [BorgBackup](https://github.com/borgbackup/borg#what-is-borgbackup))
- Imaginary
- ClamAV

## How to use this?
The following instructions are especially meant for Linux. For macOS see [this](#how-to-run-aio-on-macos), for Windows see [this](#how-to-run-aio-on-windows).
1. Install Docker on your Linux installation using:
    ```
    curl -fsSL get.docker.com | sudo sh
    ```
1. If you need ipv6 support, you should enable it by following https://docs.docker.com/config/daemon/ipv6/.
2. Run the command below in order to start the container:<br><br>
    (For people that cannot use ports 80 and/or 443 on this server, please follow the [reverse proxy documentation](https://github.com/nextcloud/all-in-one/blob/main/reverse-proxy.md) because port 443 is used by this project and opened on the host by default even though it does not look like this is the case. Otherwise please run the command below!)
    ```
    # For x64 CPUs:
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
    <details>
    <summary>Command for arm64 CPUs like the Raspberry Pi 4</summary>

    ```
    # For arm64 CPUs:
    sudo docker run -it \
    --name nextcloud-aio-mastercontainer \
    --restart always \
    -p 80:80 \
    -p 8080:8080 \
    -p 8443:8443 \
    --volume nextcloud_aio_mastercontainer:/mnt/docker-aio-config \
    --volume /var/run/docker.sock:/var/run/docker.sock:ro \
    nextcloud/all-in-one:latest-arm64
    ```

    </details>

3. After the initial startup, you should be able to open the Nextcloud AIO Interface now on port 8080 of this server.<br>
E.g. `https://ip.address.of.this.server:8080`<br><br>
If your firewall/router has port 80 and 8443 open and you point a domain to your server, you can get a valid certificate automatically by opening the Nextcloud AIO Interface via:<br>
`https://your-domain-that-points-to-this-server.tld:8443`
4. Please do not forget to open port `3478/TCP` and `3478/UDP` in your firewall/router for the Talk container!

## FAQ
### How does it work?
Nextcloud AIO is inspired by projects like Portainer that manage the docker daemon by talking to it through the docker socket directly. This concept allows a user to install only one container with a single command that does the heavy lifting of creating and managing all containers that are needed in order to provide a Nextcloud installation with most features included. It also makes updating a breeze and is not bound to the host system (and its slow updates) anymore as everything is in containers. Additionally, it is very easy to handle from a user perspective because a simple interface for managing your Nextcloud AIO installation is provided.

### Are reverse proxies supported?
Yes. Please refer to the following documentation on this: [reverse-proxy.md](https://github.com/nextcloud/all-in-one/blob/main/reverse-proxy.md)

### Which ports are mandatory to be open in your firewall/router?
Only those (if you access the Mastercontainer Interface internally via port 8080):
- `443/TCP` for the Apache container
- `3478/TCP` and `3478/UDP` for the Talk container

### Explanation of used ports:
- `8080/TCP`: Mastercontainer Interface with self-signed certificate (works always, also if only access via IP-address is possible, e.g. `https://ip.address.of.this.server:8080/`)
- `80/TCP`: redirects to Nextcloud (is used for getting the certificate via ACME http-challenge for the Mastercontainer)
- `8443/TCP`: Mastercontainer Interface with valid certificate (only works if port 80 and 8443 are open in your firewall/router and you point a domain to your server. It generates a valid certificate then automatically and access via e.g. `https://public.domain.com:8443/` is possible.)
- `443/TCP`: will be used by the Apache container later on and needs to be open in your firewall/router
- `3478/TCP` and `3478/UDP`: will be used by the Turnserver inside the Talk container and needs to be open in your firewall/router

### How to run AIO on macOS?
On macOS, there are two things different in comparison to Linux: instead of using `--volume /var/run/docker.sock:/var/run/docker.sock:ro`, you need to use `--volume /var/run/docker.sock.raw:/var/run/docker.sock:ro` to run it after you installed [Docker Desktop](https://www.docker.com/products/docker-desktop/). You also need to add `-e DOCKER_SOCKET_PATH="/var/run/docker.sock.raw"`to the startup command. Apart from that it should work and behave the same like on Linux.

### How to run AIO on Windows?
On Windows, the following command should work in the command prompt after you installed [Docker Desktop](https://www.docker.com/products/docker-desktop/):

```
docker run -it ^
--name nextcloud-aio-mastercontainer ^
--restart always ^
-p 80:80 ^
-p 8080:8080 ^
-p 8443:8443 ^
--volume nextcloud_aio_mastercontainer:/mnt/docker-aio-config ^
--volume //var/run/docker.sock:/var/run/docker.sock:ro ^
nextcloud/all-in-one:latest
```

**Please note:** In order to make the built-in backup solution able to back up to the host system, you need to create a volume with the name `nextcloud_aio_backupdir` beforehand:
```
docker volume create ^
--driver local ^
--name nextcloud_aio_backupdir ^
-o device="/host_mnt/c/your/backup/path" ^
-o type="none" ^
-o o="bind"
```
(The value `/host_mnt/c/your/backup/path` in this example would be equivalent to `C:\your\backup\path` on the Windows host. So you need to translate the path that you want to use into the correct format.) ⚠️️ **Attention**: Make sure that the path exists on the host before you create the volume! Otherwise everything will bug out!

Also, you may be interested in adjusting Nextcloud's Datadir to store the files on the host system. See [this documentation](https://github.com/nextcloud/all-in-one#how-to-change-the-default-location-of-nextclouds-datadir) on how to do it.

### How to run AIO with Portainer?
The easiest way to run it with Portainer on Linux is to use Portainer's stacks feature and use [this docker-compose file](./docker-compose.yml) in order to start AIO correctly. 

### How to run Nextcloud behind a Cloudflare Argo Tunnel?
Although it does not seems like it is the case but from AIO perspective a Cloudflare Argo Tunnel works like a reverse proxy. So please follow the [reverse proxy documentation](./reverse-proxy.md) where is documented how to make it run behind a Cloudflare Argo Tunnel.

### How to get Nextcloud running using the ACME DNS-challenge?
You can install AIO in reverse proxy mode where is also documented how to get it running using the ACME DNS-challenge for getting a valid certificate for AIO. See the [reverse proxy documentation](./reverse-proxy.md). (Meant is the `Caddy with ACME DNS-challenge` section).

### How to run Nextcloud locally?
If you do not want to open Nextcloud to the public internet, you may have a look at the following documentation how to set it up locally: [local-instance.md](./local-instance.md)

### Are self-signed certificates supported for Nextcloud?
No and they will not be. If you want to run it locally, without opening Nextcloud to the public internet, please have a look at the [local instance documentation](./local-instance.md).

### Can I use an ip-address for Nextcloud instead of a domain?
No and it will not be added. If you only want to run it locally, you may have a look at the following documentation: [local-instance.md](./local-instance.md)

### Are other ports than then default 443 for Nextcloud supported?
No and they will not be. Please use a dedicated domain for Nextcloud and set it up correctly by following the [reverse proxy documentation](./reverse-proxy.md). If port 443 and/or 80 is blocked for you, you may use the ACME DNS-challenge or a Cloudflare Argo Tunnel.

### Can I run Nextcloud in a subdirectory on my domain?
No and it will not be added. Please use a dedicated domain for Nextcloud and set it up correctly by following the [reverse proxy documentation](./reverse-proxy.md).

### How can I access Nextcloud locally?
The recommended way is to set up a local dns-server like a pi-hole and set up a custom dns-record for that domain that points to the internal ip-adddress of your server that runs Nextcloud AIO.

### How to skip the domain validation?
If you are completely sure that you've configured everything correctly and are not able to pass the domain validation, you may skip the domain validation by adding `-e SKIP_DOMAIN_VALIDATION=true` to the docker run command of the mastercontainer.

### How to resolve firewall problems with Fedora Linux, RHEL OS, CentOS, SUSE Linux and others?
It is known that Linux distros that use [firewalld](https://firewalld.org) as their firewall daemon have problems with docker networks. In case the containers are not able to communicate with each other, you may change your firewalld to use the iptables backend by running:
```
sudo sed -i 's/FirewallBackend=nftables/FirewallBackend=iptables/g' /etc/firewalld/firewalld.conf
sudo systemctl restart firewalld docker
```
Afterwards it should work.<br>

See https://dev.to/ozorest/fedora-32-how-to-solve-docker-internal-network-issue-22me for more details on this. This limitation is even mentioned on the official firewalld website: https://firewalld.org/#who-is-using-it

### How to run `occ` commands?
Simply run the following: `sudo docker exec -it nextcloud-aio-nextcloud php occ your-command`. Of course `your-command` needs to be exchanged with the command that you want to run.

### How to resolve `Security & setup warnings displays the "missing default phone region" after initial install`?
Simply run the following command: `sudo docker exec -it nextcloud-aio-nextcloud php occ config:system:set default_phone_region --value="yourvalue"`. Of course you need to modify `yourvalue` based on your location. Examples are `DE`, `EN` and `GB`. See this list for more codes: https://en.wikipedia.org/wiki/ISO_3166-1_alpha-2#Officially_assigned_code_elements

### How to run multiple AIO instances on one server?
See [multiple-instances.md](./multiple-instances.md) for some documentation on this.

### Bruteforce protection FAQ
Nextcloud features a built-in bruteforce protection which may get triggered and will block an ip-address or disable a user. You can unblock an ip-address by running `sudo docker exec -it nextcloud-aio-nextcloud php occ security:bruteforce:reset <ip-address>` and enable a disabled user by running `sudo docker exec -it nextcloud-aio-nextcloud php occ user:enable <name of user>`. See https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/occ_command.html#security for further information.

### Update policy
This project values stability over new features. That means that when a new major Nextcloud update gets introduced, we will wait at least until the first patch release, e.g. `24.0.1` is out before upgrading to it. Also we will wait with the upgrade until all important apps are compatible with the new major version. Minor or patch releases for Nextcloud and all dependencies as well as all containers will be updated to new versions as soon as possible but we try to give all updates first a good test round before pushing them. That means that it can take around 2 weeks before new updates reach the `latest` channel. If you want to help testing, you can switch to the `beta` channel by following [this documentation](#how-to-switch-the-channel) which will also give you the updates earlier.

### How to switch the channel?
You can switch to a different channel like e.g. the beta channel or from the beta channel back to the latest channel by stopping the mastercontainer, removing it (no data will be lost) and recreating the container using the same command that you used initially to create the mastercontainer. For the beta channel on x64 you need to change the last line `nextcloud/all-in-one:latest` to `nextcloud/all-in-one:beta` and vice versa. For arm64 it is `nextcloud/all-in-one:latest-arm64` and `nextcloud/all-in-one:beta-arm64`, respectively.

### How to update the containers?
If we push new containers to `latest`, you will see in the AIO interface below the `containers` section that new container updates were found. In this case, just press `Stop containers` and `Start containers` in order to update the containers. The mastercontainer has its own update procedure though. See below. And don't forget to back up the current state of your instance using the built-in backup solution before starting the containers again! Otherwise you won't be able to restore your instance easily if something should break during the update. 

If a new `Mastercontainer` update was found, you'll see an additional section below the `containers` section which shows that a mastercontainer update is available. If so, you can simply press on the button to update the container.

Additionally, there is a cronjob that runs once a day that checks for container and mastercontainer updates and sends a notification to all Nextcloud admins if a new update was found.

### How to easily log in to the AIO interface?
If your Nextcloud is running and you are logged in as admin in your Nextcloud, you can easily log in to the AIO interface by opening `https://yourdomain.tld/settings/admin/overview` which will show a button on top that enables you to log in to the AIO interface by just clicking on this button. **Note:** You can change the domain/ip-address/port of the button by simply stopping the containers, visiting the AIO interface from the correct and desired domain/ip-address/port and clicking once on `Start containers`.

### How to change the domain?
**⚠️ Please note:** Editing the configuration.json manually and making a mistake may break your instance so please create a backup first!

If you set up a new AIO instance, you need to enter a domain. Currently there is no way to change this domain afterwards from the AIO interface. So in order to change it, you need to edit the configuration.json manually that is most likely stored in `/var/lib/docker/volumes/nextcloud_aio_mastercontainer/_data/data/configuration.json`, subsitute each occurrence of your old domain with your new domain and save and write out the file. Afterwards restart your containers from the AIO interface and everything should work as expected if the new domain is correctly configured.<br>
If you are running AIO behind a reverse proxy, you need to obviously also change the domain in your reverse proxy config.

### How to properly reset the instance?
If something goes unexpected routes during the initial installation, you might want to reset the AIO installation to be able to start from scratch.

**Please note**: if you already have it running and have data on your instance, you should not follow these instructions as it will delete all data that is coupled to your AIO instance.

Here is how to reset the AIO instance properly:
1. Stop all containers if they are running from the AIO interface
1. Stop the mastercontainer with `sudo docker stop nextcloud-aio-mastercontainer`
1. If the domaincheck container is still running, stop it with `sudo docker stop nextcloud-aio-domaincheck`
1. Check which containers are stopped: `sudo docker ps --filter "status=exited"`
1. Now remove all these stopped containers with `sudo docker container prune`
1. Delete the docker network with `sudo docker network rm nextcloud-aio`
1. Check which volumes are dangling with `sudo docker volume ls --filter "dangling=true"`
1. Now remove all these dangling volumes: `sudo docker volume prune` (on Windows you might need to remove some volumes afterwards manually with `docker volume rm nextcloud_aio_backupdir`, `docker volume rm nextcloud_aio_nextcloud_datadir`)
1. Optional: You can remove all docker images with `sudo docker image prune -a`.
1. And you are done! Now feel free to start over with the recommended docker run command!

### Backup solution
Nextcloud AIO provides a local backup solution based on [BorgBackup](https://github.com/borgbackup/borg#what-is-borgbackup). These backups act as a local restore point in case the installation gets corrupted. 

It is recommended to create a backup before any container update. By doing this, you will be safe regarding any possible complication during updates because you will be able to restore the whole instance with basically one click. 

If you connect an external drive to your host, and choose the backup directory to be on that drive, you are also kind of safe against drive failures of the drive where the docker volumes are stored on. 

<details>
<summary>How to do the above step for step</summary>

<br>

1. Mount an external/backup HDD to the host OS using the built-in functionality or udev rules or whatever way you prefer. (E.g. follow this video: https://www.youtube.com/watch?v=2lSyX4D3v_s) and mount the drive in best case in `/mnt/backup`.
2. If not already done, fire up the docker container and set up Nextcloud as per the guide.
3. Now open the AIO interface.
4. Under backup section, add your external disk mountpoint as backup directory, e.g. `/mnt/backup`.
5. Click on `Create Backup` which should create the first backup on the external disk.

</details>

Backups can be created and restored in the AIO interface using the buttons `Create Backup` and `Restore selected backup`. Additionally, a backup check is provided that checks the integrity of your backups but it shouldn't be needed in most situations. 

The backups itself get encrypted with an encryption key that gets shown to you in the AIO interface. Please save that at a safe place as you will not be able to restore from backup without this key.

Be aware that this solution does not back up files and folders that are mounted into Nextcloud using the external storage app.

Note that this implementation does not provide remote backups, for this you can use the [backup app](https://apps.nextcloud.com/apps/backup).

---

#### Failure of the backup container in LXC containers
If you are running AIO in a LXC container, you need to make sure that FUSE is enabled in the LXC container settings. Otherwise the backup container will not be able to start as FUSE is required for it to work.

---

#### Pro-tip: Backup archives access
You can open the BorgBackup archives on your host by following these steps:<br>
(instructions for Ubuntu Desktop)
```bash
# Install borgbackup on the host
sudo apt update && sudo apt install borgbackup

# Mount the archives to /tmp/borg (if you are using the default backup location /mnt/backup/borg)
sudo mkdir -p /tmp/borg && sudo borg mount "/mnt/backup/borg" /tmp/borg

# After entering your repository key successfully, you should be able to access all archives in /tmp/borg
# You can now do whatever you want by syncing them to a different place using rsync or doing other things
# E.g. you can open the file manager on that location by running:
xhost +si:localuser:root && sudo nautilus /tmp/borg

# When you are done, simply close the file manager and run the following command to unmount the backup archives:
sudo umount /tmp/borg
```

---

#### Delete backup archives manually
You can delete BorgBackup archives on your host manually by following these steps:<br>
(instructions for Debian based OS' like Ubuntu)
```bash
# Install borgbackup on the host
sudo apt update && sudo apt install borgbackup

# List all archives (if you are using the default backup location /mnt/backup/borg)
sudo borg list "/mnt/backup/borg"

# After entering your repository key successfully, you should now see a list of all backup archives
# An example backup archive might be called 20220223_174237-nextcloud-aio
# Then you can simply delete the archive with:
sudo borg delete --stats --progress "/mnt/backup/borg::20220223_174237-nextcloud-aio"
```

After doing so, make sure to update the backup archives list in the AIO interface!<br>
You can do so by clicking on the `Check backup integrity` button or `Create backup` button.

---

#### Sync the backup regularly to another drive
For increased backup security, you might consider syncing the backup repository regularly to another drive.

To do that, first add the drive to `/etc/fstab` so that it is able to get automatically mounted and then create a script that does all the things automatically. Here is an example for such a script:

<details>
<summary>Click here to expand</summary>

```bash
#!/bin/bash

# Please modify all variables below to your needings:
SOURCE_DIRECTORY="/mnt/backup/borg"
DRIVE_MOUNTPOINT="/mnt/backup-drive"
TARGET_DIRECTORY="/mnt/backup-drive/borg"

########################################
# Please do NOT modify anything below! #
########################################

if [ "$EUID" -ne 0 ]; then 
    echo "Please run as root"
    exit 1
fi

if ! [ -d "$SOURCE_DIRECTORY" ]; then
    echo "The source directory does not exist."
    exit 1
fi

if [ -z "$(ls -A "$SOURCE_DIRECTORY/")" ]; then
    echo "The source directory is empty which is not allowed."
    exit 1
fi

if ! [ -d "$DRIVE_MOUNTPOINT" ]; then
    echo "The drive mountpoint must be an existing directory"
    exit 1
fi

if ! grep -q " $DRIVE_MOUNTPOINT " /etc/fstab; then
    echo "Could not find the drive mountpoint in the fstab file. Did you add it there?"
    exit 1
fi

if ! mountpoint -q "$DRIVE_MOUNTPOINT"; then
    mount "$DRIVE_MOUNTPOINT"
    if ! mountpoint -q "$DRIVE_MOUNTPOINT"; then
        echo "Could not mount the drive. Is it connected?"
        exit 1
    fi
fi

if [ -f "$SOURCE_DIRECTORY/lock.roster" ]; then
    echo "Cannot run the script as the backup archive is currently changed. Please try again later."
    exit 1
fi

mkdir -p "$TARGET_DIRECTORY"
if ! [ -d "$TARGET_DIRECTORY" ]; then
    echo "Could not create target directory"
    exit 1
fi

if [ -f "$SOURCE_DIRECTORY/aio-lockfile" ]; then
    echo "Not continuing because aio-lockfile already exists."
    exit 1
fi

touch "$SOURCE_DIRECTORY/aio-lockfile"

if ! rsync --stats --archive --human-readable --delete "$SOURCE_DIRECTORY/" "$TARGET_DIRECTORY"; then
    echo "Failed to sync the backup repository to the target directory."
    exit 1
fi

rm "$SOURCE_DIRECTORY/aio-lockfile"
rm "$TARGET_DIRECTORY/aio-lockfile"

umount "$DRIVE_MOUNTPOINT"

if docker ps --format "{{.Names}}" | grep "^nextcloud-aio-nextcloud$"; then
    docker exec -it nextcloud-aio-nextcloud bash /notify.sh "Rsync backup successful!" "Synced the backup repository successfully."
else
    echo "Synced the backup repository successfully."
fi

```

</details>

You can simply copy and past the script into a file e.g. named `backup-script.sh` e.g. here: `/root/backup-script.sh`. Do not forget to modify the variables to your requirements!

Afterwards apply the correct permissions with `sudo chown root:root /root/backup-script.sh` and `sudo chmod 700 /root/backup-script.sh`. Then you can create a cronjob that runs e.g. at `20:00` each week on Sundays like this: 
1. Open the cronjob with `sudo crontab -u root -e` (and choose your editor of choice if not already done. I'd recommend nano). 
1. Add the following new line to the crontab if not already present: `0 20 * * 7 /root/backup-script.sh` which will run the script at 20:00 on Sundays each week. 
1. save and close the crontab (when using nano are the shortcuts for this `Ctrl + o` -> `Enter` and close the editor with `Ctrl + x`).

### How to stop/start/update containers or trigger the daily backup from a script externally?
You can do so by running the `/daily-backup.sh` script that is stored in the mastercontainer. It accepts the following environmental varilables:
- `AUTOMATIC_UPDATES` if set to `1`, it will automatically stop the containers, update them and start them including the mastercontainer. If the mastercontainer gets updated, this script's execution will stop as soon as the mastercontainer gets stopped. You can then wait until it is started again and run the script with this flag again in order to update all containers correctly afterwards.
- `DAILY_BACKUP` if set to `1`, it will automatically stop the containers and create a backup. If you want to start them again afterwards, you may have a look at the `START_CONTAINERS` option. Please be aware that this option is non-blocking which means that the backup is not done when the process is finished since it only start the borgbackup container with the correct configuration.
- `START_CONTAINERS` if set to `1`, it will automatically start the containers without updating them.
- `STOP_CONTAINERS` if set to `1`, it will automatically stop the containers.

One example for this would be `sudo docker exec -it nextcloud-aio-mastercontainer DAILY_BACKUP=1 /daily-backup.sh`, which you can run via a cronjob or put it in a script.

### How to disable the backup section?
If you already have a backup solution in place, you may want to hide the backup section. You can do so by adding `-e DISABLE_BACKUP_SECTION=true` to the initial startup of the mastercontainer.

### How to change the default location of Nextcloud's Datadir?
⚠️ **Attention:** It is very important to change the datadir **before** Nextcloud is installed/started the first time and not to change it afterwards! If you still want to do it afterwards, see [this](https://github.com/nextcloud/all-in-one/discussions/890#discussioncomment-3089903) on how to do it.

You can configure the Nextcloud container to use a specific directory on your host as data directory. You can do so by adding the environmental variable `NEXTCLOUD_DATADIR` to the initial startup of the mastercontainer. Allowed values for that variable are strings that start with `/` and are not equal to `/`.

- An example for Linux is `-e NEXTCLOUD_DATADIR="/mnt/ncdata"`.
- On macOS it might be `-e NEXTCLOUD_DATADIR="/var/nextcloud-data"`
- For Synology it may be `-e NEXTCLOUD_DATADIR="/volume1/docker/nextcloud/data"`. 
- On Windows it must be `-e NEXTCLOUD_DATADIR="nextcloud_aio_nextcloud_datadir"`. In order to use this, you need to create the `nextcloud_aio_nextcloud_datadir` volume beforehand:
    ```
    docker volume create ^
    --driver local ^
    --name nextcloud_aio_nextcloud_datadir ^
    -o device="/host_mnt/c/your/data/path" ^
    -o type="none" ^
    -o o="bind"
    ```
    (The value `/host_mnt/c/your/data/path` in this example would be equivalent to `C:\your\data\path` on the Windows host. So you need to translate the path that you want to use into the correct format.) ⚠️️ **Attention**: Make sure that the path exists on the host before you create the volume! Otherwise everything will bug out!

⚠️ Please make sure to apply the correct permissions to the chosen directory before starting Nextcloud the first time (not needed on Windows). 

- In this example for Linux, the command for this would be `sudo chown -R 33:0 /mnt/ncdata` and `sudo chmod -R 750 /mnt/ncdata`. 
- On macOS, the command for this would be `sudo chown -R 33:0 /var/nextcloud-data` and `sudo chmod -R 750 /var/nextcloud-data`.
- For Synology, the command for this example would be `sudo chown -R 33:0 /volume1/docker/nextcloud/data` and `sudo chmod -R 750 /volume1/docker/nextcloud/data`
- On Windows, this command is not needed.

### How to allow the Nextcloud container to access directories on the host?
By default, the Nextcloud container is confined and cannot access directories on the host OS. You might want to change this when you are planning to use local external storage in Nextcloud to store some files outside the data directory and can do so by adding the environmental variable `NEXTCLOUD_MOUNT` to the initial startup of the mastercontainer. Allowed values for that variable are strings that start with `/` and are not equal to `/`.

- Two examples for Linux are `-e NEXTCLOUD_MOUNT="/mnt/"` and `-e NEXTCLOUD_MOUNT="/media/"`.
- For Synology it may be `-e NEXTCLOUD_MOUNT="/volume1/"`.
- On Windows is this option not supported.

After using this option, please make sure to apply the correct permissions to the directories that you want to use in Nextcloud. E.g. `sudo chown -R 33:0 /mnt/your-drive-mountpoint` and `sudo chmod -R 750 /mnt/your-drive-mountpoint` should make it work on Linux when you have used `-e NEXTCLOUD_MOUNT="/mnt/"`. 

You can then navigate to the apps management page, activate the external storage app, navigate to `https://your-nc-domain.com/settings/admin/externalstorages` and add a local external storage directory that will be accessible inside the container at the same place that you've entered. E.g. `/mnt/your-drive-mountpoint` will be mounted to `/mnt/your-drive-mountpoint` inside the container, etc. 

Be aware though that these locations will not be covered by the built-in backup solution!

### How to adjust the Talk port?
By default will the talk container use port `3478/UDP` and `3478/TCP` for connections. You can adjust the port by adding e.g. `-e TALK_PORT=3478` to the initial docker run command and adjusting the port to your desired value.

### How to adjust the upload limit for Nextcloud?
By default are uploads to Nextcloud limited to a max of 10G. You can adjust the upload limit by providing `-e NEXTCLOUD_UPLOAD_LIMIT=10G` to the docker run command of the mastercontainer and customize the value to your fitting. It must start with a number and end with `G` e.g. `10G`.

### How to adjust the max execution time for Nextcloud?
By default are uploads to Nextcloud limited to a max of 3600s. You can adjust the upload time limit by providing `-e NEXTCLOUD_MAX_TIME=3600` to the docker run command of the mastercontainer and customize the value to your fitting. It must be a number e.g. `3600`.

### What can I do to fix the internal or reserved ip-address error?
If you get an error during the domain validation which states that your ip-address is an internal or reserved ip-address, you can fix this by first making sure that your domain indeed has the correct public ip-address that points to the server and then adding `--add-host yourdomain.com:<public-ip-address>` to the initial docker run command which will allow the domain validation to work correctly. And so that you know: even if the `A` record of your domain should change over time, this is no problem since the mastercontainer will not make any attempt to access the chosen domain after the initial domain validation.

### How to run this with docker rootless?
You can run AIO also with docker rootless. How to do this is documented here: [docker-rootless.md](https://github.com/nextcloud/all-in-one/blob/main/docker-rootless.md)

### Huge docker logs
When your containers run for a few days without a restart, the container logs that you can view from the AIO interface can get really huge. You can limit the loge sizes by enabling logrotate for docker container logs. Feel free to enable this by following those instructions: https://sandro-keil.de/blog/logrotate-for-docker-container/

### Access/Edit Nextcloud files/folders manually
The files and folders that you add to Nextcloud are by default stored in the following directory: `/var/lib/docker/volumes/nextcloud_aio_nextcloud_data/_data/` on the host. If needed, you can modify/add/delete files/folders there but **ATTENTION**: be very careful when doing so because you might corrupt your AIO installation! Best is to create a backup using the built-in backup solution before editing/changing files/folders in there because you will then be able to restore your instance to the backed up state.

After you are done modifying/adding/deleting files/folders, don't forget to apply the correct permissions by running: `sudo chown -R 33:0 /var/lib/docker/volumes/nextcloud_aio_nextcloud_data/_data/*` and `sudo chmod -R 750 /var/lib/docker/volumes/nextcloud_aio_nextcloud_data/_data/*` and rescan the files with `sudo docker exec -it nextcloud-aio-nextcloud php occ files:scan --all`.

### How to store the files/installation on a separate drive?
You can move the whole docker library and all its files including all Nextcloud AIO files and folders to a separate drive by first mounting the drive in the host OS (NTFS is not supported) and then following this tutorial: https://www.guguweb.com/2019/02/07/how-to-move-docker-data-directory-to-another-location-on-ubuntu/<br>
(Of course docker needs to be installed first for this to work.)

### How to edit Nextclouds config.php file with a texteditor?
You can edit Nextclouds config.php file directly from the host with your favorite text editor. E.g. like this: `sudo nano /var/lib/docker/volumes/nextcloud_aio_nextcloud/_data/config/config.php`. Make sure to not break the file though which might corrupt your Nextcloud instance otherwise. In best case, create a backup using the built-in backup solution before editing the file.

### Custom skeleton directory
If you want to define a custom skeleton directory, you can do so by putting your skeleton files into `/var/lib/docker/volumes/nextcloud_aio_nextcloud_data/_data/skeleton/`, applying the correct permissions with `sudo chown -R 33:0 /var/lib/docker/volumes/nextcloud_aio_nextcloud_data/_data/skeleton` and and `sudo chmod -R 750 /var/lib/docker/volumes/nextcloud_aio_nextcloud_data/_data/*` and setting the skeleton directory option with `sudo docker exec -it nextcloud-aio-nextcloud php occ config:system:set skeletondirectory --value="/mnt/ncdata/skeleton"`. You can read further on this option here: [click here](https://docs.nextcloud.com/server/stable/admin_manual/configuration_server/config_sample_php_parameters.html?highlight=skeletondir#:~:text=adding%20%3Fdirect%3D1-,'skeletondirectory',-%3D%3E%20'%2Fpath%2Fto%2Fnextcloud)

### Fail2ban
You can configure your server to block certain ip-addresses using fail2ban as bruteforce protection. Here is how to set it up: https://docs.nextcloud.com/server/stable/admin_manual/installation/harden_server.html#setup-fail2ban. The logpath of AIO is by default `/var/lib/docker/volumes/nextcloud_aio_nextcloud/_data/data/nextcloud.log`. Do not forget to add `chain=DOCKER-USER` to your nextcloud jail config (`nextcloud.local`) otherwise the nextcloud service running on docker will still be accessible even if the IP is banned. Also, you may change the blocked ports to cover all AIO ports: by default `80,443,8080,8443,3478` (see [this](https://github.com/nextcloud/all-in-one#explanation-of-used-ports))

### LDAP
It is possible to connect to an existing LDAP server. You need to make sure that the LDAP server is reachable from the Nextcloud container. Then you can enable the LDAP app and configure LDAP in Nextcloud manually. If you don't have a LDAP server yet, recommended is to use this docker container: https://hub.docker.com/r/nitnelave/lldap. Make sure here as well that Nextcloud can talk to the LDAP server. The easiest way is by adding the LDAP docker container to the docker network `nextcloud-aio`. Then you can connect to the LDAP container by its name from the Nextcloud container.

### Netdata
Netdata allows you to monitor your server using a GUI. You can install it by following https://learn.netdata.cloud/docs/agent/packaging/docker#create-a-new-netdata-agent-container.

### USER_SQL
If you want to use the user_sql app, the easiest way is to create an additional database container and add it to the docker network `nextcloud-aio`. Then the Nextcloud container should be able to talk to the database container using its name.

### phpMyAdmin, Adminer or pgAdmin
It is possible to install any of these to get a GUI for your AIO database. The pgAdmin container is recommended. You can get some docs on it here: https://www.pgadmin.org/docs/pgadmin4/latest/container_deployment.html. For the container to connect to the aio-database, you need to connect the container to the docker network `nextcloud-aio` and use `nextcloud-aio-database` as database host, `oc_nextcloud` as database username and the password that you get when running `sudo grep dbpassword /var/lib/docker/volumes/nextcloud_aio_nextcloud/_data/config/config.php` as the password. 

### Mail server
You can configure one yourself by using either of these three recommended projects: [Docker Mailserver](https://github.com/docker-mailserver/docker-mailserver/#docker-mailserver), [Maddy Mail Server](https://github.com/foxcpp/maddy#maddy-mail-server) or [Mailcow](https://github.com/mailcow/mailcow-dockerized#mailcow-dockerized-------). Docker Mailserver and Maddy Mail Server are probably a bit easier to set up as it is possible to run them using only one container but Mailcow has much more features.

### How to migrate from an already existing Nextcloud installation to Nextcloud AIO?
Please see the following documentation on this: [migration.md](https://github.com/nextcloud/all-in-one/blob/main/migration.md)

### Requirements for integrating new containers
For integrating new containers, they must pass specific requirements for being considered to get integrated in AIO itself. Even if not considered, we may add some documentation on it.

What are the requirements?
1. New containers must be related to Nextcloud. Related means that there must be a feature in Nextcloud that gets added by adding this container.
2. It must be optionally installable. Disabling and enabling the container from the AIO interface must work and must not produce any unexpected side-effects.
3. The feature that gets added into Nextcloud by adding the container must be maintained by the Nextcloud GmbH. 
4. It must be possible to run the container without big quirks inside docker containers. Big quirks means e.g. needing to change the capabilities or security options. 
5. The container should not mount directories from the host into the container: only docker volumes should be used.
