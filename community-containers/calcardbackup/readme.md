## calcardbackup  
This container packages [calcardbackup](https://codeberg.org/BernieO/calcardbackup), a tool that exports calendars and addressbooks from Nextcloud to .ics and .vcf files and saves them to a compressed file.

> [!WARNING]
> This container uses a third-party image with the `latest` tag and mounts the Nextcloud volume read-only. It can therefore read Nextcloud's `config.php` including the database password and the instance secret, as well as the calendars and address books of all users.
  
### Notes  
- Backups will be created at 00:00 UTC every day. Make sure that this does not conflict with the configured daily backups inside AIO.
- All the exports will be included in AIOs backup solution
- You can find the exports in the nextcloud_aio_calcardbackup volume
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack  
  
### Repository  
https://github.com/waja/docker-calcardbackup

### Maintainer
https://github.com/pailloM
