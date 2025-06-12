## MollySocket
This container bundles MollySocket and auto-configures it for you.

### Notes
- This container is only intended to be used over https behind a reverse proxy. You can You can set up a reverse proxy following [these instructions](https://github.com/nextcloud/all-in-one/blob/main/reverse-proxy.md) OR use the Caddy community container that will automatically configure mollysocket.$NC_DOMAIN to redirect to your MollySocket.
- This will work with a locally hosted UnifiedPush provider such as [NextPush](https://codeberg.org/NextPush/uppush) if it is hosted at https://push.$NC_DOMAIN.
- See [here](https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers) how to add it to the AIO stack.

### Repository
https://github.com/mollyim/mollysocket

### Maintainer
https://github.com/Anvil5465
