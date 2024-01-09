## Facerecognition
This container bundles the external model of facerecognition and auto-configures it for you.

### Notes
- This container needs imaginary in order to analyze modern file format images. Make sure to enable imaginary in the AIO interface before adding this container.
- If facerecognition shall analyze shared files & folders (`sudo docker exec --user www-data -it nextcloud-aio-nextcloud php occ config:app:set facerecognition handle_shared_files --value true`), groupfolders (`sudo docker exec --user www-data -it nextcloud-aio-nextcloud php occ config:app:set facerecognition handle_group_files --value true`) and/or external storages (`sudo docker exec --user www-data -it nextcloud-aio-nextcloud php occ config:app:set facerecognition handle_external_files --value true`) in Nextcloud, you need to enable support for it manually first by running the mentioned commands before adding this container. See https://github.com/matiasdelellis/facerecognition/wiki/Settings#hidden-settings for further notes on each of these settings.
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack

### Repository
https://github.com/matiasdelellis/facerecognition-external-model

### Maintainer
https://github.com/matiasdelellis
