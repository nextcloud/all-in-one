> [!CAUTION]
> NocoDB is licensed under a non-free license.
> 
> And is no longer maintained.

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
This container bundles [NocoDb](https://github.com/nocodb/nocodb), an online no-code database solution, without synchronization with Nextcloud.

This is an alternative of **Airtable**.

> [!WARNING]
> - Sign-up is open by default, so everyone who can reach NocoDB can create an account. Create your own account immediately after the first start and afterwards enable invite-only sign-up in NocoDB's settings.
> - Port 10028 is published on all network interfaces of the host unless you restrict it via [`APACHE_IP_BINDING`](https://github.com/nextcloud/all-in-one/blob/main/reverse-proxy.md#3-limit-the-access-to-the-apache-container) (with the [caddy](https://github.com/nextcloud/all-in-one/tree/main/community-containers/caddy) community container, it is not published at all).
> - The image uses the `latest` tag of a project that is no longer maintained, so it will not receive security fixes.

### Notes
- You need to configure a reverse proxy in order to run this container since nocodb needs a dedicated (sub)domain! For that, you might have a look at https://github.com/nextcloud/all-in-one/tree/main/community-containers/caddy.
- Currently, only `tables.$NC_DOMAIN` is supported as subdomain! So if Nextcloud is using `your-domain.com`, nocodb will use `tables.your-domain.com`.
- The data of NocoDb will be automatically included in AIOs backup solution!
- After adding and starting the container, you can log in to the web interface at `https://tables.$NC_DOMAIN/#/signin` with the username `admin@noco.db` and the password that you can see in the AIO interface next to the container. 
- See https://docs.nocodb.com/ for usage of NocoDb
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack

### Repository
https://github.com/nocodb/nocodb

### Maintainer
https://github.com/docjyJ
