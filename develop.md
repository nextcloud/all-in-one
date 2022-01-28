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

## How to build new containers

Go to https://github.com/nextcloud-releases/all-in-one/actions/workflows/repo-sync.yml and run the workflow that will first sync the repo and then build new container that automatically get published to `develop` and `develop-arm64`.

## How to promote builds from develop to latest

1. Verify that no job is running here: https://github.com/nextcloud-releases/all-in-one/actions/workflows/build_images.yml
2. Go to https://github.com/nextcloud-releases/all-in-one/actions/workflows/promote-to-latest.yml, click on `Run workflow` and enter your desired container image name that you want to publish from develop to latest. Available image names are listed here: https://github.com/nextcloud-releases/all-in-one/blob/main/.github/workflows/build_images.yml#L21-L30 
