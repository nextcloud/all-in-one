# IPv6-Support for Docker

Before you can use IPv6 in Docker containers or swarm services, you need to enable IPv6 support in the Docker daemon. Afterward, you can choose to use either IPv4 or IPv6 (or both) with any container, service, or network.

## Docker on Linux and Docker-rootless
1.  Edit `/etc/docker/daemon.json` (or `~/.config/docker/daemon.json` in case of docker-rootless), set the `ipv6` key to `true` and the `fixed-cidr-v6` key to your IPv6 subnet. In this example we are setting it to `2001:db8:1::/64`. Additionally set `experimental` to `true` and `ip6tables` to `true` as well.

    ```json
    {
      "ipv6": true,
      "fixed-cidr-v6": "2001:db8:1::/64",
      "experimental": true,
      "ip6tables": true
    }
    ```

    Save the file.

2.  Reload the Docker configuration file.

    ```console
    sudo systemctl restart docker
    ```

## Docker Desktop (Windows and macOS)
On Windows and macOS which use Docker Desktop, you need to go into the settings, and select `Docker Engine`. There you should see the currently used daemon.json file. 

1. You need to now adjust this json file by setting the `ipv6` key to `true` and the `fixed-cidr-v6` key to your IPv6 subnet. In this example we are setting it to `2001:db8:1::/64`. Additionally set `experimental` to `true` and `ip6tables` to `true` as well.

    ```
    "ipv6": true,
    "fixed-cidr-v6": "2001:db8:1::/64",
    "experimental": true,
    "ip6tables": true
    ```

2. Add these values to the json and make sure to keep the other currently values and that you don't see `Unexpected token in JSON at position ...` before attempting to restart by clicking on `Apply & restart`.

---

**Note**: This is a copy of the original docker docs at https://docs.docker.com/config/daemon/ipv6/ which apparently are not correct. However experimental is set to true which the ip6tables feature needs. Thus it will not get included into the official docs. However it is needed to make it work in our testing.
