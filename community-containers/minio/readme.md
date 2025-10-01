## Minio
This container bundles minio s3 storage and auto-configures it for you.

>[!WARNING]
> Enabling this container will remove access to all the files formerly written to the data directory.
> So only enable this on a clean instance directly after installing AIO.
> All additional users that are added via Nextcloud afterwards are going to work correctly.
> Also, after enabling and using it, make sure to not disable the container as you cannot migrate from s3 to local storage anymore and s3 is a critical part of your infrastructure from then on.

### Notes
- The data of Minio will be automatically included in AIOs backup solution!
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack

### Repository
https://github.com/szaimen/aio-minio

### Maintainer
https://github.com/szaimen
