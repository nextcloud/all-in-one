# IPv6-Support for Docker

**Note**: IPv6 networking is only supported on Docker daemons running on Linux hosts. So it is neither supported on Windows nor on macOS.

Before you can use IPv6 in Docker containers or swarm services, you need to enable IPv6 support in the Docker daemon. Afterward, you can choose to use either IPv4 or IPv6 (or both) with any container, service, or network.

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

You can now create networks with the `--ipv6` flag and assign containers IPv6 addresses using the `--ip6` flag.

**Note**: This is a copy of the original docker docs at https://docs.docker.com/config/daemon/ipv6/ which apparently are not correct. However experimental is set to true which the ip6tables feature needs. Thus it will not get included into the official docs. However it is needed to make it work in our testing.
