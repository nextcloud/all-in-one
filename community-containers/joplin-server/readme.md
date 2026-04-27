# Joplin Server

This container allow you to deploy a [Joplin Server](https://joplinapp.org/help/dev/spec/architecture#joplin-server). Deploy automically a postgreSQL instance as a sidecar.



### Why 

Joplin Server gives you faster and better sync (less sync error) than with WebDAV. Also automatic clean-up and ability to share your notes.

Also, deploying Joplin Server through nextcloud rather than beside it allow for automatic note backup by borg and that's wonderful.


### Usage
- Port 22300
- Default creds : admin@localhost / admin
- Default UI : <your_nextcloud_URL:22300>

The postgreSQL is not exposed and is only used through the docker network.

### Repository
https://hub.docker.com/r/joplin/server

### Maintainer
https://github.com/lonode  

