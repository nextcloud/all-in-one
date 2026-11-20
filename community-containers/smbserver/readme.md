## SMB-server
This container bundles an SMB-server and allows to configure it via a graphical shell script.

> [!WARNING]
> - This container mounts the Nextcloud data directory and `NEXTCLOUD_MOUNT` with **write access**. Everyone who can access a share that points to them can read and modify the files of all Nextcloud users, bypassing Nextcloud's permissions, versions and trash bin.
> - Ports 445 and 139 (SMB) and 5803 (web terminal) are published on all network interfaces of the host. Make sure that they are not reachable from untrusted networks (note that ports published by Docker bypass host firewalls like ufw, so block them e.g. in your router or in a firewall in front of the server).

### Notes
- This container should only be run in home networks
- After adding and starting the container, you need to visit `https://internal.ip.of.server:5803` in order to log in with the `smbserver` user and the password that you can see next to the container in the AIO interface. (The web page uses a self-signed certificate, so you need to accept the warning). Then type in `bash /smbserver.sh` and you will see a graphical UI for configuring the smb-server interactively.
- The config data of SMB-server will be automatically included in AIOs backup solution!
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack

### Repository
https://github.com/szaimen/aio-smbserver/

### Maintainer
https://github.com/szaimen
