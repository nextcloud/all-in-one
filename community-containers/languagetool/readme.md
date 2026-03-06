## LanguageTool for Collabora
This container bundles a LanguageTool for Collabora which adds spell checking functionality to Collabora.

### Notes
- Make sure to have collabora enabled via the AIO interface
- After adding this container via the AIO Interface, while all containers are still stopped, you need to scroll down to the `Additional Collabora options` section and enter `--o:languagetool.enabled=true --o:languagetool.base_url=http://nextcloud-aio-languagetool:8010/v2`.
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack

### Repository
https://github.com/Erikvl87/docker-languagetool

### Maintainer
https://github.com/szaimen
