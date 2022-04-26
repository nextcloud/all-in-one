# Initial backup

- [ ] In the Backup and restore section, you should now see and input box where you should type in the path where the backup should get created and some explanation below
    - [ ] Enter `/` which should send an error
    - [ ] Enter `/mnt/` or  `/media/` or `/host_mnt/` or `/var/backups/` should send an error as well
    - [ ] Accepted should be `/mnt/backup`, `/media/backup`, `/host_mnt/c/backup` and `/var/backups`.
    - [ ] The side should now reload
- [ ] The initial Nextcloud credentials on top of the page that are visible when the containers are running should now be hidden in a details tag
- [ ] In the Backup restore section you should now see a Backup information section with important info like the encryption password, the backup location and more.
- [ ] Also you should see a Backup cretion section that contains a `Create backup` button.
- [ ] Clicking on the `Create backup` button should open a window prompt that allows to cancel the operation.
- [ ] Canceling should return to the website, confirming should reveal the big spinner again which should block the website again.
- [ ] After a while you should see the information that Backup container is currently running
- [ ] Below the Containers section you should see the option to `Start containers` again.
- [ ] After a while and a few automatic reloads (as long as the side is focused), you should be redirected to the usual page and seen in the Backup and restore section that the last backup was successful.
- [ ] Below thhat you should see a details tag that allows to reveal all backup options

You can now continue with [020-backup-and-restore.md](.//020-backup-and-restore.md)