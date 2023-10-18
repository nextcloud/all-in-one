## Local AI
This container bundles Local AI and auto-configures it for you.

### Notes
- After the container was started the first time, you should see a new `nextcloud-aio-local-ai` folder when you open the files app with the default `admin` user. In there you should see a `models.yaml` config file. You can now add models in there. Please refer [here](https://github.com/go-skynet/model-gallery/blob/main/index.yaml) where you can get further urls that you can put in there. Afterwards restart all containers from the AIO interface and the models should automatically get downloaded by the local-ai container and activated.
- Example for content of `models.yaml`:
```yaml
# Stable Diffusion in NCNN with c++, supported txt2img and img2img 
- url: github:go-skynet/model-gallery/stablediffusion.yaml

# Port of OpenAI's Whisper model in C/C++ 
- url: github:go-skynet/model-gallery/whisper-base.yaml
  name: whisper-1

# A commercially licensable model based on GPT-J and trained by Nomic AI on the v0 GPT4All dataset.
- url: github:go-skynet/model-gallery/gpt4all-j.yaml
  name: gpt4all-j
```
-  Additionally after doing so, you might want to enable or disable specific features for your models in the integration_openai settings: `https://your-nc-domain.com/settings/admin/connected-accounts`
- The models folder where models get downloaded to is covered by AIOs backup solution
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers how to add it to the AIO stack

### Repository
https://github.com/szaimen/aio-local-ai

### Maintainer
https://github.com/szaimen
