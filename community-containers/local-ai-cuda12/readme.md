## Local AI Nvidia Cuda 12
This container bundles Local AI with Nvidia GPU support via Cuda 12 and auto-configures it for you.


### Notes
- To use this, we need to have some additional environment variables configured in the Nextcloud AIO container:`ENABLE_NVIDIA_GPU=true`, `NEXTCLOUD_ENABLE_DRI_DEVICE=true` and include "local-ai-cuda12" in `AIO_COMMUNITY_CONTAINERS`
 
- Make sure to have enough storage space available. This container alone needs ~48GB storage and probable aditional ~48GB when it update. That's ~96GB of free space. Every model that you add to `models.yaml` will of course use additional space which adds up quite fast.
- After the container was started the first time, you should see a new `nextcloud-aio-local-ai` folder when you open the files app with the default `admin` user. In there you should see a `models.yaml` config file. You can now add models in there. Please refer [here](https://github.com/mudler/LocalAI/blob/master/gallery/index.yaml) where you can get further urls that you can put in there. Afterwards restart all containers from the AIO interface and the models should automatically get downloaded by the local-ai container and activated.
- Example for content of `models.yaml` (if you add all of them, it takes around 10GB additional space):
```yaml
# Stable Diffusion in NCNN with c++, supported txt2img and img2img 
- url: github:mudler/LocalAI/gallery/stablediffusion.yaml
  name: Stable_diffusion
```
-  To make it work, you first need to browse `https://your-nc-domain.com/settings/admin/ai` and enable or disable specific features for your models in the openAI settings. Afterwards using the Nextcloud Assistant should work.
- See [this guide](https://github.com/nextcloud/all-in-one/discussions/5430) for how to improve AI task pickup speed
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack

### Repository
https://github.com/luzfcb/aio-local-ai

### Maintainer
https://github.com/luzfcb
