## LibreTranslate
This container bundles LibreTranslate and auto-configures it for you.

### Notes

- Please note that this community container is currently not working since its integration app is not yet compatible with Nextcloud 27 (Hub 6). You can follow the progress here: https://github.com/v1r0x/integration_libretranslate/issues/1
- After the initial startup is done, you might want to change the default language to translate from and to via:
```bash
# Adjust the values `en` and `de` in commands below accordingly
sudo docker exec --user www-data nextcloud-aio-nextcloud php occ config:app:set integration_libretranslate from_lang --value="en"
sudo docker exec --user www-data nextcloud-aio-nextcloud php occ config:app:set integration_libretranslate to_lang --value="de"
```
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack

### Repository
https://github.com/szaimen/aio-libretranslate

### Maintainer
https://github.com/szaimen
