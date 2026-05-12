# Joplin Server

This container allow you to deploy a [Joplin Server](https://joplinapp.org/help/dev/spec/architecture#joplin-server). It's basically this [official docker image](https://hub.docker.com/r/joplin/server) wrapped in a single image with PostgreSQL embedded.



### Why 

Joplin Server gives you faster and better sync (less sync error) than with WebDAV. Also automatic clean-up and ability to share your notes.

Also, deploying Joplin Server through nextcloud rather than beside it allow for automatic note backup by borg and that's wonderful.

### Notes on architecture

Joplin Server use for dev purpose SQLite but PostgreSQL being a vastly more powerful database, it's directly bundled here. Big reminder that embedding multiple services (db + app) in a single container is a **bad practice**, but this is specifically crafted here with nextcloud AIO constraints in mind, due to how community containers works. 


### Usage
- Port 22300
- Default creds : admin@localhost / admin
- Default UI : <your_nextcloud_URL:22300>

### Repository
https://github.com/lonode/joplin-server-standalone

### Maintainer
https://github.com/lonode  
https://github.com/joplin  

