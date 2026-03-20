# Initial backup

- [ ] In the Backup and restore section, you should now see two input boxes where for one you should type in the path where the backup should get created and some explanation below or the other type in a remote ssh location
    - [ ] Enter `/` which should send an error
    - [ ] Enter `/mnt/` or  `/media/` or `/host_mnt/` or `/var/backups/` should send an error as well
    - [ ] Accepted should be `/mnt/backup`, `/media/backup`, `/host_mnt/c/backup` and `/var/backups`.
    - [ ] The side should now reload
- [ ] In the Backup restore section you should now see a Backup information section with important info like the encryption password, the backup location and more.
- [ ] Also you should see a Backup creation section that contains a `Create backup` button.
- [ ] Clicking on the `Create backup` button should open a window prompt that allows to cancel the operation.
- [ ] Canceling should return to the website, confirming should reveal the big spinner again which should block the website again.
- [ ] After a while you should see the information that Backup container is currently running
- [ ] another option are remote backups via SSH using borgbackup. The remote borg repo URL must contain both `@` and `:`. The process works as follows:
    1. You enter a remote borg repo URL (e.g. `ssh://user@host:port/path/to/repo` or `user@host:/path/to/repo`).
    2. On the first connection attempt, a SSH key pair is generated automatically and the public key is displayed.
    3. You add the public key to the `~/.ssh/authorized_keys` file on the remote server so that AIO can connect to it.
    4. Once authorized, AIO can create and restore backups on the remote server.
    - [ ] Enter `user` (no `@` and no `:`) which should send an error
    - [ ] Enter `user@host` (no `:`) which should send an error
    - [ ] Enter `userhost:/path` (no `@`) which should send an error
    - [ ] Accepted should be `ssh://user@host:22/path/to/repo` or `user@host:/path/to/repo`
    - [ ] Both a local backup location and a remote repo URL should not be accepted at the same time
    - [ ] The page should now reload
    - [ ] Now click on `Create backup`
    - [ ] After the first failed backup attempt with a remote repo, the SSH public key for borg should be shown so it can be authorized on the remote server
    - [ ] After authorizing the server on the remote, scroll down and click on `Create backup` again to create another backup. This time it should succeed.
- [ ] The initial Nextcloud credentials on top of the page that are visible when the containers are running should now be hidden in a details tag
- [ ] After a while and a few automatic reloads (as long as the side is focused), you should be redirected to the usual page and seen in the Backup and restore section that the last backup was successful.
- [ ] Below that you should see a details tag that allows to reveal all backup options

You can now continue with [020-backup-and-restore.md](.//020-backup-and-restore.md)