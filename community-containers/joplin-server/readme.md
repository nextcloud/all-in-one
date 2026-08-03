# Joplin Server

This container allow you to deploy a [Joplin Server](https://joplinapp.org/help/dev/spec/architecture#joplin-server). Deploy automatically a postgreSQL instance as a sidecar.



### Why 

Joplin Server gives you faster and better sync (less sync error) than with WebDAV. Also automatic clean-up and ability to share your notes.

Also, deploying Joplin Server through nextcloud rather than beside it allow for automatic note backup by borg and that's wonderful.


### Notes
- Exposed Port 22300
- Default creds : admin@localhost / admin
- In order to access your Joplin outside the local network, you have to set up your own reverse proxy. You can use the [Caddy](https://github.com/nextcloud/all-in-one/tree/main/community-containers/caddy) community container that will automatically configure `joplin.$NC_DOMAIN` to redirect to your Joplin Server.
- The postgreSQL is not exposed and is only used through the docker network.
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack

### Repository
https://hub.docker.com/r/joplin/server

### Maintainer
https://github.com/lonode  

