## Developer channel
If you want to switch to the develop channel, you simply stop and delete the mastercontainer and create a new one with a changed tag to develop:
```shell
sudo docker run \
--init \
--sig-proxy=false \
--name nextcloud-aio-mastercontainer \
--restart always \
--publish 80:80 \
--publish 8080:8080 \
--publish 8443:8443 \
--volume nextcloud_aio_mastercontainer:/mnt/docker-aio-config \
--volume /var/run/docker.sock:/var/run/docker.sock:ro \
ghcr.io/nextcloud-releases/all-in-one:develop
```
And you are done :)
It will now also select the developer channel for all other containers automatically.

## How to publish new releases?
Simply use https://github.com/nextcloud/all-in-one/issues/180 as template.

## How to update existing instances to a new major Nextcloud version?
Simply use https://github.com/nextcloud/all-in-one/issues/6198 as template.

## How to build new containers
Go to https://github.com/nextcloud-releases/all-in-one/actions/workflows/repo-sync.yml and run the workflow that will first sync the repo and then build new container that automatically get published to `develop` and `develop-arm64`.

## How to test things correctly?
Before testing, make sure that at least the amd64 containers are built successfully by checking the last workflow here: https://github.com/nextcloud-releases/all-in-one/actions/workflows/build_images.yml.

There is a testing-VM available for the maintainer of AIO that allows for some final testing before releasing new version. See [this](https://cloud.nextcloud.com/apps/collectives/Nextcloud%20Handbook/Technical/AIO%20testing%20VM?fileId=6350152) for details.

Additionally, there are now E2E tests available that can be run via https://github.com/nextcloud/all-in-one/actions/workflows/playwright.yml

## How to promote builds from develop to beta
1. Verify that no job is running here: https://github.com/nextcloud-releases/all-in-one/actions/workflows/build_images.yml
2. Go to https://github.com/nextcloud-releases/all-in-one/actions/workflows/promote-to-beta.yml, click on `Run workflow`.

## Where to find the VPS and other builds?
This is documented here: https://github.com/nextcloud-releases/all-in-one/tree/main/.build

## How to promote builds from beta to latest

1. Verify that GitHub Services are running correctly: https://www.githubstatus.com/
1. Verify that no job is running here: https://github.com/nextcloud-releases/all-in-one/actions/workflows/promote-to-beta.yml
1. Go to https://github.com/nextcloud-releases/all-in-one/actions/workflows/promote-to-latest.yml, click on `Run workflow`.

## How to connect to the database?
Simply run `sudo docker exec -it nextcloud-aio-database psql -U oc_nextcloud nextcloud_database` and you should be in.

## How to locally build and test changes to mastercontainer
1. Ensure you are on the developer channel per the instructions above.
1. Use the commands below from the project root to build the mastercontainer image:
```
docker buildx build --file Containers/mastercontainer/Dockerfile --tag ghcr.io/nextcloud-releases/all-in-one:develop --load .
```
1. Start a container with above built image.
1. Since the hash of a locally built image doesn't match the latest release mastercontainer, it prompts for a mandatory update. To temporarily bypass the update suffix `?bypass_mastercontainer_update` to the URL. Eg: `https://localhost:8080/containers?bypass_mastercontainer_update`

## How to locally build and test changes to other containers using the bypass_container_update param
1. Ensure you are on the developer channel per the instructions above.
1. Use the commands below from the project root to build the container image:
```
# For the "nextcloud" container
docker buildx build --file Containers/nextcloud/Dockerfile --tag ghcr.io/nextcloud-releases/aio-nextcloud:develop --load .

# For all other containers
docker buildx build --file Containers/{container}/Dockerfile --tag ghcr.io/nextcloud-releases/aio-{container}:develop --load Containers/{container}
```
1. Stop the containers using the AIO admin interface.
1. Reload the AIO admin interface with the param `bypass_container_update` to avoid overwriting your local changes, e.g. `https://localhost:8080/containers?bypass_container_update`.
1. Click "Start and update containers" and test your changes. Containers will not be updated, despite the button text.
