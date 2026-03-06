## MakeMKV
This container bundles MakeMKV and auto-configures it for you.

### Notes
- This container should only be run in home networks
- ⚠️ This container mounts all devices from the host inside the container in order to be able to access the external DVD/Blu-ray drives which is a security issue. However no better solution was found for the time being.
- This container only works on Linux and not on Docker-Desktop.
- This container requires the [`NEXTCLOUD_MOUNT` variable in AIO to be set](https://github.com/nextcloud/all-in-one#how-to-allow-the-nextcloud-container-to-access-directories-on-the-host). Otherwise the output will not be saved correctly..
- After adding and starting the container, you need to visit `https://internal.ip.of.server:5802` in order to log in with the `makemkv` user and the password that you can see next to the container in the AIO interface. (The web page uses a self-signed certificate, so you need to accept the warning).
- After the first login, you can adjust the `/output` directory in the MakeMKV settings to a subdirectory of the root of your chosen `NEXTCLOUD_MOUNT`. (by default `NEXTCLOUD_MOUNT` is mounted to `/output` inside the container. Thus all data is written to the root of it)
- The configured `NEXTCLOUD_DATADIR` is getting mounted to `/storage` inside the container.
- The config data of MakeMKV will be automatically included in AIOs backup solution!
- ⚠️ After you are done doing your operations, remove the container for better security again from the stack: https://github.com/nextcloud/all-in-one/tree/main/community-containers#how-to-remove-containers-from-aios-stack
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack

### Repository
https://github.com/jlesage/docker-makemkv

### Maintainer
https://github.com/szaimen
