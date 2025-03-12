## SMB-server
This container bundles an SMB-server and allows to configure it via a graphical shell script.

### Notes
- This container should only be run in home networks
- This container currently only works on amd64. See https://github.com/szaimen/aio-smbserver/issues/3
- After adding and starting the container, you need to visit `https://internal.ip.of.server:5803` in order to log in with the `smbserver` user and the password that you can see next to the container in the AIO interface. (The web page uses a self-signed certificate, so you need to accept the warning). Then type in `bash /smbserver.sh` and you will see a graphical UI for configuring the smb-server interactively.
- The config data of SMB-server will be automatically included in AIOs backup solution!
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack

### Repository
https://github.com/szaimen/aio-smbserver/

### Maintainer
https://github.com/szaimen
