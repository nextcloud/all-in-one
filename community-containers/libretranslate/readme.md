## LibreTranslate
This container bundles LibreTranslate and auto-configures it for you.

> [!WARNING]
> The LibreTranslate container and app is deprecated!
> Please use the [translate2 app](https://apps.nextcloud.com/apps/translate2) instead.
> You can activate it by first enabling the Docker-Socket-Proxy in the AIO-interface and then heading over to `https://your-nc-domain.com/settings/apps/tools` and installing and enabling the `Local Machine Translation` app.

### Notes
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
