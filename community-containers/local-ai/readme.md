## Local AI
This container bundles Local AI and auto-configures it for you.

### Notes
- Make sure to have enough storage space available. This container alone needs ~7GB storage. Every model that you add to `models.yaml` will of course use additional space which adds up quite fast.
- After the container was started the first time, you can open `http://ip.address.of.server:8000` and log in with the api key that you can retrieve when running... 
-  To make it work, you first need to browse `https://your-nc-domain.com/settings/admin/ai` and enable or disable specific features for your models in the openAI settings. Afterwards using the Nextcloud Assistant should work.
- See [this guide](https://github.com/nextcloud/all-in-one/discussions/5430) for how to improve AI task pickup speed
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack

### Repository
https://github.com/szaimen/aio-local-ai

### Maintainer
https://github.com/szaimen
