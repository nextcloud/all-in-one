# Manual upgrade

If you do not install any upgrade for around 6-12 months or longer, it can happen that your instance is so outdated that in the meantime the PHP version of the Nextcloud container got bumped to a version that is not compatible with your currently installed Nextcloud version which means that after doing an upgrade after this long time, Nextcloud will suddenly not work anymore. There is unfortunately no way to fix this from the maintainer side if you refrain from upgrading for so long.

The only way to fix this on your side is upgrading regularly (e.g. by enabling daily backups which will also automatically upgrade all containers) and following the steps below:

1. Start all containers from the aio interface (now, it will report that Nextcloud is restarting because it is not able to start due to the above mentioned problem)
1. Do **not** click on `Stop containers` because you will need them running going forward, see below
1. Stop the Nextcloud container and the Apache container by running `sudo docker stop nextcloud-aio-nextcloud && sudo docker stop nextcloud-aio-apache`.
1. Find out with which PHP version your installed Nextcloud is compatible by running `sudo cat /var/lib/docker/volumes/nextcloud_aio_nextcloud/_data/lib/versioncheck.php`. (There you will find information about the max. supported PHP version.)
1. Run the following commands in order to reverse engineer the Nextcloud container:
    ```bash
        sudo docker pull assaflavie/runlike
        echo '#/bin/bash' > /tmp/nextcloud-aio-nextcloud
        sudo docker run --rm -v /var/run/docker.sock:/var/run/docker.sock assaflavie/runlike -p nextcloud-aio-nextcloud >> /tmp/nextcloud-aio-nextcloud
        sudo chown root:root /tmp/nextcloud-aio-nextcloud
    ```
1. Now open the file with e.g. nano: `sudo nano /tmp/nextcloud-aio-nextcloud` and change the line that should probably be `nextcloud/aio-nextcloud:latest` on x64 or `nextcloud/aio-nextcloud:latest-arm64` on arm64 to the highest compatible PHP version: E.g. `nextcloud/aio-nextcloud:php8.0-latest` on x64 or `nextcloud/aio-nextcloud:php8.0-latest-arm64` on arm64. Then save the file and close it with `[Ctrl]+[o]` -> `[Enter]` and `[Ctrl]+[x]`.
1. After doing so, remove the Nextcloud container with `sudo docker rm nextcloud-aio-nextcloud`.
1. Now start the Nextcloud container with the new tag by simply running `sudo bash /tmp/nextcloud-aio-nextcloud` which at startup should automatically upgrade Nextcloud to a more recent version. If not, make sure that there is no `skip.update` file in the Nextcloud datadir. If there is such a file, simply delete the file and restart the container again.<br>
**Info**: You can open the Nextcloud container logs with `sudo docker logs -f nextcloud-aio-nextcloud`.
1. After the Nextcloud container is started (you can tell by looking at the logs), simply restart the container again with `sudo docker restart nextcloud-aio-nextcloud` until it does not install a new Nextcloud update anymore upon the container startup.
1. Now, you should be able to use the AIO interface again by simply stopping the AIO containers and starting them again which should finally bring up your instance again.
1. If not and if you get the same error again, you may repeat the process starting from the beginning again until your Nextcloud version is finally up-to-date.
1. Now, if everything is finally running as usual again, it is recommended to create a backup in order to save the current state. Also you should think about enabling daily backups if doing regularl upgrades is too much effort for you.
