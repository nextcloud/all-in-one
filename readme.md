# Nextcloud All In One
Nextcloud AIO stands for Nextcloud All In One and provides easy deployment and maintenance with most features included in this one Nextcloud instance. 

Included are:
- Nextcloud
- Nextcloud Office
- High performance backend for Nextcloud Files
- High performance backend for Nextcloud Talk
- Backup solution (based on [BorgBackup](https://github.com/borgbackup/borg#what-is-borgbackup))

**Found a bug?** Please file an issue at https://github.com/nextcloud/all-in-one

## How to use this?
The following instructions are especially meant for Linux. For macOS see [this](#how-to-run-it-on-macos), for Windows see [this](#how-to-run-it-on-windows).
1. Install Docker on your Linux installation using:
    ```
    curl -fsSL get.docker.com | sudo sh
    ```

2. Run the following command in order to start the container:<br>
    (For people that cannot use ports 80 and/or 443 on this server, please follow [this documentation](https://github.com/nextcloud/all-in-one/blob/main/reverse-proxy.md). Otherwise please run the command below!)
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
E.g. `https://internal.ip.of.this.server:8080`<br>
If your firewall/router has port 80 and 8443 open and you point a domain to your server, you can get a valid certificate automatially by opening the Nextcloud AIO Interface via:<br>
`https://your-domain-that-points-to-this-server.tld:8443`
4. Please do not forget to open port `3478/TCP` and `3478/UDP` in your firewall/router for the Talk container!

## FAQ
### How does it work?
Nextcloud AIO is inspired by projects like Portainer that allow to manage the docker daemon by talking to the docker socket directly. This concept allows to install only one container with a single command that does the heavy lifting of creating and managing all containers that are needed in order to provide a Nextcloud installation with most features included. It also makes updating a breeze and is not bound to the host system (and its slow updates) anymore as everything is in containers. Additionally, it is very easy to handle from a user perspective because a simple interface for managing your Nextcloud AIO installation is provided.

### Are reverse proxies supported?
Yes. Please refer to the following documentation on this: [reverse-proxy.md](https://github.com/nextcloud/all-in-one/blob/main/reverse-proxy.md)

### Which ports are mandatory to be open in your firewall/router?
Only those (if you acces the Mastercontainer Interface internally via port 8080):
- `443/TCP` for the Apache container
- `3478/TCP` and `3478/UDP` for the Talk container

### Explanation of used ports:
- `8080/TCP`: Mastercontainer Interface with self-signed certificate (works always, also if only access via IP-address is possible, e.g. `https://internal.ip.address:8080/`)
- `80/TCP`: redirects to Nextcloud (is used for getting the certificate via ACME http-challenge for the Mastercontainer)
- `8443/TCP`: Mastercontainer Interface with valid certificate (only works if port 80 and 8443 are open in your firewall/router and you point a domain to your server. It generates a valid certificate then automatically and access via e.g. `https://public.domain.com:8443/` is possible.)
- `443/TCP`: will be used by the Apache container later on and needs to be open in your firewall/router
- `3478/TCP` and `3478/UDP`: will be used by the Turnserver inside the Talk container and needs to be open in your firewall/router

### How to run it on macOS?
On macOS, there is one specialty in comparison to Linux: instead of using `--volume /var/run/docker.sock:/var/run/docker.sock:ro`, you need to use `--volume /var/run/docker.sock.raw:/var/run/docker.sock:ro` to run it after you installed [Docker Desktop](https://www.docker.com/products/docker-desktop/). Apart from that it should work and behave the same like on Linux.

### How to run it on Windows?
On Windows, the following command should work after you installed [Docker Desktop](https://www.docker.com/products/docker-desktop/):
<details>
<summary>Click here to show it</summary>

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

</details>

### How to run `occ` commands?
Simply run the following: `sudo docker exec -it nextcloud-aio-nextcloud php occ your-command`. Of course `your-command` needs to be exchanged with the command that you want to run.

### How to resolve `Security & setup warnings displays the "missing default phone region" after initial install`?
Simply run the following command: `sudo docker exec -it nextcloud-aio-nextcloud php occ config:system:set default_phone_region --value="yourvalue"`. Of course you need to modify `yourvalue` based on your location. Examples are `DE`, `EN` and `GB`. See this list for more codes: https://en.wikipedia.org/wiki/ISO_3166-1_alpha-2#Officially_assigned_code_elements

### How to switch the channel?
You can switch to a different channel like e.g. the beta channel or from the beta channel back to the latest channel by stopping the mastercontainer, removing it (no data will be lost) and recreating the container using the same command that you used initially to create the mastercontainer. For the beta channel on x64 you need to change the last line `nextcloud/all-in-one:latest` to `nextcloud/all-in-one:beta` and vice versa. For arm64 it is `nextcloud/all-in-one:latest-arm64` and `nextcloud/all-in-one:beta-arm64`, respectively.

### How to update the containers?
If we push new containers to `latest`, you will see in the AIO interface below the `containers` section that new container updates were found. In this case, just press `Stop containers` and `Start containers` in order to update the containers. The mastercontainer has its own update procedure though. See below. And don't forget to back up the current state of your instance using the built-in backup solution before starting the containers again! Otherwise you won't be able to restore your instance easily if something should break during the update. 

If a new `Mastercontainer` update was found, you'll see an additional section below the `containers` section which shows that a mastercontainer update is available. If so, you can simply press on the button to update the container.

Additionally, there is a cronjob that runs once a day that checks for container and mastercontainer updates and sends a notification to all Nextcloud admins if a new update was found.

### How to easily log in to the AIO interface?
If your Nextcloud is running and you are logged in as admin in your Nextcloud, you can easily log in to the AIO interface by opening `https://yourdomain.tld/settings/admin/overview` which will show a button on top that enables you to log in to the AIO interface by just clicking on this button. **Note:** You can change the domain/ip-address/port of the button by simply stopping the containers, visiting the AIO interface from the correct and desired domain/ip-address/port and clicking once on `Start containers`.

### Backup solution
Nextcloud AIO provides a local backup solution based on [BorgBackup](https://github.com/borgbackup/borg#what-is-borgbackup). These backups act as a local restore point in case the installation gets corrupted. 

It is recommended to create a backup before any container update. By doing this, you will be safe regarding any possible complication during updates because you will be able to restore the whole instance with basically one click. 

If you connect an external drive to your host, and choose the backup directory to be on that drive, you are also kind of save against drive failures of the drive where the docker volumes are stored on. 

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

if ! rsync --stats --archive --human-readable --delete "$SOURCE_DIRECTORY/" "$TARGET_DIRECTORY"; then
    echo "Failed to sync the backup repository to the target directory."
    exit 1
fi

umount "$DRIVE_MOUNTPOINT"

if docker ps --format "{{.Names}}" | grep "^nextcloud-aio-nextcloud$"; then
    docker exec -it nextcloud-aio-nextcloud bash /notify.sh "Rsync backup successful!" "Synced the backup repository successfully."
else
    echo "Synced the backup repository successfully."
fi

```

</details>

You can simply copy and past the script into a file e.g. named `backup-script.sh` e.g. here: `/root/backup-script.sh`. Do not forget to modify the variables to your needings though!

Afterwards apply the correct permissions with `sudo chown root:root /root/backup-script.sh` and `sudo chmod 700 /root/backup-script.sh`. Then you can create a cronjob that runs e.g. at `20:00` each week on sundays like this: `crontab -u root -l | { cat; echo "0 20 * * 7 /root/backup-script.sh"; } | crontab -u root -`. Make sure that it does not collidate with the daily backups from AIO (if configured) since the target backup repository might get into an inconsistent state. (There is no check in place that checks this.)

### How to change the default location of Nextcloud's Datadir?
You can configure the Nextcloud container to use a specific directory on your host as data directory. You can do so by adding the environmental variable `NEXTCLOUD_DATADIR` to the initial startup of the mastercontainer. Allowed values for that variable are strings that start with `/mnt/`, `/media/` or `/host_mnt/`. An example for Linux and macOS is `-e NEXTCLOUD_DATADIR="/mnt/ncdata"`. On Windows it might be `-e NEXTCLOUD_DATADIR="/host_mnt/c/your/data/path"` (This Windows example would be equivalent to `C:\your\data\path` on the Windows host. So you need to translate the path that you want to use into the correct format.) Please make sure to apply the correct permissions to the chosen directory before starting Nextcloud the first time (not needed on Windows). In this example would the command for this be: `sudo chown -R 33:0 /mnt/ncdata`. ⚠ **Attention:** It is very important to change the datadir **before** Nextcloud is installed/started the first time and not to change it afterwards!

### How to allow the Nextcloud container to access directories on the host?
By default, the Nextcloud container is confined and cannot access directories on the host OS. You might want to change this when you are planning to use local external storage in Nextcloud to store some files outside the data directory and can do so by adding the environmental variable `NEXTCLOUD_MOUNT` to the initial startup of the mastercontainer. Allowed values for that variable are strings that are equal to or start with `/mnt/`, `/media/` or `/host_mnt/` or are equal to `/var/backups` and unequal to `/mnt/ncdata`. Two examples for Linux and macOS are: `-e NEXTCLOUD_MOUNT="/mnt/"` or `-e NEXTCLOUD_MOUNT="/media/"`. On Windows it might be `-e NEXTCLOUD_DATADIR="/host_mnt/c"` (This Windows example would be equivalent to `C:\` on the Windows host. So you need to translate the path that you want to use into the correct format.) After using this option, please make sure to apply the correct permissions to the directories that you want to use in Nextcloud (not needed on Windows). E.g. `sudo chown -R 33:0 /mnt/your-drive-mountpoint` should make it work. You can then navigate to the apps management page, activate the external storage app, navigate to `https://your-nc-domain.com/settings/admin/externalstorages` and add a local external storage directory that will be accessible inside the container at the same place that you've entered. E.g. `/mnt/your-drive-mountpoint` will be mounted to `/mnt/your-drive-mountpoint` inside the container, etc. Be aware though that these locations will not be covered by the built-in backup solution!

### Huge docker logs
When your containers run for a few days without a restart, the container logs that you can view from the AIO interface can get really huge. You can limit the loge sizes by enabling logrotate for docker container logs. Feel free to enable this by following those instructions: https://sandro-keil.de/blog/logrotate-for-docker-container/

### Access/Edit Nextcloud files/folders manually
The files and folders that you add to Nextcloud are by default stored in the following directory: `/var/lib/docker/volumes/nextcloud_aio_nextcloud_data/_data/` on the host. If needed, you can modify/add/delete files/folders there but **ATTENTION**: be very careful when doing so because you might corrupt your AIO installation! Best is to create a backup using the built-in backup solution before editing/changing files/folders in there because you will then be able to restore your instance to the backed up state.

After you are done modifying/adding/deleting files/folders, don't forget to apply the correct permissions by running: `sudo chown -R 33:0 /var/lib/docker/volumes/nextcloud_aio_nextcloud_data/_data/*` and rescan the files with `sudo docker exec -it nextcloud-aio-nextcloud php occ files:scan --all`.

### How to store the files/installation on a separate drive?
You can move the whole docker library and all its files including all Nextcloud AIO files and folders to a separate drive by first mounting the drive in the host OS (NTFS is not supported) and then following this tutorial: https://www.guguweb.com/2019/02/07/how-to-move-docker-data-directory-to-another-location-on-ubuntu/<br>
(Of course docker needs to be installed first for this to work.)

### How to edit Nextclouds config.php file with a texteditor?
You can edit Nextclouds config.php file directly from the host with your favorite text editor. E.g. like this: `sudo nano /var/lib/docker/volumes/nextcloud_aio_nextcloud/_data/config/config.php`. Make sure to not break the file though which might corrupt your Nextcloud instance otherwise. In best case, create a backup using the built-in backup solution before editing the file.

### Custom skeleton directory
If you want to define a custom skeleton directory, you can do so by putting your skeleton files into `/var/lib/docker/volumes/nextcloud_aio_nextcloud_data/_data/skeleton/`, applying the correct permissions with `sudo chown -R 33:0 /var/lib/docker/volumes/nextcloud_aio_nextcloud_data/_data/skeleton` and setting the skeleton directory option with `sudo docker exec -it nextcloud-aio-nextcloud php occ config:system:set skeletondirectory --value="/mnt/ncdata/skeleton"`. You can read further on this option here: [click here](https://docs.nextcloud.com/server/stable/admin_manual/configuration_server/config_sample_php_parameters.html?highlight=skeletondir#:~:text=adding%20%3Fdirect%3D1-,'skeletondirectory',-%3D%3E%20'%2Fpath%2Fto%2Fnextcloud)

### LDAP
It is possible to connect to an existing LDAP server. You need to make sure that the LDAP server is reachable from the Nextcloud container. Then you can enable the LDAP app and configure LDAP in Nextcloud manually. If you don't have a LDAP server yet, recommended is to use this docker container: https://hub.docker.com/r/osixia/openldap/. Make sure here as well that Nextcloud can talk to the LDAP server. The easiest way is by adding the LDAP docker container to the docker network `nextcloud-aio`. Then you can connect to the LDAP container by its name from the Nextcloud container. **Pro-tip**: You will probably find this app useful: https://apps.nextcloud.com/apps/ldap_write_support

### USER_SQL
If you want to use the user_sql app, the easiest way is to create an additional database container and add it to the docker network `nextcloud-aio`. Then the Nextcloud container should be able to talk to the database container using its name.

### How to migrate from an already existing Nextcloud installation to Nextcloud AIO?
Please see the following documentation on this: [migration.md](https://github.com/nextcloud/all-in-one/blob/main/migration.md)
