## Borgbackup Viewer
This container allows to view the local borg repository in a web session. It also allows you to restore files and folders from the backup by using desktop programs in a web browser.

### Notes
- After adding and starting the container, you need to visit `https://ip.address.of.this.server:5801` in order to log in with the user `nextcloud` and the password that you can see next to the container in the AIO interface. (The web page uses a self-signed certificate, so you need to accept the warning).
- Then, you should see a terminal. There type in `borg mount /mnt/borgbackup/borg /tmp/borg` to mount the backup archive at `/tmp/borg` inside the container. Afterwards type in `nautilus /tmp/borg` which will show a file explorer and allows you to see all the files. You can then copy files and folders back to their initial mountpoints inside `/nextcloud_aio_volumes/`, `/host_mounts/` and `/docker_volumes/`. ⚠️ Be very carefully while doing that as can break your instance!
- After you are done with the operation, click on the terminal in the background and press `[CTRL]+[c]` multiple times to close any open application. Then run `umount /tmp/borg` to unmount the mountpoint correctly.
- You can also delete specific archives by running `borg list`,  delete a specific archive e.g. via `borg delete --stats --progress "::20220223_174237-nextcloud-aio"` and compact the archives via `borg compact`. After doing so, make sure to update the backup archives list in the AIO interface! You can do so by clicking on the `Check backup integrity` button or `Create backup` button.
- ⚠️ After you are done doing your operations, remove the container for better security again from the stack: https://github.com/nextcloud/all-in-one/tree/main/community-containers#how-to-remove-containers-from-aios-stack
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack

### Repository
https://github.com/szaimen/aio-borgbackup-viewer

### Maintainer
https://github.com/szaimen

