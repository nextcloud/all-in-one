## Facerecognition
This container bundles the external model of facerecognition and auto-configures it for you.

### Notes
- This container needs imaginary in order to analyze modern file format images. Make sure to enable imaginary in the AIO interface before adding this container.
- The image analysis is currently set to fixed value of `1G`. See [this](https://github.com/search?q=repo%3Anextcloud%2Fall-in-one+1G+path%3A%2F%5Ecommunity-containers%5C%2Ffacerecognition%5C%2F%2F&type=code)
- Facerecognition is by default disabled for all users, if you want to enable facerecognition for all users, you can run the following before adding this container:
    ```bash
    # Go into the container
    sudo docker exec --user www-data -it nextcloud-aio-nextcloud bash
    ```
    Now inside the container:
    ```bash
    NC_USERS_NEW=$(php occ user:list | sed 's|^  - ||g' | sed 's|:.*||')
    mapfile -t NC_USERS_NEW <<< "$NC_USERS_NEW"
    for user in "${NC_USERS_NEW[@]}"
    do
        php occ user:setting "$user" facerecognition full_image_scan_done false
        php occ user:setting "$user" facerecognition enabled true
    done

    # Exit the container shell
    exit
    ```
- If facerecognition shall analyze shared files & folders (`sudo docker exec --user www-data -it nextcloud-aio-nextcloud php occ config:app:set facerecognition handle_shared_files --value true`), groupfolders (`sudo docker exec --user www-data -it nextcloud-aio-nextcloud php occ config:app:set facerecognition handle_group_files --value true`) and/or external storages (`sudo docker exec --user www-data -it nextcloud-aio-nextcloud php occ config:app:set facerecognition handle_external_files --value true`) in Nextcloud, you need to enable support for it manually first by running the mentioned commands before adding this container. See https://github.com/matiasdelellis/facerecognition/wiki/Settings#hidden-settings for further notes on each of these settings.
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack

### Repository
https://github.com/matiasdelellis/facerecognition-external-model

### Maintainer
https://github.com/matiasdelellis
