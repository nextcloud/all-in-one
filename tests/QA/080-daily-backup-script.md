# Daily backup script

The script is delivered within the mastercontainer and allows to run a few things like daily backup and container updates from an external script.

You can find the documentation on this here which needs to work as documented: https://github.com/nextcloud/all-in-one#how-to-stopstartupdate-containers-or-trigger-the-daily-backup-from-a-script-externally

## SKIP_IF_UP_TO_DATE

- With all containers running and up to date, `sudo docker exec --env AUTOMATIC_UPDATES=1 --env SKIP_IF_UP_TO_DATE=1 nextcloud-aio-mastercontainer /daily-backup.sh` should print `Everything is running and up to date...`, exit without restarting any container and keep you logged in to the AIO interface.
- Stop one container (e.g. `sudo docker stop nextcloud-aio-redis`) and run the same command: it should run the normal update and start all containers again.
- With `DAILY_BACKUP=1` added, the option should have no effect and a backup should be created as usual.
