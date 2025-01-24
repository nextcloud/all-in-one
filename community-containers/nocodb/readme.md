> [!NOTE]
> This container is there to compensate for the lack of functionality in Nextcloud Tables.
>
> When Nextcloud Tables V2 is released, I will stop checking for updates, and will no longer fix any potential issues.
>
> Some missing functionality in Nextcloud Tables:
> - Multiple view layout (Gantt, Kanban, Calendar...)
> - Field (Person, Tag, File...)
> - See more here https://github.com/nextcloud/tables/issues/103 

## NocoDb server
This container bundles NocoDb without synchronization with Nextcloud.

This is an alternative of **Airtable**.

### Notes
- You need to configure a reverse proxy in order to run this container since nocodb needs a dedicated (sub)domain! For that, you might have a look at https://github.com/nextcloud/all-in-one/tree/main/community-containers/caddy.
- Currently, only `tables.$NC_DOMAIN` is supported as subdomain! So if Nextcloud is using `your-domain.com`, nocodb will use `tables.your-domain.com`.
- The data of NocoDb will be automatically included in AIOs backup solution!
- After adding and starting the container, you can log in to the web interface at `https://tables.$NC_DOMAIN/#/signin` with the username `admin@noco.db` and the password that you can see in the AIO interface next to the container. 
- See https://docs.nocodb.com/ for usage of NocoDb
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack

### Repository
https://github.com/docjyJ/aio-nocodb

### Maintainer
https://github.com/docjyJ
