## Home Assistant
This container bundles Home Assistant and auto-configures it for you.

### Notes
- This container should only be run in home networks since Home Assistant is designed for local home automation.
- After adding and starting the container, you can visit `http://ip.address.of.this.server:8123` in order to set up your Home Assistant instance.
- The data of Home Assistant will be automatically included in AIOs backup solution!
- In order to access your Home Assistant outside the local network, you have to set up your own reverse proxy. You can set up a reverse proxy following [these instructions](https://github.com/nextcloud/all-in-one/blob/main/reverse-proxy.md).
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack

### Repository
https://github.com/home-assistant/core

### Maintainer
https://github.com/szaimen
