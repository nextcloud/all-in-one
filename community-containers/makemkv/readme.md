## MakeMKV
This container bundles MakeMKV and auto-configures it for you.

### Notes
- This container should only be run in home networks
- This container requires the [`NEXTCLOUD_MOUNT` variable in AIO to be set](https://github.com/nextcloud/all-in-one?tab=readme-ov-file#how-to-allow-the-nextcloud-container-to-access-directories-on-the-host). Otherwise the output will not be saved correctly.
- ⚠️ TODO: note down requirements regarding devices and also how to check if they are there...
- After adding and starting the container, you need to visit `https://internal.ip.of.server:5800` in order to log in with the `makemkv` user and the password that you can retrieve when running `sudo docker inspect nextcloud-aio-makemkv | grep WEB_AUTHENTICATION_PASSWORD`. 
- After the first login, you can adjust the `/output` directory in the MakeMKV settings to a subdirectory of the root of your chosen `NEXTCLOUD_MOUNT`. (by default `NEXTCLOUD_MOUNT` is mounted to `/output` inside the container. Thus all data is written to the root of it)
- The config data of MakeMKV will be automatically included in AIOs backup solution!
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack

### Repository
https://github.com/jlesage/docker-makemkv

### Maintainer
https://github.com/szaimen
