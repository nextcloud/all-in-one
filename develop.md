## Developer channel
If you want to switch to the develop channel, you simply stop and delete the mastercontainer and create a new one with a changed tag to develop:
```shell
sudo docker run -it \
--name nextcloud-aio-mastercontainer \
--restart always \
-p 80:80 \
-p 8080:8080 \
-p 8443:8443 \
--volume nextcloud_aio_mastercontainer:/mnt/docker-aio-config \
--volume /var/run/docker.sock:/var/run/docker.sock:ro \
nextcloud/all-in-one:develop
```
And you are done :)
It will now also select the developer channel for all other containers automatically.

## How to publish new releases?
Simply use https://github.com/nextcloud/all-in-one/issues/180 as template.

## How to build new containers

Go to https://github.com/nextcloud-releases/all-in-one/actions/workflows/repo-sync.yml and run the workflow that will first sync the repo and then build new container that automatically get published to `develop` and `develop-arm64`.

## How to promote builds from develop to beta

1. Verify that no job is running here: https://github.com/nextcloud-releases/all-in-one/actions/workflows/build_images.yml
2. Go to https://github.com/nextcloud-releases/all-in-one/actions/workflows/promote-to-beta.yml, click on `Run workflow`.

## How to promote builds from beta to latest

1. Verify that no job is running here: https://github.com/nextcloud-releases/all-in-one/actions/workflows/promote-to-beta.yml
2. Go to https://github.com/nextcloud-releases/all-in-one/actions/workflows/promote-to-latest.yml, click on `Run workflow`.

## Where to find the VPS and other builds?
This is documented here: https://github.com/nextcloud-releases/all-in-one/tree/main/.build
