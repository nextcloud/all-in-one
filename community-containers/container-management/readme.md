## Container-Management
This container allows to manage insides of other containers via a GUI inside a Web session by allowing to run docker commands from inside this container.

### Notes
- After adding and starting the container, you need to visit `https://ip.address.of.this.server:5804` in order to log in with the user `container-management` and the password that you can see next to the container in the AIO interface. (The web page uses a self-signed certificate, so you need to accept the warning).
- Then, you should see a terminal. There you can use any docker command. ⚠️ Be very carefully while doing that as can break your instance!
- There are also some pre-made scripts that make configuring some of the community containers easier. For example scripts for [LLDAP](https://github.com/nextcloud/all-in-one/tree/main/community-containers/lldap) and [Facerecognition](https://github.com/nextcloud/all-in-one/tree/main/community-containers/facerecognition).
- ⚠️ After you are done doing your operations, remove the container for better security again from the stack: https://github.com/nextcloud/all-in-one/tree/main/community-containers#how-to-remove-containers-from-aios-stack
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack

### Repository
https://github.com/szaimen/aio-container-management

### Maintainer
https://github.com/szaimen
