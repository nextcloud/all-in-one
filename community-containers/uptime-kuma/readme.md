# Uptime Kuma 

[Uptime Kuma](https://github.com/louislam/uptime-kuma/) is an easy-to-use self-hosted monitoring tool that can monitor all your remote resources. 

<img src="https://user-images.githubusercontent.com/1336778/212262296-e6205815-ad62-488c-83ec-a5b0d0689f7c.jpg" width="700" alt="Uptime Kuma Dashboard Screenshot" />

## Installation

See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack. Add and start the container in the Nextcloud AIO interface. No configuration needs to be done by you. 

The data of uptime kuma will be included in AIO's backup solution automatically. 

> [!CAUTION]
> - To create a backup, the container will be stopped and then restarted automatically. During that time, no monitoring takes place and there will be gaps in ping graphs. The relative availability calculation is unaffected by that.
> 
> **By installing this container, you agree that you have read and understood these information.**


## Access to your instance

Your uptime kuma instance will be available at `http://server.tld:30001`. Note that this is not encrypted and therefore strongly discouraged. 

If you use AIO Caddy Container, it will be available at `https://status.server.tld`

When you first access your instance, you have to answer the question on database usage. If you have very strict hardware limitations, go for sqlite, else use built-in MariaDB. In both cases you do not have to setup anything else. Make sure to use a strong and unique administration password together with 2fA. 

## Difference between Glances, LinkBoard and uptime kuma

[Glances community container](https://github.com/nextcloud/all-in-one/tree/main/community-containers/glances) provides real-time monitoring to your local server on which AIO is running. Think `task manager` or `HTOP`. There is no historic view. If this is what you want, go for it. 

[Linkboard App](https://apps.nextcloud.com/apps/linkboard) does not provide real-time data but can monitor remote servers by pinging https:// addresses. It has a historic view of those pings over 7 days. If this is fine for you, then this is your tool.

Uptime Kuma is the right choice if you want to have more than that, e.g.
- unlimited historic data
- publicly accessible status pages
- monitoring of DNS/certificate/port/database/SMTP/...
- get notifications to talk/mail/signal/matrix/...
- faster checks than once per minute
- ...

