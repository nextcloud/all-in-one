# How to migrate from an already existing Nextcloud installation to Nextcloud AIO?

There are basically three ways how to migrate from an already existing Nextcloud installation to Nextcloud AIO:

1. Migrate only the files which is the easiest way
1. Migrate the files and the database which is much more complicated (and doesn't work on former snap installations)
1. Use the user_migration app that allows to migrate some of the user's data from a former instance to a new instance but needs to be done manually for each user

## Migrate only the files 
**Please note**: If you used groupfolders or encrypted your files before, you will need to restore the database, as well!

The procedure for migrating only the files works like this:
1. Take a backup of your former instance (especially from your datadirectory)
1. Install Nextcloud AIO on a new server/linux installation, enter your domain and wait until all containers are running
1. Recreate all users that were present on your former installation
1. Take a backup using Nextcloud AIO's built-in backup solution (so that you can easily restore to this state again) (Note: this will stop all containers and is expected: don't start the container again at this point!)
1. Restore the datadirectory of your former instance into the following directory: `/var/lib/docker/volumes/nextcloud_aio_nextcloud_data/_data/`
1. Next, run `sudo chown -R 33:0 /var/lib/docker/volumes/nextcloud_aio_nextcloud_data/_data/*` and `sudo chmod -R 750 /var/lib/docker/volumes/nextcloud_aio_nextcloud_data/_data/*` to apply the correct permissions
1. Start the containers again and wait until all containers are running
1. Run `sudo docker exec --user www-data -it nextcloud-aio-nextcloud php occ files:scan-app-data && sudo docker exec --user www-data -it nextcloud-aio-nextcloud php occ files:scan --all` in order to scan all files in the datadirectory.

## Migrate the files and the database
**Please note**: this is much more complicated than migrating only the files and also not as failproof so be warned! Also, this will not work on former snap installations as the snap is read-only and thus you cannot install the necessary `pdo_pgsql` PHP extension.

The procedure for migrating the files and the database works like this:
1. Make sure that your old instance is on exactly the same version like the version used in Nextcloud AIO. (e.g. 23.0.0) You can find the used version here: [click here](https://github.com/nextcloud/all-in-one/search?l=Dockerfile&q=NEXTCLOUD_VERSION&type=). If not, simply upgrade your former installation to that version or wait until the version used in Nextcloud AIO got updated to the same version of your former installation or the other way around.
1. Take a backup of your former instance (especially from your datadirectory and database)
1. If your former installation didn't use Postgresql already, you will now need to convert your old installation to use Postgresql as database temporarily (in order to be able to perform a pg_dump afterwards):
    1. Install Postgresql on your former installation: on a Debian based OS should the following command work:
        ```
        sudo apt update && sudo apt install postgresql -y
        ```
    1. Create a new database by running:
        ```
        export PG_USER="ncadmin"
        export PG_PASSWORD="my-temporary-password"
        export PG_DATABASE="nextcloud_db"
        sudo -u postgres psql <<END
        CREATE USER $PG_USER WITH PASSWORD '$PG_PASSWORD';
        CREATE DATABASE $PG_DATABASE WITH OWNER $PG_USER TEMPLATE template0 ENCODING 'UTF8';
        END
        ```
    1. Run the following command to start the conversion:
        ```
        occ db:convert-type --all-apps --password "$PG_PASSWORD" pgsql "$PG_USER" 127.0.0.1 "$PG_DATABASE"
        ```
        **Please note:** You might need to change the ip-address `127.0.0.1` and adjust the occ command (`occ`) based on your exact installation. Further information on the conversion is additionally available here: https://docs.nextcloud.com/server/stable/admin_manual/configuration_database/db_conversion.html#converting-database-type<br>
        **Troubleshooting:** If you get an error that it could not find a driver for the conversion, you most likely need to install the PHP extension `pdo_pgsql`.
    1. Hopefully does the conversion finish successfully. If not, simply restore your old Nextcloud installation from backup. If yes, you should now log in to your Nextcloud and test if everything works and if all data has been converted successfully.
    1. If everything works as expected, feel free to continue with the steps below.
1. Now, run a pg_dump to get an export of your current database. Something like the following command should work:
    ```
    sudo -Hiu postgres pg_dump "$PG_DATABASE"  > ./database-dump.sql
    ```
    **Please note:** The exact name of the database export file is important! (`database-dump.sql`)<br>
    And of course you need to to use the correct name that the Postgresql database has for the export (if `$PG_DATABASE` doesn't work directly).
1. At this point, you can finally install Nextcloud AIO on a new server/linux installation, enter your domain in the AIO interface (use the same domain that you used on your former installation) and wait until all containers are running. Then you should check the included Nextcloud version by running `sudo docker inspect nextcloud-aio-nextcloud | grep NEXTCLOUD_VERSION`.
1. Next, take a backup using Nextcloud AIO's built-in backup solution (so that you can easily restore to this state again) (Note: this will stop all containers and is expected: don't start the container again at this point!)
1. Now, we are slowly starting to import your files and database. First, you need to modify the datadirectory that is stored inside the database export:
    1. Find out what the directory of your old Nextcloud installation is by e.g. opening the config.php file and looking at the value `datadirectory`.
    1. Now, create a copy of the database file so that you can simply restore it if you should make a mistake while editing: `cp database-dump.sql database-dump.sql.backup`
    1. Next, open the database export with e.g. nano: `nano database-dump.sql`
    1. Press `[CTRL] + [w]` in order to open the search
    1. Type in `local::/your/old/datadir/` which should bring up the exact line where you need to modify the path to use the one used in Nextcloud AIO, instead.
    1. Change it to look like this: `local::/mnt/ncdata/`.
    1. Now save the file by pressing `[CTRL] + [o]` then `[ENTER]` and close nano by pressing `[CTRL] + [x]`
    1. In order to make sure that everything is good, you can now run `grep "/your/old/datadir" database-dump.sql` which should not bring up further results.
1. Next, copy the database dump into the correct place and prepare the database container which will import from the database dump automatically the next container start: 
    ```
    sudo rm /var/lib/docker/volumes/nextcloud_aio_database_dump/_data/database-dump.sql
    sudo cp database-dump.sql /var/lib/docker/volumes/nextcloud_aio_database_dump/_data/
    sudo chmod 777 /var/lib/docker/volumes/nextcloud_aio_database_dump/_data/database-dump.sql
    sudo rm /var/lib/docker/volumes/nextcloud_aio_database_dump/_data/initial-cleanup-done
    ```
1. If the commands above were executed successfully, restore the datadirectory of your former instance into your datadirectory: `/var/lib/docker/volumes/nextcloud_aio_nextcloud_data/_data/`. Be aware if you have changed the standard path of your datadirectory like described [here](https://github.com/nextcloud/all-in-one#how-to-change-the-default-location-of-nextclouds-datadir).
1. Next, run `sudo chown -R 33:0 /var/lib/docker/volumes/nextcloud_aio_nextcloud_data/_data/*` and `sudo chmod -R 750 /var/lib/docker/volumes/nextcloud_aio_nextcloud_data/_data/*`to apply the correct permissions on the datadirectory.
1. Edit the Nextcloud AIO config.php file that is stored in `/var/lib/docker/volumes/nextcloud_aio_nextcloud/_data/config/config.php` and modify only `passwordsalt`, `secret`, `instanceid` and set it to the old values that you used on your old installation. If you are brave, feel free to modify further values e.g. add your old LDAP config or S3 storage config. (Some things like Mail server config can be added back using Nextcloud's webinterface later on).
1. When you are done and saved your changes to the file, finally start the containers again and wait until all containers are running.
1. As last step, install all apps again that were installed before on your old instance by using the webinterface.

Now the whole Nextcloud instance should work again.<br>
If not, feel free to restore the AIO instance from backup and start at step 8 again.

## Use the user_migration app
A new way since the Nextcloud update to 24 is to use the new [user_migration app](https://apps.nextcloud.com/apps/user_migration#app-gallery). It allows to export the most important data on one instance and import it on a different Nextcloud instance. For that, you need to install and enable the user_migration app on your old instance, trigger the export for the user, create the user on the new instance, log in with that user and import the archive that was created during the export. This then needs to be done for each user that you want to migrate.
