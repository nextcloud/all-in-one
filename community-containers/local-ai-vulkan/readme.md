
> [!WARNING]
> ARM devices are not supported by this container. It is only for x86_64 devices. (See: https://github.com/mudler/LocalAI/issues/5778)

## Local AI
This container bundles Local AI and auto-configures it for you.

### Notes
- Make sure vulkan is enabled in the AIO settings. Run `vulkaninfo` in the terminal to check if it is enabled.
- Make sure to have enough storage space available. This container alone needs ~7GB storage.
- Make sure to have enabled DRI device by adding `--env NEXTCLOUD_ENABLE_DRI_DEVICE=true`
-  To make it work, you first need to browse `https://your-nc-domain.com/settings/admin/ai` and enable or disable specific features for your models in the openAI settings. Afterwards using the Nextcloud Assistant should work.
- To access the Local AI web interface, you need to set reverse proxy rules for it.
```Cadyfile
http://local-ai.your-nc-domain.com {
    # Local AI web interface haven't any authentication, so you should protect it
    basic_auth {
        # Username "Bob", password "hiccup"
        Bob $2a$14$Zkx19XLiW6VYouLHR5NmfOFU0z2GTNmpkT/5qqR7hx4IjWJPDhjvG
    }
    reverse_proxy nenxtcloud-aio-local-ai-vulkan:8080
}
```
- See [this guide](https://github.com/nextcloud/all-in-one/discussions/5430) for how to improve AI task pickup speed
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack

### Repository
https://github.com/docjyJ/aio-local-ai-vulkan

### Maintainer
https://github.com/docjyJ
