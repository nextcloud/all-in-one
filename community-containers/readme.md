# Community containers
This directory features containers that are built for AIO which allows to add additional functionality very easily.

## Disclaimers
⚠️ This is currently beta and not stable yet!

All containers that are in this directory are community maintained so the responsibility is on the community to keep them updated and secure. There is no guarantee that this will be the case in the future.

## How to use this?
You might want to add additional community containers to the default AIO stack. You can do so by adding `--env AIO_COMMUNITY_CONTAINERS="container1 container2"` to the docker run command of the mastercontainer (but before the last line `nextcloud/all-in-one:latest`! If it was started already, you will need to stop the mastercontainer, remove it (no data will be lost) and recreate it using the docker run command that you initially used) and customize the value to your fitting. It must match the folder names in this directory! ⚠️⚠️⚠️ Please review the folder for documentation on each of the containers before adding them! Not reviewing the documentation for each of them first might break starting the AIO containers because e.g. fail2ban only works on Linux and not on Docker Desktop!

## How to add containers?
Simply submit a PR by creating a new folder in this directory: https://github.com/nextcloud/all-in-one/tree/main/community-containers with the name of your container. It must include a json file with the same name and with correct syntax and a readme.md with additional information. You might get inspired by https://github.com/nextcloud/all-in-one/tree/main/community-containers/fail2ban or https://github.com/nextcloud/all-in-one/tree/main/community-containers/plex. For a full-blown example of the json file, see https://github.com/nextcloud/all-in-one/blob/main/php/containers.json. The json-schema that it validates against can be found here: https://github.com/nextcloud/all-in-one/blob/main/php/containers-schema.json.
