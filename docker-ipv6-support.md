# IPv6-Support for Docker

## Docker on Linux and Docker-rootless
First of all upgrade your docker installation to v27.0.1 or higher.
1. Then edit `/etc/docker/daemon.json` (or `~/.config/docker/daemon.json` in case of docker-rootless), add the below json:

> [!WARNING]
> This will enable ipv6 for all new docker networks by default! You can alternatively create the `nextcloud-aio` network with ipv6 support by hand manually via docker network create or via compose.yaml.

```json
{
    "default-network-opts": {"bridge":{"com.docker.network.enable_ipv6":"true"}}
}
```

And save the file.

2.  Reload the Docker configuration file.

```console
sudo systemctl restart docker
```

3. Make sure that ipv6 is enabled for the internal `nextcloud-aio` network by running `sudo docker network inspect nextcloud-aio | grep EnableIPv6`. On a new instance, this command should return that it did not find a network with this name. Then you can run `sudo docker network create nextcloud-aio` in order to create the network with ipv6-support. However if it finds the network and its value `EnableIPv6` is set to false, make sure to follow https://github.com/nextcloud/all-in-one/discussions/4989 in order to recreate the network and enable ipv6 for it.

## Docker Desktop (Windows and macOS)
First of all upgrade your docker desktop installation to v4.32.0 or higher.
Then, on Windows and macOS which use Docker Desktop, you need to go into the settings, and select `Docker Engine`. There you should see the currently used daemon.json file. 

1. You need to now adjust this json file:

> [!WARNING]
> This will enable ipv6 for all new docker networks by default! You can alternatively create the `nextcloud-aio` network with ipv6 support by hand manually via docker network create or via compose.yaml.

```json
"default-network-opts": {"bridge":{"com.docker.network.enable_ipv6":"true"}}
```

2. Add these values to the json and make sure to keep the other currently values and that you don't see `Unexpected token in JSON at position ...` before attempting to restart by clicking on `Apply & restart`.
3. Make sure that ipv6 is enabled for the internal `nextcloud-aio` network by running `sudo docker network inspect nextcloud-aio | grep EnableIPv6`. On a new instance, this command should return that it did not find a network with this name. Then you can run `sudo docker network create nextcloud-aio` in order to create the network with ipv6-support. However if it finds the network and its value `EnableIPv6` is set to false, make sure to follow https://github.com/nextcloud/all-in-one/discussions/4989 in order to recreate the network and enable ipv6 for it.

---

**Note**: This is a copy of the original docker docs at https://docs.docker.com/config/daemon/ipv6/ which apparently are not correct.
