## Local AI
This container bundles Local AI and auto-configures it for you.

### Notes
- After the container was started the first time, you should see a new `nextcloud-aio-local-ai` folder when you open the files app with the default `admin` user. In there you should see a `models` folder. Now you can download models from e.g. https://download.nextcloud.com/server/apps/stt_whisper/ or https://download.nextcloud.com/server/apps/llm/ or others that are mentioned in https://localai.io/model-compatibility/index.html#model-compatibility-table and put them into the `models` folder. Afterwards restart all containers from the AIO interface and the models should automatically get active. Additionally after doing so, you might want to enable specific features for your models in the integration_openai settings: `https://your-nc-domain.com/settings/admin/connected-accounts`
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers how to add it to the AIO stack

### Repository
https://github.com/szaimen/aio-local-ai

### Maintainer
https://github.com/szaimen
