# Manual installation

You can run the containers that are build for AIO with docker-compose. This comes with a few downsides, that are discussed below.

### Advantages
- You can run it without a container having access to the docker socket
- You can modify all values on your own
- You can run the containers with docker swarm

### Disadvantages
- You lose the AIO interface
- You lose update notifications and automatic updates
- You lose all AIO backup and restore features
- You lose all community containers: https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers
- **You need to know what you are doing, especially when modifying the compose.yaml file**
- For updating, you need to strictly follow the at the bottom described update routine
- Probably more

## How to use this?
First, install docker and docker-compose (v2) if not already done. Then simply run the following:
```bash
git clone https://github.com/nextcloud/all-in-one.git
cd all-in-one/manual-install
```
Then copy the sample.conf to default environment file, e.g. `cp sample.conf .env`, open the new conf file, e.g. with `nano .env`, edit all values that are marked with `# TODO!`, close and save the file. (Note: there is no clamav image for arm64).<br>
⚠️ **Warning**: Do not use the symbols `@` and `:` in your passwords. These symbols are used to build database connection strings. You will experience issues when using these symbols! Also please note that values inside the latest.yaml that are not exposed as variables are not officially supported to be changed. See for example [this report](https://github.com/nextcloud/all-in-one/issues/5612).

Now copy the provided yaml file to a compose.yaml file by running `cp latest.yml compose.yaml`.

Now you should be ready to go with `sudo docker compose up`.

## Docker profiles
The default profile of `latest.yml` only provide the minimum necessary services: nextcloud, database, redis and apache. To get optional services collabora, talk, whiteboard, talk-recording, clamav, imaginary or fulltextsearch use additional arguments for each of them, for example `--profile collabora`. (Note: there is no clamav image for arm64).

For a complete all-in-one with collabora use `sudo docker compose --profile collabora --profile talk --profile talk-recording --profile clamav --profile imaginary --profile fulltextsearch --profile whiteboard up`. (Note: there is no clamav image for arm64).

## How to update?
Since the AIO containers may change in the future, it is highly recommended to strictly follow the following procedure whenever you want to upgrade your containers.
1. If your previous copy of `sample.conf` is named `my.conf`, run `mv -vn my.conf .env` in order to rename the file to `.env`.
1. Run `sudo docker compose down` to stop all running containers
1. Back up all important files and folders
1. If your compose file is still named `docker-compose.yml` rename it to `compose.yaml` by running `mv -vn docker-compose.yml compose.yaml`
1. Run `git pull` in order to get the updated yaml files from the repository. Now bring your `compose.yaml` file up-to-date with the updated one from the repository. You can use `diff compose.yaml latest.yml` for comparing. ⚠️ **Please note**: Starting with AIO v5.1.0, ipv6 networking will be enabled by default, so make sure to either enable it first by following steps 1 and 2 of https://github.com/nextcloud/all-in-one/blob/main/docker-ipv6-support.md and then proceed with the steps below or disable ipv6 networking by editing the compose.yaml file and removing ipv6 from the network.
1. Also have a look at the `sample.conf` if any variable was added or renamed and add that to your conf file as well. Here may help the diff command as well.
1. After the file update was successful, simply run `sudo docker compose pull` to pull the new images.
1. At the end run `sudo docker compose up` in order to start and update the containers with the new configuration.

## FAQ
### Backup and restore?
If you leave `NEXTCLOUD_DATADIR` in your conf file at the default value of `nextcloud_aio_nextcloud_data` and don't modify the yaml file, all data will be stored inside docker volumes which are on Linux by default located here: `/var/lib/docker/volumes`. Simply backing up this location should be a valid backup solution. Then you can also easily restore in case something bad happens. However if you change `NEXTCLOUD_DATADIR` to a path like `/mnt/ncdata`, you obviously need to back up this location, too because the Nextcloud data will be stored there. The same applies to any change to the yaml file. 

Obviously you also need to back up the conf file and the yaml file if you modified it.
