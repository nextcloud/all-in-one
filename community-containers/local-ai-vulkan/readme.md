
> [!WARNING]
> ARM devices are not supported by this container. It is only for x86_64 devices. (See: https://github.com/mudler/LocalAI/issues/5778)

## Local AI with Vulkan support
This container bundles Local AI and auto-configures it for you. It support hardware acceleration with Vulkan.

### Notes
Documentation is available on the container repository. This documentation is regularly updated and is intended to be as simple and detailed as possible. Thanks for all your feedback!

- See https://github.com/docjyJ/aio-local-ai-vulkan#getting-started for getting start with this container.
- See [this guide](https://github.com/nextcloud/all-in-one/discussions/5430) for how to improve AI task pickup speed
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack
- Note that Nextcloud supports only one server for AI queries, so this container cannot be used at the same time as other AI containers.

### Repository
https://github.com/docjyJ/aio-local-ai-vulkan

### Maintainer
https://github.com/docjyJ
