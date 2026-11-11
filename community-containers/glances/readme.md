## Glances
This container starts [Glances](https://nicolargo.github.io/glances/), a web-based system monitoring dashboard, and auto-configures it for you.

> [!CAUTION]
> This container mounts the docker-socket from the host-system and thus has unlimited root access to it! Mounting the socket read-only does not limit this, as the full Docker API stays reachable.
>
> The image used is the third-party image `nicolargo/glances` with the moving `latest-full` tag, which is not built or reviewed by Nextcloud. Every update of that image gets root access to your host without any further review. Only add this container if you fully trust the upstream Glances project and its release process.

### Notes
- After adding and starting the container, you can directly visit http://ip.address.of.server:61208/ and access your new Glances instance!
- if you are using caddy community container, this will be available at `https://glances.your-server.com`, For security reasons, a password will be generated and shown in the AIO interface (if you click on showing the secret for glances). Use `glances` as user name. 
- It is recommended to start this container only in home networks, because there is no built-in authentication. But you can do a http-auth with your proxy.
- In order to access your Glances outside the local network, you have to set up your own reverse proxy. You can set up a reverse proxy following [these instructions](https://github.com/nextcloud/all-in-one/blob/main/reverse-proxy.md).
- The data of Glances will be automatically included in AIO's backup solution!
- See [here](https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers) how to add it to the AIO stack.

### Repository
https://github.com/nicolargo/glances

### Maintainer
https://github.com/pi-farm
