# How to migrate from an already existing Nextcloud installation to Nextcloud AIO?

There are basically three ways how to migrate from an already existing Nextcloud installation to Nextcloud AIO (if you ran AIO on the former installation already, you can follow [these steps](https://github.com/nextcloud/all-in-one#how-to-migrate-from-aio-to-aio)):

1. Migrate only the files which is the easiest way (this excludes all calendar data for example)
1. Migrate the files and the database which is much more complicated (with special handling required for former snap installations, see [below](#migrating-from-a-snap-installation))
1. Use the user_migration app that allows to migrate some of the user's data from a former instance to a new instance but needs to be done manually for each user

## Migrate only the files 
**Please note**: If you used groupfolders or encrypted your files before, you will need to restore the database, as well! (This will also exclude all calendar data for example).

The procedure for migrating only the files works like this:
1. Take a backup of your former instance (especially from your datadirectory, see `'datadirectory'` in your `config.php`)
1. Install Nextcloud AIO on a new server/linux installation, enter your domain and wait until all containers are running
1. Recreate all users that were present on your former installation
1. Take a backup using Nextcloud AIO's built-in backup solution (so that you can easily restore to this state again) (Note: this will stop all containers and is expected: don't start the container again at this point!)
1. Restore the datadirectory of your former instance: for `/path/to/old/nextcloud/data/` run `sudo docker cp --follow-link /path/to/old/nextcloud/data/. nextcloud-aio-nextcloud:/mnt/ncdata/` Note: the `/.` and `/` at the end are necessary.
1. Next, run `sudo docker run --rm --volume nextcloud_aio_nextcloud_data:/mnt/ncdata:rw alpine chown -R 33:0 /mnt/ncdata/` and `sudo docker run --rm --volume nextcloud_aio_nextcloud_data:/mnt/ncdata:rw alpine chmod -R 750 /mnt/ncdata/` to apply the correct permissions. (Or if `NEXTCLOUD_DATADIR` was provided, apply `chown -R 33:0` and `chmod -R 750` to the chosen path.)
1. Start the containers again and wait until all containers are running
1. Run `sudo docker exec --user www-data -it nextcloud-aio-nextcloud php occ files:scan-app-data && sudo docker exec --user www-data -it nextcloud-aio-nextcloud php occ files:scan --all` in order to scan all files in the datadirectory.
1. If the restored data is older than any clients you want to continue to sync, for example if the server was down for a period of time during migration, you may want to take a look at [Synchronising with clients after migration](/migration.md#synchronising-with-clients-after-migration) below.

## Migrate the files and the database
**Please note**: this is much more complicated than migrating only the files and also not as failproof so be warned! If you are migrating from a snap installation, please first follow the [dedicated snap migration steps](#migrating-from-a-snap-installation) below, which show you how to perform the database conversion using a temporary Docker container. Once done, you can continue from step 5 of this guide.

The procedure for migrating the files and the database works like this:
1. Make sure that your old instance is on exactly the same version like the version used in Nextcloud AIO. (e.g. 23.0.0) You can find the used version here: [click here](https://github.com/nextcloud/all-in-one/search?l=Dockerfile&q=NEXTCLOUD_VERSION&type=). If not, simply upgrade your former installation to that version or wait until the version used in Nextcloud AIO got updated to the same version of your former installation or the other way around.
1. First, on the old instance, update all Nextcloud apps to its latest version via the app management site (important for the restore later on). Then take a backup of your former instance (especially from your datadirectory and database).
1. If your former installation didn't use Postgresql already, you will now need to convert your old installation to use Postgresql as database temporarily (in order to be able to perform a pg_dump afterwards):
    1. Install Postgresql on your former installation: on a Debian based OS should the following command work:
        ```
        sudo apt update && sudo apt install postgresql -y
        ```
    1. Create a new database by running:
        ```
        export PG_USER="ncadmin" # This is a temporary user that gets created for the dump but is then overwritten by the correct one later on
        export PG_PASSWORD="my-temporary-password"
        export PG_DATABASE="nextcloud_db"
        sudo -u postgres psql <<END
        CREATE USER $PG_USER WITH PASSWORD '$PG_PASSWORD' CREATEDB;
        CREATE DATABASE $PG_DATABASE WITH OWNER $PG_USER TEMPLATE template0 ENCODING 'UTF8';
        GRANT ALL PRIVILEGES ON DATABASE $PG_DATABASE TO $PG_USER;
        GRANT ALL PRIVILEGES ON SCHEMA public TO $PG_USER;
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
1. At this point, you can finally install Nextcloud AIO on a new server/linux installation, enter your domain in the AIO interface (use the same domain that you used on your former installation) and wait until all containers are running. Then you should check the included Nextcloud version by running `sudo docker inspect nextcloud-aio-nextcloud | grep NEXTCLOUD_VERSION`. On the AIO interface, use the passphrase to connect to your newly created Nextcloud instance's admin account. There, install all the Nextcloud apps that were installed on the old Nextcloud installation. If you don't, the migration will show them as installed, but they won't work.
1. Next, take a backup using Nextcloud AIO's built-in backup solution (so that you can easily restore to this state again). Once finished, all containers are automatically stopped and is expected: **don't start the container again at this point!**
1. Now, with the containers still stopped, we are slowly starting to import your files and database. First, you need to modify the datadirectory that is stored inside the database export:
    1. Find out what the directory of your old Nextcloud installation is by e.g. opening the config.php file and looking at the value `datadirectory`.
    1. Now, create a copy of the database file so that you can simply restore it if you should make a mistake while editing: `cp database-dump.sql database-dump.sql.backup`
    1. Next, open the database export with e.g. nano: `nano database-dump.sql`
    1. Press `[CTRL] + [w]` in order to open the search
    1. Type in `local::/your/old/datadir/` which should bring up the exact line where you need to modify the path to use the one used in Nextcloud AIO, instead.
    1. Change it to look like this: `local::/mnt/ncdata/`.
    1. Now save the file by pressing `[CTRL] + [o]` then `[ENTER]` and close nano by pressing `[CTRL] + [x]`
    1. In order to make sure that everything is good, you can now run `grep "/your/old/datadir" database-dump.sql` which should not bring up further results.<br>
    1. **Please note:** Unfortunately it is not possible to import a database dump from a former database owner with the name `nextcloud`. You can check if that is the case with this command: `grep "Name: oc_appconfig; Type: TABLE; Schema: public; Owner:" database-dump.sql | grep -oP 'Owner:.*$' | sed 's|Owner:||;s| ||g'`. If it returns `nextcloud`, you need to rename the owner in the dump file manually. A command like the following should work, however please note that it is possible that it will overwrite wrong lines. You can thus first check which lines it will change with `grep "Owner: nextcloud$" database-dump.sql`. If only correct looking lines get returned, feel free to change them with `sed -i 's|Owner: nextcloud$|Owner: ncadmin|' database-dump.sql`.
The same applies for the second statement, check with `grep " OWNER TO nextcloud;$" database-dump.sql` and replace with `sed -i 's| OWNER TO nextcloud;$| OWNER TO ncadmin;|' database-dump.sql`.
1. Next, copy the database dump into the correct place and prepare the database container which will import from the database dump automatically the next container start: 
    ```
    sudo docker run --rm --volume nextcloud_aio_database_dump:/mnt/data:rw alpine rm /mnt/data/database-dump.sql
    sudo docker cp database-dump.sql nextcloud-aio-database:/mnt/data/
    sudo docker run --rm --volume nextcloud_aio_database_dump:/mnt/data:rw alpine chmod 777 /mnt/data/database-dump.sql
    sudo docker run --rm --volume nextcloud_aio_database_dump:/mnt/data:rw alpine rm /mnt/data/initial-cleanup-done
    ```
1. If the commands above were executed successfully, restore the datadirectory of your former instance into your datadirectory: `sudo docker run --rm --volume nextcloud_aio_nextcloud_data:/mnt/ncdata:rw alpine sh -c "rm -rf /mnt/ncdata/*"` and `sudo docker cp --follow-link /path/to/nextcloud/data/. nextcloud-aio-nextcloud:/mnt/ncdata/` Note: the `/.` and `/` at the end are necessary. (Or if `NEXTCLOUD_DATADIR` was provided, first delete the files in there and then copy the files to the chosen path.)
1. Next, run `sudo docker run --rm --volume nextcloud_aio_nextcloud_data:/mnt/ncdata:rw alpine chown -R 33:0 /mnt/ncdata/` and `sudo docker run --rm --volume nextcloud_aio_nextcloud_data:/mnt/ncdata:rw alpine chmod -R 750 /mnt/ncdata/` to apply the correct permissions on the datadirectory. (Or if `NEXTCLOUD_DATADIR` was provided, apply `chown -R 33:0` and `chmod -R 750` to the chosen path.)
1. Edit the Nextcloud AIO config.php file using `sudo docker run -it --rm --volume nextcloud_aio_nextcloud:/var/www/html:rw alpine sh -c "apk add --no-cache nano && nano /var/www/html/config/config.php"` and modify only `passwordsalt`, `secret`, `instanceid` and set it to the old values that you used on your old installation. If you are brave, feel free to modify further values e.g. add your old LDAP config or S3 storage config. (Some things like Mail server config can be added back using Nextcloud's webinterface later on).
1. When you are done and saved your changes to the file, finally start the containers again and wait until all containers are running.

Now the whole Nextcloud instance should work again.<br>
If not, feel free to restore the AIO instance from backup and start at step 8 again.

If the restored data is older than any clients you want to continue to sync, for example if the server was down for a period of time during migration, you may want to take a look at [Synchronising with clients after migration](/migration.md#synchronising-with-clients-after-migration) below.

### Migrating from a snap installation

**Disclaimer:** it might be possible that the guide below is not working 100% correctly, yet. Improvements to it are very welcome!

Since the Nextcloud snap is read-only, it is not possible to install the `pdo_pgsql` PHP extension inside the snap to perform the MySQL-to-PostgreSQL database conversion required by AIO. As a workaround, a temporary [nextcloud/docker](https://github.com/nextcloud/docker) container can be used as an intermediate environment that already includes `pdo_pgsql` and can convert the snap's MySQL database to PostgreSQL for you.

This procedure covers steps 1–3 of the regular migration above (version matching, app updates, and database conversion) and also produces the `database-dump.sql` needed for step 4. Once finished, continue from step 5 of the [Migrate the files and the database](#migrate-the-files-and-the-database) procedure above.

1. **Create a backup of the snap before doing anything else**, so you can restore the snap to its current state if anything goes wrong:
    ```
    sudo snap save nextcloud
    ```
    This creates a snapshot that can be restored later with `sudo snap restore <snapshot-id>`. The snapshot ID is shown in the output of `snap save`. You can also list existing snapshots with `snap saved`.
1. Note the exact Nextcloud version of your snap installation:
    ```
    sudo nextcloud.occ -V
    ```
1. Make sure that this version matches exactly the version used in Nextcloud AIO. You can find the AIO version here: [click here](https://github.com/nextcloud/all-in-one/search?l=Dockerfile&q=NEXTCLOUD_VERSION&type=). If they do not match, upgrade your snap with `sudo snap refresh nextcloud --channel=<major-version>/stable` or wait for AIO to be updated to the same version.
1. Update all installed Nextcloud apps to their latest versions:
    ```
    sudo nextcloud.occ app:update --all
    ```
1. Retrieve the necessary configuration values from the snap using `nextcloud.occ` and store them in environment variables. Do this **before** stopping the snap, as `nextcloud.occ` requires the snap services to be running:
    ```
    export INSTANCEID=$(sudo nextcloud.occ config:system:get instanceid)
    export PASSWORDSALT=$(sudo nextcloud.occ config:system:get passwordsalt)
    export SECRET=$(sudo nextcloud.occ config:system:get secret)
    export TABLE_PREFIX=$(sudo nextcloud.occ config:system:get dbtableprefix || echo "oc_")
    export SNAP_DATA=$(sudo nextcloud.occ config:system:get datadirectory)
    # Note down SNAP_DATA — you will need it later when copying files
    echo "Snap data directory: $SNAP_DATA"
    ```
1. Export a dump of the snap's MySQL database:
    ```
    sudo nextcloud.mysqldump > ~/mysql-dump.sql
    ```
1. Stop the snap to prevent further writes during the migration:
    ```
    sudo snap stop nextcloud
    ```
1. Set up environment variables for the temporary containers (adjust the version and passwords as needed):
    ```
    export NEXTCLOUD_VERSION="29.0.0"  # Replace with the exact version from step 2
    export MYSQL_PASSWORD="mysql-temp-password"
    export PG_USER="ncadmin"
    export PG_PASSWORD="my-temporary-pg-password"
    export PG_DATABASE="nextcloud_db"
    ```
1. Create a Docker network for the temporary migration containers:
    ```
    docker network create nextcloud-migration
    ```
1. Start a temporary MySQL container and import the snap database dump into it:
    ```
    docker run -d \
      --name mysql-migration \
      --network nextcloud-migration \
      -e MYSQL_ROOT_PASSWORD="mysql-root-temp" \
      -e MYSQL_DATABASE="nextcloud" \
      -e MYSQL_USER="nextcloud" \
      -e MYSQL_PASSWORD="$MYSQL_PASSWORD" \
      mysql:8
    # Wait for MySQL to finish starting up before importing
    until docker exec mysql-migration mysqladmin ping -h localhost --silent 2>/dev/null; do sleep 1; done
    docker exec -i mysql-migration mysql -u nextcloud -p"$MYSQL_PASSWORD" nextcloud < ~/mysql-dump.sql
    ```
1. Start a temporary PostgreSQL container as the migration target:
    ```
    docker run -d \
      --name postgres-migration \
      --network nextcloud-migration \
      -e POSTGRES_USER="$PG_USER" \
      -e POSTGRES_PASSWORD="$PG_PASSWORD" \
      -e POSTGRES_DB="$PG_DATABASE" \
      postgres:16
    ```
1. Create a temporary config file for the migration container using the values retrieved in step 5:
    ```
    cat > /tmp/migration-config.php << EOF
    <?php
    \$CONFIG = array(
      'instanceid' => '$INSTANCEID',
      'passwordsalt' => '$PASSWORDSALT',
      'secret' => '$SECRET',
      'dbtype' => 'mysql',
      'dbname' => 'nextcloud',
      'dbhost' => 'mysql-migration',
      'dbport' => '',
      'dbtableprefix' => '$TABLE_PREFIX',
      'dbuser' => 'nextcloud',
      'dbpassword' => '$MYSQL_PASSWORD',
      'datadirectory' => '$SNAP_DATA',
      'installed' => true,
    );
    EOF
    ```
1. Run a temporary nextcloud/docker container to convert the MySQL database to PostgreSQL. Note that the container image version must match the Nextcloud version you noted in step 2, and that `pdo_pgsql` is already included in the `nextcloud` Docker image:
    ```
    docker run --rm \
      --network nextcloud-migration \
      --entrypoint bash \
      -v /tmp/migration-config.php:/var/www/html/config/config.php:rw \
      -v "${SNAP_DATA}:${SNAP_DATA}:ro" \
      nextcloud:${NEXTCLOUD_VERSION}-apache \
      -c "php /var/www/html/occ db:convert-type --all-apps --password '$PG_PASSWORD' pgsql '$PG_USER' postgres-migration '$PG_DATABASE'"
    ```
    **Please note:** The `occ` command may print a warning about being run as root — this can be safely ignored for this migration step.
1. Export the converted PostgreSQL database:
    ```
    docker exec postgres-migration pg_dump -U "$PG_USER" "$PG_DATABASE" > ~/database-dump.sql
    ```
    **Please note:** The exact name of the database export file is important! (`database-dump.sql`)
1. Clean up the temporary containers and network:
    ```
    docker rm -f mysql-migration postgres-migration
    docker network rm nextcloud-migration
    ```
1. You now have a `~/database-dump.sql`. Continue from step 5 of the [Migrate the files and the database](#migrate-the-files-and-the-database) procedure above. When those steps ask for your old data directory path, use the `$SNAP_DATA` value noted in step 5 (typically `/var/snap/nextcloud/common`). If you have opened a new shell session since then, you can retrieve it again with `sudo nextcloud.occ config:system:get datadirectory` (requires the snap to be running) or read it directly from `/var/snap/nextcloud/current/nextcloud/config/config.php`.
1. Once you have verified that the migration to AIO was successful and everything is working correctly, you can permanently remove the Nextcloud snap from your system:
    ```
    sudo snap remove --purge nextcloud
    ```
    The `--purge` flag removes the snap along with all its saved snapshots and data. Omit it if you want to keep the snap snapshots as a fallback. **Only do this after you are fully satisfied that your AIO instance is working correctly**, as this action cannot be undone.

## Use the user_migration app
A new way since the Nextcloud update to 24 is to use the new [user_migration app](https://apps.nextcloud.com/apps/user_migration#app-gallery). It allows to export the most important data on one instance and import it on a different Nextcloud instance. For that, you need to install and enable the user_migration app on your old instance, trigger the export for the user, create the user on the new instance, log in with that user and import the archive that was created during the export. This then needs to be done for each user that you want to migrate.

If the restored data is older than any clients you want to continue to sync, for example if the server was down for a period of time during migration, you may want to take a look at [Synchronising with clients after migration](/migration.md#synchronising-with-clients-after-migration) below.

# Synchronising with clients after migration
#### From https://docs.nextcloud.com/server/latest/admin_manual/maintenance/restore.html#synchronising-with-clients-after-data-recovery
By default the Nextcloud server is considered the authoritative source for the data. If the data on the server and the client differs clients will default to fetching the data from the server.

If the recovered backup is outdated the state of the clients may be more up to date than the state of the server. In this case also make sure to run `sudo docker exec --user www-data -it nextcloud-aio-nextcloud php occ maintenance:data-fingerprint` command afterwards. It changes the logic of the synchronisation algorithm to try an recover as much data as possible. Files missing on the server are therefore recovered from the clients and in case of different content the users will be asked.

>[!Note]
>The usage of maintenance:data-fingerprint can cause conflict dialogues and difficulties deleting files on the client. Therefore it’s only recommended to prevent dataloss if the backup was outdated.


If you are running multiple application servers you will need to make sure the config files are synced between them so that the updated data-fingerprint is applied on all instances.
