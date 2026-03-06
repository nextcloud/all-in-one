## Scrutiny
This container bundles Scrutiny which is a frontend for SMART stats and auto-configures it for you.

### Notes
- This container should only be run in home networks
- ⚠️ This container mounts all devices from the host inside the container in order to be able to access the drives and smartctl stats which is a security issue. However no better solution was found for the time being.
- This container only works on Linux and not on Docker-Desktop.
- After adding and starting the container, you need to visit `http://internal.ip.of.server:8000` which will show the dashboard for your drives.
- It supports sending notifications in case of a smartctl failure if you enable the notifications community container: https://github.com/nextcloud/all-in-one/tree/main/community-containers/notifications
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack

### Repository
https://github.com/szaimen/aio-scrutiny

### Maintainer
https://github.com/szaimen
