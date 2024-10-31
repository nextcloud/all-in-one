# Docker rootless

**Please note:** Due to a bug in Collabora is the Collabora container currently in rootless mode not working. See https://github.com/CollaboraOnline/online/issues/2800. In that case, you need to run a separate Collabora instance on your own if you want to use this feature. The following flag will be useful https://github.com/nextcloud/all-in-one#how-to-keep-disabled-apps.

You can run AIO with docker rootless by following the steps below.

0. If docker is already installed, you should consider disabling it first: (`sudo systemctl disable --now docker.service docker.socket`)
1. Install docker rootless by following the official documentation: https://docs.docker.com/engine/security/rootless/#install. The easiest way is installing it **Without packages** (`curl -fsSL https://get.docker.com/rootless | sh`). Further limitations, distribution specific hints, etc. are discussed on the same site. Also do not forget to enable the systemd service, which may not be enabled always by default. See https://docs.docker.com/engine/security/rootless/#usage. (`systemctl --user enable docker`)
1. If you need ipv6 support, you should enable it by following https://github.com/nextcloud/all-in-one/blob/main/docker-ipv6-support.md.
1. Do not forget to set the mentioned environmental variables `PATH` and `DOCKER_HOST` and in best case add them to your `~/.bashrc` file as shown!
1. Also do not forget to run `loginctl enable-linger USERNAME` (and substitute USERNAME with the correct one) in order to make sure that user services are automatically started after every reboot.
1. Expose the privileged ports by following https://docs.docker.com/engine/security/rootless/#exposing-privileged-ports. (`sudo setcap cap_net_bind_service=ep $(which rootlesskit); systemctl --user restart docker`). If you require the correct source IP you must expose them via `/etc/sysctl.conf`, [see note below](#note-regarding-docker-network-driver).
1. Use the official AIO startup command but use `--volume $XDG_RUNTIME_DIR/docker.sock:/var/run/docker.sock:ro` instead of `--volume /var/run/docker.sock:/var/run/docker.sock:ro` and also add `--env WATCHTOWER_DOCKER_SOCKET_PATH=$XDG_RUNTIME_DIR/docker.sock` to the initial container startup (which is needed for mastercontainer updates to work correctly). When you are using Portainer to deploy AIO, the variable `$XDG_RUNTIME_DIR` is not available. In this case, it is necessary to manually add the path (e.g. `/run/user/1000/docker.sock`) to the Docker compose file to replace the `$XDG_RUNTIME_DIR` variable. If you are not sure how to get the path, you can run on the host: `echo $XDG_RUNTIME_DIR`.
1. Now everything should work like without docker rootless. You can consider using docker-compose for this or running it behind a reverse proxy. Basically the only thing that needs to be adjusted always in the startup command or compose.yaml file (after installing docker rootles) are things that are mentioned in point 3.
1. ⚠️ **Important:** Please read through all notes below!

### Note regarding sudo in the documentation
Almost all commands in this project's documentation use `sudo docker ...`. Since `sudo` is not needed in case of docker rootless, you simply remove `sudo` from the commands and they should work. 

### Note regarding permissions
All files outside the containers get created, written to and accessed as the user that is running the docker daemon or a subuid of it. So for the built-in backup to work you need to allow this user to write to the target directory. E.g. with `sudo chown -R USERNAME:GROUPNAME /mnt/backup`. The same applies when changing Nextcloud's datadir via NEXTCLOUD_DATADIR. E.g. `sudo chown -R USERNAME:GROUPNAME /mnt/ncdata`. When you want to use the NEXTCLOUD_MOUNT option for local external storage, you need to adjust the permissions of the chosen folders to be accessible/writeable by the userid `100032:100032` (if running `grep ^$(whoami): /etc/subuid` as the user that is running the docker daemon returns 100000 as first value). 


### Note regarding docker network driver
By default rootless docker uses the `slirp4netns` IP driver and the `builtin` port driver. As mentioned in [the documentation](https://docs.docker.com/engine/security/rootless/#networking-errors), this combination doesn't provide "Source IP propagation". This means that Apache and Nextcloud will see all connections as coming from the docker gateway (e.g 172.19.0.1), which can lead to the Nextcloud brute force protection blocking all connection attempts. To expose the correct source IP, you will need to configure docker to also use `slirp4netns` as the port driver (see also [this guide](https://rootlesscontaine.rs/getting-started/docker/#changing-the-port-forwarder)).
As stated in the documentation, this change will likely lead to decreased network throughput. You should test this by trying to transfer a large file after completing your setup and revert back to the `builtin` port driver if the throughput is too slow.
* Add `net.ipv4.ip_unprivileged_port_start=80` to `/etc/sysctl.conf`. Editing this file requires root privileges. (using capabilities doesn't work here; see [this issue](https://github.com/rootless-containers/slirp4netns/issues/251#issuecomment-761415404)).
* Run `sudo sysctl --system` to propagate the change.
* Create `~/.config/systemd/user/docker.service.d/override.conf`
  with the following content:
  ```
  [Service]
  Environment="DOCKERD_ROOTLESS_ROOTLESSKIT_NET=slirp4netns"
  Environment="DOCKERD_ROOTLESS_ROOTLESSKIT_PORT_DRIVER=slirp4netns"
  ```
* Restart the docker daemon
  ```
  systemctl --user restart docker
  ```
