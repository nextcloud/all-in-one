## Pi-hole
This container bundles the [pi-hole](https://pi-hole.net/) ad blocker and auto-configures it for you.

> [!WARNING]
> Ports 53 (tcp and udp) and 8573 are published on all network interfaces of the host and Pi-hole answers DNS queries from all networks. On a server that is reachable from the internet, it therefore acts as an open DNS resolver that can be abused e.g. for DNS amplification attacks. Also, the admin interface on port 8573 uses plain http. Make sure that these ports are not reachable from untrusted networks (note that ports published by Docker bypass host firewalls like ufw, so block them e.g. in your router or in a firewall in front of the server).

### Notes
- You should not run this container on a public VPS! It is only intended to run in home networks!
- This container is incompatible with the dnsmasq community container as both need ports 53 to work correctly. Do not enable both at the same time!
- Make sure that no dns server is already running by checking with `sudo netstat -tulpn | grep 53`. Otherwise the container will not be able to start!
- The DHCP functionality of Pi-hole has been disabled!
- The data of pi-hole will be automatically included in AIOs backup solution!
- After adding and starting the container, you can visit `http://ip.address.of.this.server:8573/admin` in order to log in with the admin key that you can see next to the container in the AIO interface. There you can configure the pi-hole setup. Also you can add local dns records.
- You can configure your home network now to use pi-hole as its dns server by configuring your router.
- Additionally, you can configure the docker daemon to use that by editing `/etc/docker/daemon.json` and adding ` { "dns" : [ "ip.address.of.this.server" , "8.8.8.8" ] } `.
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack

### Repository
https://github.com/pi-hole/docker-pi-hole

### Maintainer
https://github.com/szaimen
