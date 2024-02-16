## Pi-hole
This container bundles pi-hole and auto-configures it for you.

### Notes
- You should not run this container on a public VPS! It is only intended to run in home networks!
- Make sure that no dns server is already running by checking with `sudo netstat -tulpn | grep 53`. Otherwise the container will not be able to start!
- The DHCP functionality of Pi-hole has been disabled!
- The data of pi-hole will be automatically included in AIOs backup solution!
- After adding and starting the container, you can visit `http://ip.address.of.this.server:8573/admin` in order to log in with the admin key that you can retrieve when running `sudo docker inspect nextcloud-aio-pihole | grep WEBPASSWORD`. There you can configure the pi-hole setup. Also you can add local dns records.
- You can configure your home network now to use pi-hole as its dns server by configuring your router.
- Additionally, you can configure the docker daemon to use that by editing `/etc/docker/daemon.json` and adding ` { "dns" : [ "ip.address.of.this.server" , "8.8.8.8" ] } `.
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack

### Repository
https://github.com/pi-hole/docker-pi-hole

### Maintainer
https://github.com/szaimen
