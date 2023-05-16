# IPv6-Support for Docker

Before enabling IPv6-Support for Docker, please note that there are still some unresolved problems in regards to IPv6-Support in Docker. See https://github.com/nextcloud/all-in-one/discussions/2557 for more details on this.

Now that this was mentioned, see the instructions below on how to enable IPv6 for Docker.

## Docker on Linux and Docker-rootless
1.  Edit `/etc/docker/daemon.json` (or `~/.config/docker/daemon.json` in case of docker-rootless), set the `ipv6` key to `true` and the `fixed-cidr-v6` key to your IPv6 subnet. In this example we are setting it to `fd12:3456:789a:1::/64`. Additionally set `experimental` to `true` and `ip6tables` to `true` as well. If you are using mailcow and enabled IPv6 with the update.sh, you can keep their daemon.json, it will work too.

    ```json
    {
      "ipv6": true,
      "fixed-cidr-v6": "fd12:3456:789a:1::/64",
      "experimental": true,
      "ip6tables": true
    }
    ```

    Save the file.

2.  Reload the Docker configuration file.

    ```console
    sudo systemctl restart docker
    ```
3. Make sure that ipv6 is enabled for the internal `nextcloud-aio` network by running `sudo docker network inspect nextcloud-aio | grep EnableIPv6`. On a new instance, this command should return that it did not find a network with this name. Then you can run `sudo docker network create --subnet="fd12:3456:789a:2::/64" --driver bridge --ipv6 nextcloud-aio` in order to create the network with ipv6-support. However if it finds the network and its value `EnableIPv6` is set to false, make sure to follow https://github.com/nextcloud/all-in-one/discussions/2045 in order to recreate the network and enable ipv6 for it.

## Docker Desktop (Windows and macOS)
On Windows and macOS which use Docker Desktop, you need to go into the settings, and select `Docker Engine`. There you should see the currently used daemon.json file. 

1. You need to now adjust this json file by setting the `ipv6` key to `true` and the `fixed-cidr-v6` key to your IPv6 subnet. In this example we are setting it to `fd12:3456:789a:1::/64`. Additionally set `experimental` to `true` and `ip6tables` to `true` as well.

    ```
    "ipv6": true,
    "fixed-cidr-v6": "fd12:3456:789a:1::/64",
    "experimental": true,
    "ip6tables": true
    ```

2. Add these values to the json and make sure to keep the other currently values and that you don't see `Unexpected token in JSON at position ...` before attempting to restart by clicking on `Apply & restart`.
3. Make sure that ipv6 is enabled for the internal `nextcloud-aio` network by running `docker network inspect nextcloud-aio`. On a new instance, this command should return that it did not find a network with this name. Then you can run `docker network create --subnet="fd12:3456:789a:2::/64" --driver bridge --ipv6 nextcloud-aio` in order to create the network with ipv6-support. However if it finds the network and its value `EnableIPv6` is set to false, make sure to follow https://github.com/nextcloud/all-in-one/discussions/2045 in order to recreate the network and enable ipv6 for it.

---

**Note**: This is a copy of the original docker docs at https://docs.docker.com/config/daemon/ipv6/ which apparently are not correct. However experimental is set to true which the ip6tables feature needs. Thus it will not get included into the official docs. However it is needed to make it work in our testing.
