# Multiple AIO instances
It is possible to run multiple instances of AIO on one server.

There are two ways to achieve this: The normal way is creating multiple VMs, installing AIO in [reverse proxy mode](./reverse-proxy.md) in each of them and having one reverse proxy in front of them that points to each VM (you also need to [use a different `TALK_PORT`](https://github.com/nextcloud/all-in-one#how-to-adjust-the-talk-port) for each of them). The second and more advanced way is creating multiple users on the server and using docker rootless for each of them in order to install multiple instances on the same server. 


## Run multiple AIO instances on the same server with docker rootless
1. Create as many linux users as you need first. The easiest way is to use `sudo adduser` and follow the setup for that. Make sure to create a strong unique password for each of them and write it down!
1. Log in as each of the users by opening a new SSH connection as the user and install docker rootless for each of them by following step 0-1 and 3-4 of the [docker rootless documentation](./docker-rootless.md) (you can skip step 2 in this case).
1. Then install AIO in reverse proxy mode by using the command that is described in step 2 and 3 of the [reverse proxy documentation](./reverse-proxy.md) but use a different `APACHE_PORT` and [`TALK_PORT`](https://github.com/nextcloud/all-in-one#how-to-adjust-the-talk-port) for each instance as otherwise it will bug out. Also make sure to adjust the docker socket and `WATCHTOWER_DOCKER_SOCKET_PATH` correctly for each of them by following step 6 of the [docker rootless documentation](./docker-rootless.md). Additionally, modify `--publish 8080:8080` to a different port for each container, e.g. `8081:8080` as otherwise it will not work.<br>
**⚠️ Please note:** If you want to adjust the `NEXTCLOUD_DATADIR`, make sure to apply the correct permissions to the chosen path as documented at the bottom of the [docker rootless documentation](./docker-rootless.md). Also for the built-in backup to work, the target path needs to have the correct permissions as documented there, too.
1. Now install your webserver of choice on the host system. It is recommended to use caddy for this as it is by far the easiest solution. You can do so by following https://caddyserver.com/docs/install#debian-ubuntu-raspbian or below. (It needs to be installed directly on the host or on a different server in the same network).
1. Next create your Caddyfile with multiple entries and domains for the different instances like described in step 1 of the [reverse proxy documentation](./reverse-proxy.md). Obviously each domain needs to point correctly to the chosen `APACHE_PORT` that you've configured before. Then start Caddy which should automatically get the needed certificates for you if your domains are configured correctly and ports 80 and 443 are forwarded to your server.
1. Now open each of the AIO interfaces by opening `https://ip.address.of.this.server:8080` or e.g. `https://ip.address.of.this.server:8081` or as chosen during step 3 of this documentation. 
1. Finally type in the domain that you've configured for each of the instances during step 5 of this documentation and you are done.
1. Please also do not forget to open/forward each chosen `TALK_PORT` UDP and TCP in your firewall/router as otherwise Talk will not work correctly!

Now everything should be set up correctly and you should have created multiple working instances of AIO on the same server!


## Run multiple AIO instances on the same server inside their own virtual machines
This guide will walk you through creating and configuring two (or more) Debian-based VMs (with "reverse proxy mode" Nextcloud AIO installed in each VM), behind one Caddy reverse proxy, all running on one host physical machine (like a laptop or desktop PC). It's highly recommend to follow the steps in order. Steps 1 through 4 will need to be repeated. Steps 5 through 8 only need to be completed once. All commands are expected to be run as root.

<details><summary><strong>PLEASE READ: A few expectations about your network</strong></summary>
This guide assumes that you have forwarded ports 443 and 8443 to your host physical machine via your router's configuration page, and either set up Dynamic DNS or obtained a static outbound IP address from your ISP. If this is not the case, or if you are brand-new to networking, you probably should not proceed with this guide, unless you are just using it for educational purposes. Proper network setup and security is critical when it comes to keeping your data safe. You may consider hosting using a VPS instead, or choosing one of <a href="https://nextcloud.com/providers/">Nextcloud's trusted providers.</a>
</details>

<details><summary><strong>A note for VPS users</strong></summary>
If you want to do this on a VPS, and your VPS is KVM-based and provides a static IP address, you can likely benefit from this guide too! Simply replace the words "host physical machine" with "VPS" and follow along.
</details>

**Before starting:** Make sure your host physical machine has enough resources. A host machine with 8GB RAM and 100GB storage is sufficient for running two fairly minimal VMs, with 2GB RAM and 32GB storage allocated to each VM. This guide assumes you have these resources at the minimum. This is fine for just testing the setup, but you will probably want to allocate more resources to your VMs if you plan to use this for day-to-day use.
If your host machine has more than 8GB memory available, and you plan to enable any of the optional containers (Nextcloud Office, Talk, Imaginary, etc.) in any of your instances, then you should definitely allocate more memory to the VM hosting that instance. In other words, before turning on any extra features inside a particular AIO interface, make sure you've first allocated enough resources to the VM that the instance is running inside. If in doubt, the AIO interface itself gives great recommendations for extra CPU and RAM allocation.

**Additional prerequisites:** Your host physical machine needs to have virtualization enabled in it's UEFI/BIOS. It also needs a few tools installed in order to create VMs. Assuming your host machine is a bare-bones Ubuntu or Debian Linux server without a desktop environment installed, the easiest way to create VMs is to install *QEMU*, *virsh*, *virt-install*, and a few extra packages to support UEFI booting and network config ([more info](https://wiki.debian.org/KVM)). You only need to do this once. To do this, run this command (**on the host physical machine**):
<!--
```shell
# For host machines running Ubuntu Server:
apt install --no-install-recommends qemu-system libvirt-clients libvirt-daemon-system virtinst ovmf bridge-utils
```
```shell
# For host machines running Debian:
apt install --no-install-recommends qemu-system qemu-utils libvirt-clients libvirt-daemon-system virtinst ovmf bridge-utils dnsmasq-base
```
-->
```shell
# For host machines running Ubuntu Server or Debian:
apt install --no-install-recommends qemu-system qemu-utils libvirt-clients libvirt-daemon-system virtinst ovmf bridge-utils dnsmasq-base
```

**Let's begin!** This guide assumes that you have two domains where you would like to host two individual AIO instances (one instance per domain). Let's call these domains `example1.com` and `example2.com`. Therefore, we'll create two VMs named `example1-com` and `example2-com` (These are the VM names we'll use below in step 1).

**Once you're ready, follow steps 1-4 below to set up your VMs. You will configure them one at a time.**

1. Choose a name for your VM. A good choice is to name each VM the same as the domain name that will be used to access it.
2. Choose the distribution you'd like to install within the VM:
   <details><summary><strong>Ubuntu Server 22.04.4 LTS</strong></summary>
       <h4>Downloading the .ISO image</h4>
       You must first download an .ISO image to your host machine, and then provide virt-install with the path to that image.
       <!-- This step is required because Ubuntu no longer hosts their "Legacy Ubuntu Server Installer" images, meaning we can no longer pass a URL to virt-install to use as a location. -->
       <pre><code># Skip this part if you've already downloaded this image
   curl -o /tmp/ubuntu-22.04.4-live-server-amd64.iso https://releases.ubuntu.com/jammy/ubuntu-22.04.4-live-server-amd64.iso
   </code></pre>
       <em>Note: You may choose a different place to store the .ISO file, but it needs to be somewhere accessible by QEMU. "/tmp" and "/home" work well, but choosing a location like "/root" will cause the next command to fail.</em>
       <h4>Creating the VM</h4>
       Now create the Ubuntu Server VM (Don't forget to replace [VM_NAME]):
       <pre><code>virt-install \
   --name [VM_NAME] \
   --virt-type kvm \
   --location /tmp/ubuntu-22.04.4-live-server-amd64.iso,kernel=casper/vmlinuz,initrd=casper/initrd \
   --os-variant ubuntujammy \
   --disk size=32 \
   --memory 2048 \
   --graphics none \
   --console pty,target_type=serial \
   --extra-args "console=ttyS0" \
   --autostart \
   --boot uefi
   </code></pre>
       <h4>Using a different version of Ubuntu Server</h4>
       To use a different Ubuntu Server release, visit <a href="https://releases.ubuntu.com">this page</a> and find the version you want. You will need to adjust the filename and URL for the curl command, and the location and os-variant for the virt-install command, accordingly.
   </details>
   <details><summary><strong>Debian 11</strong></summary>
       <h4>Creating the VM</h4>
       Create the Debian VM (Don't forget to replace [VM_NAME]):
       <pre><code>virt-install \
   --name [VM_NAME] \
   --virt-type kvm \
   --location http://deb.debian.org/debian/dists/bullseye/main/installer-amd64/ \
   --os-variant debian11 \
   --disk size=32 \
   --memory 2048 \
   --graphics none \
   --console pty,target_type=serial \
   --extra-args "console=ttyS0" \
   --autostart \
   --boot uefi
   </code></pre>
   </details>
   <details><summary><strong>Debian 12</strong></summary>
       <h4>Creating the VM</h4>
       Create the Debian VM (Don't forget to replace [VM_NAME]):
       <pre><code># If the os-variant "debian12" is unknown, try "debiantesting" instead
   virt-install \
   --name [VM_NAME] \
   --virt-type kvm \
   --location http://deb.debian.org/debian/dists/bookworm/main/installer-amd64/ \
   --os-variant debian12 \
   --disk size=32 \
   --memory 2048 \
   --graphics none \
   --console pty,target_type=serial \
   --extra-args "console=ttyS0" \
   --autostart \
   --boot uefi
   </code></pre>
   </details>
   <!--To learn more about virt-install or automating this process, see <a href="https://access.redhat.com/documentation/en-us/red_hat_enterprise_linux/7/html/virtualization_deployment_and_administration_guide/sect-guest_virtual_machine_installation_overview-creating_guests_with_virt_install">this guide</a>.-->
3. Navigate through the text-based installer. Most options can remain as default, but here are some tips:
   <details><summary><strong>For the Ubuntu Server installer</strong></summary>
   When asked about the "type of installation", you can leave the default "Ubuntu Server" without third-party drivers. You can leave the HTTP proxy information blank. In the "Profile Configuration" section, you can set "Your servers name" (hostname) to the same value as the name you gave to your VM (for example, "example1-com"). The installer will only let you create a non-root user. Note down the password you use here! You may skip enabling Ubuntu Pro. You can allow the partitioner to use the entire disk, this only uses the virtual disk that you defined above in step 2. You'll eventually be given the option to install additional software. Although "Nextcloud" is listed here, you almost certainly do <strong>not</strong> want to select this option, since you are setting up Nextcloud AIO. You'll be asked about installing "SSH server", this is entirely optional (This lets you easily SSH into the VM in the future in case you have to perform any maintenance, but even if you do not install an SSH server, you can still log in using the "virsh console" command). Finally, disregard the "[FAILED] Failed unmounting /cdrom." message, and press return.
   </details>
   <details><summary><strong>For the Debian installer</strong></summary>
   When asked, you can set the hostname to the same value as the name you gave to your VM (for example, "example1-com"). You can leave the domain name and HTTP proxy information blank. Allow the installer to create both a root and a non-root user. Note down the password(s) you use here! You can allow the partitioner to use the entire disk, this only uses the virtual disk that you defined above in step 2. When tasksel (Software selection) runs and asks if you want to install additional software, use spacebar and your arrow keys to un-check the "Debian desktop environment" and "GNOME" options. The "SSH server" option is entirely optional (This lets you easily SSH into the VM in the future in case you have to perform any maintenance, but even if you do not install an SSH server, you can still log in using the "virsh console" command). Make sure "standard system utilities" is also checked. Hit tab to select "Continue". Finally, disregard the warning about GRUB, allow it to install to your "primary drive" (again, it's only virtual, and this only applies to the VM- this will not affect the boot configuration of your host physical machine) and select "/dev/vda" for the bootable device.
   </details>
4. Configure your new VM:

   After it has finished installing, the VM will have rebooted and presented you with a login prompt. For Debian, just use `root` as the username, and enter the password you chose during the installation process. Ubuntu restricts root account access, so you'll need to first login with your non-root user, and then run `sudo su -` to elevate your privileges.

   We will now run a few commands to install docker and AIO in reverse proxy mode! As with any other commands, carefully read and try your best to understand them before running them.

   **Each time you reach this step and run the `docker run` command below, you'll need to increment the `TALK_PORT` value. For example: 3478, 3479, etc... You may use other values as long as they don't conflict, and make sure they are [greater than 1024](https://github.com/nextcloud/all-in-one/discussions/2517). Be sure to note down the Talk port number you've assigned to this VM/AIO instance. You will need it later if you decide to enable Nextcloud Talk.**

   Run these commands (**on the VM**):
   ```shell
   apt install -y curl
   
   curl -fsSL https://get.docker.com | sh

   # Make sure you increment the TALK_PORT value every time you run this!
   docker run \
   --init \
   --sig-proxy=false \
   --name nextcloud-aio-mastercontainer \
   --restart always \
   --publish 8080:8080 \
   --env APACHE_PORT=11000 \
   --env APACHE_IP_BINDING=0.0.0.0 \
   --env TALK_PORT=3478 \
   --volume nextcloud_aio_mastercontainer:/mnt/docker-aio-config \
   --volume /var/run/docker.sock:/var/run/docker.sock:ro \
   nextcloud/all-in-one:latest
   ```
   The last command may take a few minutes. When it's finished, you should see a success message, saying "Initial startup of Nextcloud All-in-One complete!". Now exit the console session with `Ctrl + [c]`. This concludes the setup for this particular VM.

   
   ---
6. Go ahead and run through steps 1-4 again in order to set up your second VM. When you're finished, proceed down to step 6. *(Note: If you downloaded the Ubuntu .ISO image and no longer need it, you may delete it now.)*
7. Almost done! All that's left is configuring your reverse proxy. To do this, you first need to [install it](https://caddyserver.com/docs/install#debian-ubuntu-raspbian). Run (**on the host physical machine**):
   ```shell
   apt update -y
   apt install -y debian-keyring debian-archive-keyring apt-transport-https curl
   curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
   curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | tee /etc/apt/sources.list.d/caddy-stable.list
   apt update -y
   apt install -y caddy
   ```
   These commands will ensure that your system is up-to-date and install the latest stable version of Caddy via it's official binary source.
8. To configure Caddy, you need to know the IP address assigned to each VM. Run (**on the host physical machine**):
    ```shell
    virsh net-dhcp-leases default
    ```
    This will show you the VMs you set up, and the IP address corresponding to each of them. Note down each IP and corresponding hostname.
    Finally, you will configure Caddy using this information. Open the default Caddyfile with a text editor:
    ```shell
    nano /etc/caddy/Caddyfile
    ```
    Replace everything in this file with the following configuration. Don't forget to edit this sample configuration and substitute in your own domain names and IP addresses. `[DOMAIN_NAME_*]` should be a domain name like `example1.com`, and `[IP_ADDRESS_*]` should be a local IPv4 address like `192.168.122.225`.
    ```shell
    # Virtual machine #1 - "example1-com"
    https://[DOMAIN_NAME_1]:8443 {
        reverse_proxy https://[IP_ADDRESS_1]:8080 {
            transport http {
                tls_insecure_skip_verify
            }
        }
    }
    https://[DOMAIN_NAME_1]:443 {
        reverse_proxy [IP_ADDRESS_1]:11000
    }
    
    # Virtual machine #2 - "example2-com"
    https://[DOMAIN_NAME_2]:8443 {
        reverse_proxy https://[IP_ADDRESS_2]:8080 {
            transport http {
                tls_insecure_skip_verify
            }
        }
    }
    https://[DOMAIN_NAME_2]:443 {
        reverse_proxy [IP_ADDRESS_2]:11000
    }

    # (Add more configurations here if you set up more than two VMs!)
    ```
    After making this change, you'll need to restart Caddy:
   ```shell
   systemctl restart caddy
   ```
9. That's it! Now, all that's left is to set up your instances through the AIO interface as usual by visiting `https://example1.com:8443` and `https://example2.com:8443` in a browser. Once you're finished going through each setup, you can access your new instances simply through their domain names. You can host as many instances with as many domain names as you want this way, as long as you have enough system resources. Enjoy!
    
    <details><summary><strong>A few extra tips for managing this setup</strong></summary>
        <ul>
            <li>You can easily connect to a VM to perform maintenance using this command (<strong>on the host physical machine</strong>): <pre><code>virsh console --domain [VM_NAME]</code></pre></li>
            <li>If you chose to install an SSH Server, you can SSH in using this command (<strong>on the host physical machine</strong>): <pre><code>ssh [NONROOT_USER]@[IP_ADDRESS] # By default, OpenSSH does not allow logging in as root</code></pre></li>
            <li>If you mess up the configuration of a VM, you may wish to completely delete it and start fresh with a new one. <strong>THIS WILL DELETE ALL DATA ASSOCIATED WITH THE VM INCLUDING ANYTHING IN YOUR AIO DATADIR!</strong> If you are sure you would like to do this, run (<strong>on the host physical machine</strong>): <pre><code>virsh destroy --domain [VM_NAME] ; virsh undefine --nvram --domain [VM_NAME] && rm -rfi /var/lib/libvirt/images/[VM_NAME].qcow2</code></pre></li>
            <li>Using Nextcloud Talk will require some extra configuration. Back when you set up your VMs, they were (by default) configured with NAT, meaning they are in their own subnet. The VMs must each instead be bridged, so that your router may directly "see" them (as if they were real, physical devices on your network), and each AIO instance inside each VM must be configured with a different Talk port (like 3478, 3479, etc.). You should have already set these port numbers (back when you first configured the VM in step 4 above), but if you still need to set (or want to change) these values, you can remove the mastercontainer and re-run the initial "docker run" command with a modified Talk port <a href="https://github.com/nextcloud/all-in-one#how-to-adjust-the-talk-port">like so</a>. Then, the Talk port for EACH instance needs to be forwarded in your router's settings DIRECTLY to the VM hosting the instance (completely bypassing your host physical machine/reverse proxy). And finally, inside an admin-privileged account (such as the default "admin" account) in each instance, you must visit <strong>https://[DOMAIN_NAME]/settings/admin/talk</strong> then find the STUN/TURN Settings, and from there set the proper values. If this is too complicated, it may be easier to use public STUN/TURN servers, but I have not tested any of this, rather I'm just sharing what I have found so far (more info available <a href="https://github.com/nextcloud/all-in-one/discussions/2517">here</a>). If you have figured this out or if any of this information is incorrect, please edit this section!</li>
            <li>Configuring daily automatic backups is a bit more involved with this setup. But for the occasional manual borg backup, you can connect a physical SSD/HDD via a cheap USB SATA adapter/dock to a free USB port on your host physical machine, and then use these commands to pass the disk through to a VM of your choosing (<strong>on the host physical machine and on the VM</strong>): <pre><code>virsh attach-device --live --domain [VM_NAME] --file [USB_DEVICE_DEFINITION.xml]
   virsh console --domain [VM_NAME]
   # (Login to the VM with root privileges)
   mkdir -p /mnt/[MOUNT_NAME]
   mount /dev/disk/by-label/[DISK_NAME] /mnt/[MOUNT_NAME]</code></pre></li>
            To create the XML device definition file, see <a href="https://access.redhat.com/documentation/en-us/red_hat_enterprise_linux/6/html/virtualization_administration_guide/sect-managing_guest_virtual_machines_with_virsh-attaching_and_updating_a_device_with_virsh">this short guide</a>. An SSD/HDD is recommended, but nothing is stopping you from using something as simple as a flash drive for testing if you really want. Finally, to actually perform a manual backup, make sure your disk is properly mounted and then simply use the AIO interface to perform the backup.
            <li>If you want to shave off around 8-10 seconds of total boot time when you reboot your host physical machine, a simple trick is to lower the GRUB_TIMEOUT from the default five seconds to one second, on both the host physical machine and each of the VMs. You can also remove the delay, but it's generally safer to leave at least one second. (Always be extremely careful when editing GRUB config, especially on the host physical machine, as an incorrect configuration can prevent your device from booting!)</li>
        </ul>
    </details>
