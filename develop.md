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

To automatically promoted the latest develop version you can use the following script:

**WARNING:** Make sure to verify that the latest develop tag is what you really want to deploy since someone could have pushed to main and created a new container in between.
```shell
# Set the name of the container that you want to promote from the develop- to the latest channels
export AIO_NAME=$name
# x64
docker pull nextcloud/$AIO_NAME\:develop
docker tag nextcloud/$AIO_NAME\:develop nextcloud/$AIO_NAME\:latest
docker push nextcloud/$AIO_NAME\:latest
# arm64 
docker pull nextcloud/$AIO_NAME\:develop-arm64
docker tag nextcloud/$AIO_NAME\:develop-arm64 nextcloud/$AIO_NAME\:latest-arm64
docker push nextcloud/$AIO_NAME\:latest-arm64
```
