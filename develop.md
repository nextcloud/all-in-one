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

## How to promote builds from develop to latest

<!---
You can use the Docker CLI to promote builds from develop to latest. Make sure to adjust:

- $name
- $digest

```shell
export AIO_NAME=$name
export AIO_DIGEST=$digest
docker pull nextcloud/$AIO_NAME@sha256:$AIO_DIGEST
docker tag nextcloud/$AIO_NAME@sha256:$AIO_DIGEST nextcloud/$AIO_NAME\:latest
docker push nextcloud/$AIO_NAME\:latest
```
--->

To automatically promoted the latest develop version you can use the following script:

**WARNING:** Make sure to verify that the latest develop tag is what you really want to deploy since someone could have pushed to main and created a new container in between.
```shell
# x64
export AIO_NAME=$name
docker pull nextcloud/$AIO_NAME\:develop
docker tag nextcloud/$AIO_NAME\:develop nextcloud/$AIO_NAME\:latest
docker push nextcloud/$AIO_NAME\:latest
```

**ATTENTION**: don't run the script below since the arm64 containers currently don't work!
```shell
# arm64 
export AIO_NAME=$name
docker pull nextcloud/$AIO_NAME\:develop-arm64
docker tag nextcloud/$AIO_NAME\:develop-arm64 nextcloud/$AIO_NAME\:latest-arm64
docker push nextcloud/$AIO_NAME\:latest-arm64
```
Later when the arm64 containers work, we can simply publish to latest and latest-arm64 in a rush by providing the name one time at the top of the script.