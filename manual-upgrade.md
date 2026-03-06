# Manual upgrade

If you do not update Nextcloud AIO for a long time (6+ months), when you eventually update in the AIO interface you will find Nextcloud no longer works. This is due to incompatible PHP versions within the nextcloud container.
There is unfortunately no way to fix this from a maintainer POV if you refrain from upgrading for so long.

The only way to fix this on your side is upgrading regularly (e.g. by enabling daily backups which will also automatically upgrade all containers) and following the steps below to get back to a normal state:

---

## Method 1 using `assaflavie/runlike`

> [!Warning]
> Please note that this method is apparently currently broken. See https://help.nextcloud.com/t/manual-upgrade-keeps-failing/217164/10
> So please refer to method 2 using Portainer.

1. Start all containers from the AIO interface 
    - Now, it will report that Nextcloud is restarting because it is not able to start due to the above mentioned problem
    - #### Do **not** click on `Stop containers` because you will need them running going forward, see below
2. Find out with which PHP version your installed Nextcloud is compatible by running `sudo docker exec nextcloud-aio-nextcloud cat lib/versioncheck.php`. 
    - There you will find information about the max. supported PHP version
    - **Make a mental note of this**
3. Stop the Nextcloud container and the Apache container by running 
    ```bash
        sudo docker stop nextcloud-aio-nextcloud && sudo docker stop nextcloud-aio-apache
    ```
4. Run the following commands in order to reverse engineer the Nextcloud container:
    ```bash
        sudo docker pull assaflavie/runlike
        echo '#!/bin/bash' > /tmp/nextcloud-aio-nextcloud
        sudo docker run --rm -v /var/run/docker.sock:/var/run/docker.sock assaflavie/runlike -p nextcloud-aio-nextcloud >> /tmp/nextcloud-aio-nextcloud
        sudo chown root:root /tmp/nextcloud-aio-nextcloud
    ```
5. Now open `/tmp/nextcloud-aio-nextcloud` with a text editor, and edit the container tag:


| To change                              | Replace with                                        |
|----------------------------------------|-----------------------------------------------------|
| `ghcr.io/nextcloud-releases/aio-nextcloud:latest`       | `ghcr.io/nextcloud-releases/aio-nextcloud:php{version}-latest`       |
| `ghcr.io/nextcloud-releases/aio-nextcloud:latest-arm64` | `ghcr.io/nextcloud-releases/aio-nextcloud:php{version}-latest-arm64` |



 - e.g. `ghcr.io/nextcloud-releases/aio-nextcloud:php8.0-latest` or `ghcr.io/nextcloud-releases/aio-nextcloud:php8.0-latest-arm64`
 - However, if you are unsure check the ghcr.io (https://github.com/nextcloud-releases/all-in-one/pkgs/container/aio-nextcloud/versions?filters%5Bversion_type%5D=tagged) and docker hub: https://hub.docker.com/r/nextcloud/aio-nextcloud/tags?name=php
 - Using nano and the arrow keys to navigate:
  - `sudo nano /tmp/nextcloud-aio-nextcloud` making changes as above, then `[Ctrl]+[o]` -> `[Enter]` and `[Ctrl]+[x]` to save and exit.
6. Next, stop and remove the current container: 
    ```bash
        sudo docker stop nextcloud-aio-nextcloud
        sudo docker rm nextcloud-aio-nextcloud
    ```
7. Now start the Nextcloud container with the new tag by simply running `sudo bash /tmp/nextcloud-aio-nextcloud` which at startup should automatically upgrade Nextcloud to a more recent version. If not, make sure that there is no `skip.update` file in the Nextcloud datadir. If there is such a file, simply delete the file and restart the container again.<br>
**Info**: You can open the Nextcloud container logs with `sudo docker logs -f nextcloud-aio-nextcloud`.
8. After the Nextcloud container is started (you can tell by looking at the logs), simply restart the container again with `sudo docker restart nextcloud-aio-nextcloud` until it does not install a new Nextcloud update anymore upon the container startup.
9. Now, you should be able to use the AIO interface again by simply stopping the AIO containers and starting them again which should finally bring up your instance again.
10. If not and if you get the same error again, you may repeat the process starting from the beginning again until your Nextcloud version is finally up-to-date.
11. Now, if everything is finally running as usual again, it is recommended to create a backup in order to save the current state. Consider enabling daily backups if doing regular upgrades is a hassle for you. 

---

## Method 2 using Portainer
#### *Approach using portainer if method 1 does not work for you*

Prerequisite: have all containers from AIO interface running.

##### 1. Install portainer if not installed:
```bash
docker volume create portainer_data
docker run -d -p 8000:8000 -p 9443:9443 --name portainer --restart=always -v /var/run/docker.sock:/var/run/docker.sock -v portainer_data:/data portainer/portainer-ce:latest
```
- If you have a reverse proxy
    - you can setup and navigate using a domain name.
- For the **standard** AIO install
    - Open port 9443 on your firewall
    - navigate to `https://<server-ip>:9443`
- Accept the insecure self-signed certificate and set an admin password
- If prompted to add an environment
    - add local

##### 2. Within the local portainer environment navigate to the **containers** tab 
- Here you should see all the various containers running

##### 3. Now we need to stop the `nextcloud-aio-nextcloud` and `nextcloud-aio-apache` containers

-  This can be done by selecting the checkbox's next to the containers' name and clicking the **Stop** button at the top
    - or you can click into individual containers and stop them there

##### 4. Find the version of PHP compatible with the running nextcloud container
- navigate to ```nextcloud-aio-nextcloud``` and click on ```logs```, you should see something along the lines of:
```logs
This version of nextcloud is not compatible with >=php 8.2, you are currently running php 8.2.18
```
Make **note** of the version which is compatible, rounding down to 1 digit after the dot. 
 - In this example we would want php 8.1 since anything with 8.2 or above is incompatible

##### 5. Find the correct container version
In general it should be ```ghcr.io/nextcloud-releases/aio-nextcloud:php8.x-latest-arm64``` or `ghcr.io/nextcloud-releases/aio-nextcloud:php8.x-latest` replacing `x` with the version you require.
However, if you are unsure check the ghcr.io (https://github.com/nextcloud-releases/all-in-one/pkgs/container/aio-nextcloud/versions?filters%5Bversion_type%5D=tagged) and docker hub: https://hub.docker.com/r/nextcloud/aio-nextcloud/tags?name=php

##### 6. Replace the container
- Navigate to the ```nextcloud-aio-nextcloud``` container within portainer
- Click ```Duplicate/Edit```
- Within image, change this to the correct version from Step 5
- Click ```Deploy the container```
    - if you are prompted to force repull the image click the slider and press pull image

*Navigate to the nextcloud-aio-nextcloud logs and you will see the container updating*

Once you see no more activities in the logs or a message like ```NOTICE: ready to handle connections```, we've done it!

#### Now you can handle everything through the AIO interface and stop and restart the containers normally.

---

##### 7. Last Step is removing portainer if you don't want to keep it

```bash
docker stop portainer
docker rm portainer
docker volume rm portainer_data
```
- Make sure you close port 9443 on your firewall and delete any necessary reverse proxy hosts.
