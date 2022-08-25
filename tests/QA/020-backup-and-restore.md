# Backup and restore

- [ ] Expanding all backup options in the Backup and restore sectioin should reveal a Backup information section, Backup creation section, Backup check section, Backup restore section and a Daily backup section.
- [ ] The backup restore section should list all available backup archives and list them from most recent to least recent.
- [ ] Clicking on either option of Create backup, Check backup integrity or Restore selected backup should run the corresponding action and report after a while in the last check, backup or restore was successful.
- [ ] Daily backup creatio should allow to enter a time in 24h format e.g. `04:00` should be accepted, `24:00` or `dfjlk` not.
- [ ] Submitting a time here should reload the page and reveal at the same place the option to delete the setting again.
- [ ] When the time of the automatic backup has come (you can test it by choosing a time that is e.g. only a minute away), it should automatically log you out (you can verify by reloading) and after you log in again you should see that the automatic backup is currently running.
- [ ] After a while you should see that your container are starting and in the Backup and restore section you should see that the backup was successful
- [ ] When entering additional backup directories, it should allow e.g. `/etc` and `nextcloud_aio_mastercontainer` but not `nextcloud/test`. Running a backup with this should back up these directories/volumes successfully.

You can now continue with [030-aio-password-change.md](./030-aio-password-change.md)