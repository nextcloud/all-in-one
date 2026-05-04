---
name: 🐛 Bug report - no questions and no support!
about: Help us improving by reporting a bug - this category is not for questions and also not for support! Please use one of the options below for questions and support
labels: 0. Needs triage
---

<!---
- Before submitting a bug report, please read through the documentation available at https://github.com/nextcloud/all-in-one#faq
- Additional documentation is available here: https://github.com/nextcloud/all-in-one/discussions/categories/wiki
- You should also read through existing questions and their answer here: https://github.com/nextcloud/all-in-one/discussions/categories/questions
- Additional threads can be found here: https://help.nextcloud.com/tag/aio
- Existing feature requests are listed here: https://github.com/nextcloud/all-in-one/discussions/categories/ideas
--->

<!--- Please fill out the whole template below -->
### Steps to reproduce
1.Upgrade Nextcloud AIO to 13.0.0 Beta
2.Login as Admin
3.Try to open the Nextcloud AIO-Interface by clicking the button "open Nextcloud AIO interface" via `Administrator settings -> Overview -> "open Nextcloud AIO interface"` (link to `https://<ip>:9080`)

### Expected behavior <!--- Tell us what should happen -->
+ The AIO-interface should immediately (even if Nextcloud AIO instance is currently running).
### Actual behavior <!--- Tell us what happens instead -->
+ The error [message](#Other-valuable-info) appears and prevents access to the Nextcloud AIO-interface.


### Other information
#### Host OS <!--- (the host OS on which you are trying to install AIO on) -->
+ Docker on QNAP QTS 5.2.9.3451
#### Output of `sudo docker info`
```bash
Client:
 Version:    27.1.2-qnap8
 Context:    default
 Debug Mode: false
 Plugins:
  buildx: Docker Buildx (Docker Inc.)
    Version:  v0.21.2-qnap1
    Path:     /usr/local/lib/docker/cli-plugins/docker-buildx
  compose: Docker Compose (Docker Inc.)
    Version:  v2.29.1-qnap2
    Path:     /usr/local/lib/docker/cli-plugins/docker-compose

Server:
 Containers: 29
  Running: 18
  Paused: 0
  Stopped: 11
 Images: 36
 Server Version: 27.1.2-qnap8
 Storage Driver: overlay2
  Backing Filesystem: extfs
  Supports d_type: true
  Using metacopy: true
  Native Overlay Diff: false
  userxattr: false
 Logging Driver: json-file
 Cgroup Driver: cgroupfs
 Cgroup Version: 1
 Plugins:
  Volume: local
  Network: bridge host ipvlan macvlan null overlay qnet
  Log: awslogs fluentd gcplogs gelf journald json-file local splunk syslog
 Swarm: inactive
 Runtimes: io.containerd.runc.v2 kata-runtime nvidia-runtime runc
 Default Runtime: runc
 Init Binary: docker-init
 containerd version: 8fc6bcff51318944179630522a095cc9dbf9f353
 runc version: v1.1.13-0-g58aa920
 init version: de40ad0
 Security Options:
  apparmor
  seccomp
   Profile: builtin
 Kernel Version: 5.10.60-qnap
 Operating System: QTS 5.2.9 (20260327)
 OSType: linux
 Architecture: x86_64
 CPUs: 4
 Total Memory: 31.2GiB
 Name: SeidlerNAS
 ID: ac2bbf61-3ccc-44f4-a62f-30d1359a1fcb
 Docker Root Dir: /share/CACHEDEV2_DATA/Container/container-station-data/lib/docker
 Debug Mode: true
  File Descriptors: 179
  Goroutines: 178
  System Time: 2026-05-01T18:25:16.206200711+02:00
  EventsListeners: 1
 Experimental: false
 Insecure Registries:
  127.0.0.0/8
 Live Restore Enabled: false
 Product License: Community Engine
 Default Address Pools:
   Base: 172.29.0.0/16, Size: 22
```
#### Docker run command or docker-compose file that you used
```yaml
sudo docker run \
--init \
--sig-proxy=false \
--name nextcloud-aio-mastercontainer \
--restart always \
--publish 9080:8080 \
--env APACHE_PORT=11000 \
--env APACHE_IP_BINDING=0.0.0.0 \
--env APACHE_ADDITIONAL_NETWORK="" \
--env SKIP_DOMAIN_VALIDATION=false \
--env NEXTCLOUD_DATADIR="/share/nextcloud_data/data/" \
--volume nextcloud_aio_mastercontainer:/mnt/docker-aio-config \
--volume /var/run/docker.sock:/var/run/docker.sock:ro \
nextcloud/all-in-one:beta
```
#### Output of `sudo docker logs nextcloud-aio-mastercontainer`
```bash
Trying to fix docker.sock permissions internally...
Adding internal www-data to group root
Initial startup of Nextcloud All-in-One complete!
You should be able to open the Nextcloud AIO Interface now on port 8080 of this server!
E.g. https://internal.ip.of.this.server:8080
⚠️ Important: do always use an ip-address if you access this port and not a domain as HSTS might block access to it later!

If your server has port 80 and 8443 open and you point a domain to your server, you can get a valid certificate automatically by opening the Nextcloud AIO Interface via:
https://your-domain-that-points-to-this-server.tld:8443
++ head -1 /mnt/docker-aio-config/data/daily_backup_time
+ BACKUP_TIME=03:30
+ export BACKUP_TIME
+ export DAILY_BACKUP=1
+ DAILY_BACKUP=1
++ sed -n 2p /mnt/docker-aio-config/data/daily_backup_time
+ '[' '' '!=' automaticUpdatesAreNotEnabled ']'
+ export AUTOMATIC_UPDATES=1
+ AUTOMATIC_UPDATES=1
++ sed -n 3p /mnt/docker-aio-config/data/daily_backup_time
+ '[' '' '!=' successNotificationsAreNotEnabled ']'
+ export SEND_SUCCESS_NOTIFICATIONS=1
+ SEND_SUCCESS_NOTIFICATIONS=1
+ '[' warn '!=' debug ']'
+ set +x
Daily backup script has started
[01-May-2026 03:30:55] NOTICE: fpm is running, pid 170
[01-May-2026 03:30:55] NOTICE: ready to handle connections
Connection to nextcloud-aio-apache (172.29.4.13) 11000 port [tcp/*] succeeded!
Starting mastercontainer update...
(The script might get exited due to that. In order to update all the other containers correctly, you need to run this script with the same settings a second time.)
Waiting for watchtower to stop
Updating container images...
Creating daily backup...
Could not get digest of container nextcloud-releases/aio-borgbackup:beta cURL error 6: Could not resolve host: ghcr.io (DNS server returned general failure) (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for https://ghcr.io/token?scope=repository:nextcloud-releases/aio-borgbackup:pull
Not pulling the ghcr.io/nextcloud-releases/aio-borgbackup image for the nextcloud-aio-borgbackup container because the registry does not seem to be reachable.
Waiting for backup container to stop
Waiting for backup container to stop
Waiting for backup container to stop
Waiting for backup container to stop
Waiting for backup container to stop
Waiting for backup container to stop
Waiting for backup container to stop
Waiting for backup container to stop
Starting and updating containers...

Fatal error: Uncaught Exception: The secret COLLABORA_LOG_LEVEL was not registered. Please check if it is defined in secrets of containers.json. in /var/www/docker-aio/php/src/Data/ConfigurationManager.php:376
Stack trace:
#0 /var/www/docker-aio/php/src/Data/ConfigurationManager.php(1119): AIO\Data\ConfigurationManager->getRegisteredSecret('COLLABORA_LOG_L...')
#1 [internal function]: AIO\Data\ConfigurationManager->getPlaceholderValue('COLLABORA_LOG_L...')
#2 /var/www/docker-aio/php/src/Data/ConfigurationManager.php(1064): array_map(Object(Closure), Array)
#3 /var/www/docker-aio/php/src/Docker/DockerActionManager.php(266): AIO\Data\ConfigurationManager->replaceEnvPlaceholders('HP_LOG_LEVEL=%C...')
#4 /var/www/docker-aio/php/src/Controller/DockerController.php(43): AIO\Docker\DockerActionManager->CreateContainer(Object(AIO\Container\Container))
#5 /var/www/docker-aio/php/src/Controller/DockerController.php(30): AIO\Controller\DockerController->PerformRecursiveContainerStart('nextcloud-aio-h...', true, NULL)
#6 /var/www/docker-aio/php/src/Controller/DockerController.php(268): AIO\Controller\DockerController->PerformRecursiveContainerStart('nextcloud-aio-a...', true, NULL)
#7 /var/www/docker-aio/php/src/Cron/StartAndUpdateContainers.php(20): AIO\Controller\DockerController->startTopContainer(true)
#8 {main}
  thrown in /var/www/docker-aio/php/src/Data/ConfigurationManager.php on line 376
Something seems to be wrong: Nextcloud should be started at this step.
Sending backup notification...
Daily backup script has finished
Deleted Images:
untagged: ghcr.io/nextcloud-releases/aio-notify-push@sha256:c90eea8e86f500956bd4494e520cf0a40f983d64b639f114b06778f2e10a7295
deleted: sha256:a838c3171d2b261edc4db90e7886d3665ddc4ea03df8723da641182824313484
deleted: sha256:8216d8a759c0e2b8ff3076b1e8289a381677cbf162f29487add60a1e3f6f3dc3
deleted: sha256:8169d5550e2c9f566ef2d304e8b1f6e4753b9cca763939167973eb2e04b0f98f
deleted: sha256:73185591302c256eaea3c8864aa1a479d1bb9afde502404aeee7232fa76fe8dc
untagged: ghcr.io/nextcloud-releases/aio-collabora@sha256:fb32e8647b14fc442742d6dcaed6cbde8ab2b2d4a2904bb43bfbc95872cab8f5
deleted: sha256:d86a08ae434cde95594f86871f42d4b677c15d27843535706571e808de54ff94
deleted: sha256:bfcf391f23435b80b42cf5a3b746881e966caa79ce2ed26eaf391c244c97a4fd
untagged: ghcr.io/nextcloud-releases/aio-whiteboard@sha256:b2945a943838a0490ba2ec00d262186fb7b6383cdaa683aa1871fad11f085086
deleted: sha256:d57dd5f8c7decce281cdef3f4717a191c9cd917ce464df9113b26103b5d5f9a8
deleted: sha256:d35d033fa13cc8888e64c436b72526833d4202b42d1c8d8350be05d4eceba471
deleted: sha256:dd203b24f0f917e299abfb221b46766da997fe0d3d2c02d61d59ce082268a110
deleted: sha256:267b22e5c7e4ffbc2e82c5384dab0bdc13525a4c6ce4363409bd706e212e6fba
deleted: sha256:6b3e8ec26e58da55d485a79587b11c27c58f88fa0685913f3a7d12e3b4916126
untagged: ghcr.io/nextcloud-releases/aio-watchtower@sha256:366fc874fb4ad4d48419011b3d5158d0ffbcaf4cb601c47d894303fd279584d2
deleted: sha256:a8ed35782a8315792674f38961befc24b812ea71bb1484e473fa5728cd9b7af5
deleted: sha256:06a1121685b3b62b875d99638ac4bc940273775273bca3db483017ee0d51c86f
deleted: sha256:1253b1b500b0c6b745f82a1d7b9faae95e23e4b5c7a49ef0a8e28196537a3d1b
deleted: sha256:df673962da093adaa21b4dbc116d65125ae1b4cba20c3a56c8969a4f0b1f59f4
untagged: ghcr.io/nextcloud-releases/aio-talk@sha256:6d78dd1c14ecf3299f155d46294481ba37b61c38a3123f5090d480d8990bd269
deleted: sha256:7b764d10cc7f2d1a9ac0337d8b42de6acc1f12831ba50da5006c86a97a95d61b
deleted: sha256:88985c986812077a731e49be419484d1f9b56e4d06c84c139d7e023e3a4863d6
deleted: sha256:b34d379c532fd42bf127a9f3ed2d4fb8e33fa83f0f872f5dfcedf203eee654fc
deleted: sha256:ab17be674939568847b40af13204efb5192c004f3a91d354986af07fc668543f
deleted: sha256:cde7a520e25c03ac1736712979c0a24cffcce77a0c50007845dc70b701564593
deleted: sha256:e7cd0de09fe879a05b2e474578880e3824d36f1ed856e4581d81f566f9aa900a
deleted: sha256:4f801b2abf698d55d98980e2b594f845a81b36c475dde9520f02b5a2a495c285
deleted: sha256:7ec51b26d24845b28a731b43eac456e4976fb673ab55fd0ee42167b46529bf69
deleted: sha256:39fa8f595d0580d7932455d7a26db2030e189def4285ef21e3251b3e50a7af13

Total reclaimed space: 213.3MB
Total reclaimed space: 0B
++ head -1 /mnt/docker-aio-config/data/daily_backup_time
+ BACKUP_TIME=03:30
+ export BACKUP_TIME
+ export DAILY_BACKUP=1
+ DAILY_BACKUP=1
++ sed -n 2p /mnt/docker-aio-config/data/daily_backup_time
+ '[' '' '!=' automaticUpdatesAreNotEnabled ']'
+ export AUTOMATIC_UPDATES=1
+ AUTOMATIC_UPDATES=1
++ sed -n 3p /mnt/docker-aio-config/data/daily_backup_time
+ '[' '' '!=' successNotificationsAreNotEnabled ']'
+ export SEND_SUCCESS_NOTIFICATIONS=1
+ SEND_SUCCESS_NOTIFICATIONS=1
+ '[' warn '!=' debug ']'
+ set +x
Deleting duplicate sessions
NOTICE: PHP message: Slim Application Error
Type: Exception
Code: 0
Message: The secret COLLABORA_LOG_LEVEL was not registered. Please check if it is defined in secrets of containers.json.
File: /var/www/docker-aio/php/src/Data/ConfigurationManager.php
Line: 376
Trace: #0 /var/www/docker-aio/php/src/Data/ConfigurationManager.php(1119): AIO\Data\ConfigurationManager->getRegisteredSecret('COLLABORA_LOG_L...')
#1 [internal function]: AIO\Data\ConfigurationManager->getPlaceholderValue('COLLABORA_LOG_L...')
#2 /var/www/docker-aio/php/src/Data/ConfigurationManager.php(1064): array_map(Object(Closure), Array)
#3 /var/www/docker-aio/php/src/Docker/DockerActionManager.php(266): AIO\Data\ConfigurationManager->replaceEnvPlaceholders('HP_LOG_LEVEL=%C...')
#4 /var/www/docker-aio/php/src/Controller/DockerController.php(43): AIO\Docker\DockerActionManager->CreateContainer(Object(AIO\Container\Container))
#5 /var/www/docker-aio/php/src/Controller/DockerController.php(30): AIO\Controller\DockerController->PerformRecursiveContainerStart('nextcloud-aio-h...', true, Object(Closure))
#6 /var/www/docker-aio/php/src/Controller/DockerController.php(268): AIO\Controller\DockerController->PerformRecursiveContainerStart('nextcloud-aio-a...', true, Object(Closure))
#7 /var/www/docker-aio/php/src/Controller/DockerController.php(249): AIO\Controller\DockerController->startTopContainer(true, Object(Closure))
#8 /var/www/docker-aio/php/vendor/slim/slim/Slim/Handlers/Strategies/RequestResponse.php(39): AIO\Controller\DockerController->StartContainer(Object(Slim\Psr7\Request), Object(Slim\Psr7\Response), Array)
#9 /var/www/docker-aio/php/vendor/slim/slim/Slim/Routing/Route.php(362): Slim\Handlers\Strategies\RequestResponse->__invoke(Array, Object(Slim\Psr7\Request), Object(Slim\Psr7\Response), Array)
#10 /var/www/docker-aio/php/vendor/slim/slim/Slim/MiddlewareDispatcher.php(73): Slim\Routing\Route->handle(Object(Slim\Psr7\Request))
#11 /var/www/docker-aio/php/vendor/slim/slim/Slim/MiddlewareDispatcher.php(73): Slim\MiddlewareDispatcher->handle(Object(Slim\Psr7\Request))
#12 /var/www/docker-aio/php/vendor/slim/slim/Slim/Routing/Route.php(321): Slim\MiddlewareDispatcher->handle(Object(Slim\Psr7\Request))
#13 /var/www/docker-aio/php/vendor/slim/slim/Slim/Routing/RouteRunner.php(74): Slim\Routing\Route->run(Object(Slim\Psr7\Request))
#14 /var/www/docker-aio/php/vendor/slim/csrf/src/Guard.php(482): Slim\Routing\RouteRunner->handle(Object(Slim\Psr7\Request))
#15 /var/www/docker-aio/php/vendor/slim/slim/Slim/MiddlewareDispatcher.php(178): Slim\Csrf\Guard->process(Object(Slim\Psr7\Request), Object(Slim\Routing\RouteRunner))
#16 /var/www/docker-aio/php/vendor/slim/twig-view/src/TwigMiddleware.php(117): Psr\Http\Server\RequestHandlerInterface@anonymous->handle(Object(Slim\Psr7\Request))
#17 /var/www/docker-aio/php/vendor/slim/slim/Slim/MiddlewareDispatcher.php(129): Slim\Views\TwigMiddleware->process(Object(Slim\Psr7\Request), Object(Psr\Http\Server\RequestHandlerInterface@anonymous))
#18 /var/www/docker-aio/php/src/Middleware/AuthMiddleware.php(54): Psr\Http\Server\RequestHandlerInterface@anonymous->handle(Object(Slim\Psr7\Request))
#19 /var/www/docker-aio/php/vendor/slim/slim/Slim/MiddlewareDispatcher.php(283): AIO\Middleware\AuthMiddleware->__invoke(Object(Slim\Psr7\Request), Object(Psr\Http\Server\RequestHandlerInterface@anonymous))
#20 /var/www/docker-aio/php/vendor/slim/slim/Slim/Middleware/ErrorMiddleware.php(77): Psr\Http\Server\RequestHandlerInterface@anonymous->handle(Object(Slim\Psr7\Request))
#21 /var/www/docker-aio/php/vendor/slim/slim/Slim/MiddlewareDispatcher.php(129): Slim\Middleware\ErrorMiddleware->process(Object(Slim\Psr7\Request), Object(Psr\Http\Server\RequestHandlerInterface@anonymous))
#22 /var/www/docker-aio/php/vendor/slim/slim/Slim/MiddlewareDispatcher.php(73): Psr\Http\Server\RequestHandlerInterface@anonymous->handle(Object(Slim\Psr7\Request))
#23 /var/www/docker-aio/php/vendor/slim/slim/Slim/App.php(209): Slim\MiddlewareDispatcher->handle(Object(Slim\Psr7\Request))
#24 /var/www/docker-aio/php/vendor/slim/slim/Slim/App.php(193): Slim\App->handle(Object(Slim\Psr7\Request))
#25 /var/www/docker-aio/php/public/index.php(259): Slim\App->run()
#26 {main}
Tips: To display error details in HTTP response set "displayErrorDetails" to true in the ErrorHandler constructor.
NOTICE: PHP message: Slim Application Error
Type: Exception
Code: 0
Message: The secret COLLABORA_LOG_LEVEL was not registered. Please check if it is defined in secrets of containers.json.
File: /var/www/docker-aio/php/src/Data/ConfigurationManager.php
Line: 376
Trace: #0 /var/www/docker-aio/php/src/Data/ConfigurationManager.php(1119): AIO\Data\ConfigurationManager->getRegisteredSecret('COLLABORA_LOG_L...')
#1 [internal function]: AIO\Data\ConfigurationManager->getPlaceholderValue('COLLABORA_LOG_L...')
#2 /var/www/docker-aio/php/src/Data/ConfigurationManager.php(1064): array_map(Object(Closure), Array)
#3 /var/www/docker-aio/php/src/Docker/DockerActionManager.php(266): AIO\Data\ConfigurationManager->replaceEnvPlaceholders('HP_LOG_LEVEL=%C...')
#4 /var/www/docker-aio/php/src/Controller/DockerController.php(43): AIO\Docker\DockerActionManager->CreateContainer(Object(AIO\Container\Container))
#5 /var/www/docker-aio/php/src/Controller/DockerController.php(30): AIO\Controller\DockerController->PerformRecursiveContainerStart('nextcloud-aio-h...', true, Object(Closure))
#6 /var/www/docker-aio/php/src/Controller/DockerController.php(268): AIO\Controller\DockerController->PerformRecursiveContainerStart('nextcloud-aio-a...', true, Object(Closure))
#7 /var/www/docker-aio/php/src/Controller/DockerController.php(249): AIO\Controller\DockerController->startTopContainer(true, Object(Closure))
#8 /var/www/docker-aio/php/vendor/slim/slim/Slim/Handlers/Strategies/RequestResponse.php(39): AIO\Controller\DockerController->StartContainer(Object(Slim\Psr7\Request), Object(Slim\Psr7\Response), Array)
#9 /var/www/docker-aio/php/vendor/slim/slim/Slim/Routing/Route.php(362): Slim\Handlers\Strategies\RequestResponse->__invoke(Array, Object(Slim\Psr7\Request), Object(Slim\Psr7\Response), Array)
#10 /var/www/docker-aio/php/vendor/slim/slim/Slim/MiddlewareDispatcher.php(73): Slim\Routing\Route->handle(Object(Slim\Psr7\Request))
#11 /var/www/docker-aio/php/vendor/slim/slim/Slim/MiddlewareDispatcher.php(73): Slim\MiddlewareDispatcher->handle(Object(Slim\Psr7\Request))
#12 /var/www/docker-aio/php/vendor/slim/slim/Slim/Routing/Route.php(321): Slim\MiddlewareDispatcher->handle(Object(Slim\Psr7\Request))
#13 /var/www/docker-aio/php/vendor/slim/slim/Slim/Routing/RouteRunner.php(74): Slim\Routing\Route->run(Object(Slim\Psr7\Request))
#14 /var/www/docker-aio/php/vendor/slim/csrf/src/Guard.php(482): Slim\Routing\RouteRunner->handle(Object(Slim\Psr7\Request))
#15 /var/www/docker-aio/php/vendor/slim/slim/Slim/MiddlewareDispatcher.php(178): Slim\Csrf\Guard->process(Object(Slim\Psr7\Request), Object(Slim\Routing\RouteRunner))
#16 /var/www/docker-aio/php/vendor/slim/twig-view/src/TwigMiddleware.php(117): Psr\Http\Server\RequestHandlerInterface@anonymous->handle(Object(Slim\Psr7\Request))
#17 /var/www/docker-aio/php/vendor/slim/slim/Slim/MiddlewareDispatcher.php(129): Slim\Views\TwigMiddleware->process(Object(Slim\Psr7\Request), Object(Psr\Http\Server\RequestHandlerInterface@anonymous))
#18 /var/www/docker-aio/php/src/Middleware/AuthMiddleware.php(54): Psr\Http\Server\RequestHandlerInterface@anonymous->handle(Object(Slim\Psr7\Request))
#19 /var/www/docker-aio/php/vendor/slim/slim/Slim/MiddlewareDispatcher.php(283): AIO\Middleware\AuthMiddleware->__invoke(Object(Slim\Psr7\Request), Object(Psr\Http\Server\RequestHandlerInterface@anonymous))
#20 /var/www/docker-aio/php/vendor/slim/slim/Slim/Middleware/ErrorMiddleware.php(77): Psr\Http\Server\RequestHandlerInterface@anonymous->handle(Object(Slim\Psr7\Request))
#21 /var/www/docker-aio/php/vendor/slim/slim/Slim/MiddlewareDispatcher.php(129): Slim\Middleware\ErrorMiddleware->process(Object(Slim\Psr7\Request), Object(Psr\Http\Server\RequestHandlerInterface@anonymous))
#22 /var/www/docker-aio/php/vendor/slim/slim/Slim/MiddlewareDispatcher.php(73): Psr\Http\Server\RequestHandlerInterface@anonymous->handle(Object(Slim\Psr7\Request))
#23 /var/www/docker-aio/php/vendor/slim/slim/Slim/App.php(209): Slim\MiddlewareDispatcher->handle(Object(Slim\Psr7\Request))
#24 /var/www/docker-aio/php/vendor/slim/slim/Slim/App.php(193): Slim\App->handle(Object(Slim\Psr7\Request))
#25 /var/www/docker-aio/php/public/index.php(259): Slim\App->run()
#26 {main}
Tips: To display error details in HTTP response set "displayErrorDetails" to true in the ErrorHandler constructor.
NOTICE: PHP message: Slim Application Error
Type: Exception
Code: 0
Message: The secret COLLABORA_LOG_LEVEL was not registered. Please check if it is defined in secrets of containers.json.
File: /var/www/docker-aio/php/src/Data/ConfigurationManager.php
Line: 376
Trace: #0 /var/www/docker-aio/php/src/Data/ConfigurationManager.php(1119): AIO\Data\ConfigurationManager->getRegisteredSecret('COLLABORA_LOG_L...')
#1 [internal function]: AIO\Data\ConfigurationManager->getPlaceholderValue('COLLABORA_LOG_L...')
#2 /var/www/docker-aio/php/src/Data/ConfigurationManager.php(1064): array_map(Object(Closure), Array)
#3 /var/www/docker-aio/php/src/Docker/DockerActionManager.php(266): AIO\Data\ConfigurationManager->replaceEnvPlaceholders('HP_LOG_LEVEL=%C...')
#4 /var/www/docker-aio/php/src/Controller/DockerController.php(43): AIO\Docker\DockerActionManager->CreateContainer(Object(AIO\Container\Container))
#5 /var/www/docker-aio/php/src/Controller/DockerController.php(30): AIO\Controller\DockerController->PerformRecursiveContainerStart('nextcloud-aio-h...', true, Object(Closure))
#6 /var/www/docker-aio/php/src/Controller/DockerController.php(268): AIO\Controller\DockerController->PerformRecursiveContainerStart('nextcloud-aio-a...', true, Object(Closure))
#7 /var/www/docker-aio/php/src/Controller/DockerController.php(249): AIO\Controller\DockerController->startTopContainer(true, Object(Closure))
#8 /var/www/docker-aio/php/vendor/slim/slim/Slim/Handlers/Strategies/RequestResponse.php(39): AIO\Controller\DockerController->StartContainer(Object(Slim\Psr7\Request), Object(Slim\Psr7\Response), Array)
#9 /var/www/docker-aio/php/vendor/slim/slim/Slim/Routing/Route.php(362): Slim\Handlers\Strategies\RequestResponse->__invoke(Array, Object(Slim\Psr7\Request), Object(Slim\Psr7\Response), Array)
#10 /var/www/docker-aio/php/vendor/slim/slim/Slim/MiddlewareDispatcher.php(73): Slim\Routing\Route->handle(Object(Slim\Psr7\Request))
#11 /var/www/docker-aio/php/vendor/slim/slim/Slim/MiddlewareDispatcher.php(73): Slim\MiddlewareDispatcher->handle(Object(Slim\Psr7\Request))
#12 /var/www/docker-aio/php/vendor/slim/slim/Slim/Routing/Route.php(321): Slim\MiddlewareDispatcher->handle(Object(Slim\Psr7\Request))
#13 /var/www/docker-aio/php/vendor/slim/slim/Slim/Routing/RouteRunner.php(74): Slim\Routing\Route->run(Object(Slim\Psr7\Request))
#14 /var/www/docker-aio/php/vendor/slim/csrf/src/Guard.php(482): Slim\Routing\RouteRunner->handle(Object(Slim\Psr7\Request))
#15 /var/www/docker-aio/php/vendor/slim/slim/Slim/MiddlewareDispatcher.php(178): Slim\Csrf\Guard->process(Object(Slim\Psr7\Request), Object(Slim\Routing\RouteRunner))
#16 /var/www/docker-aio/php/vendor/slim/twig-view/src/TwigMiddleware.php(117): Psr\Http\Server\RequestHandlerInterface@anonymous->handle(Object(Slim\Psr7\Request))
#17 /var/www/docker-aio/php/vendor/slim/slim/Slim/MiddlewareDispatcher.php(129): Slim\Views\TwigMiddleware->process(Object(Slim\Psr7\Request), Object(Psr\Http\Server\RequestHandlerInterface@anonymous))
#18 /var/www/docker-aio/php/src/Middleware/AuthMiddleware.php(54): Psr\Http\Server\RequestHandlerInterface@anonymous->handle(Object(Slim\Psr7\Request))
#19 /var/www/docker-aio/php/vendor/slim/slim/Slim/MiddlewareDispatcher.php(283): AIO\Middleware\AuthMiddleware->__invoke(Object(Slim\Psr7\Request), Object(Psr\Http\Server\RequestHandlerInterface@anonymous))
#20 /var/www/docker-aio/php/vendor/slim/slim/Slim/Middleware/ErrorMiddleware.php(77): Psr\Http\Server\RequestHandlerInterface@anonymous->handle(Object(Slim\Psr7\Request))
#21 /var/www/docker-aio/php/vendor/slim/slim/Slim/MiddlewareDispatcher.php(129): Slim\Middleware\ErrorMiddleware->process(Object(Slim\Psr7\Request), Object(Psr\Http\Server\RequestHandlerInterface@anonymous))
#22 /var/www/docker-aio/php/vendor/slim/slim/Slim/MiddlewareDispatcher.php(73): Psr\Http\Server\RequestHandlerInterface@anonymous->handle(Object(Slim\Psr7\Request))
#23 /var/www/docker-aio/php/vendor/slim/slim/Slim/App.php(209): Slim\MiddlewareDispatcher->handle(Object(Slim\Psr7\Request))
#24 /var/www/docker-aio/php/vendor/slim/slim/Slim/App.php(193): Slim\App->handle(Object(Slim\Psr7\Request))
#25 /var/www/docker-aio/php/public/index.php(259): Slim\App->run()
#26 {main}
Tips: To display error details in HTTP response set "displayErrorDetails" to true in the ErrorHandler constructor.
Deleting duplicate sessions
Deleting duplicate sessions
Deleting duplicate sessions
Deleting duplicate sessions
Deleting duplicate sessions
NOTICE: PHP message: Slim Application Error
Type: Exception
Code: 0
Message: The secret COLLABORA_LOG_LEVEL was not registered. Please check if it is defined in secrets of containers.json.
File: /var/www/docker-aio/php/src/Data/ConfigurationManager.php
Line: 376
Trace: #0 /var/www/docker-aio/php/src/Data/ConfigurationManager.php(1119): AIO\Data\ConfigurationManager->getRegisteredSecret('COLLABORA_LOG_L...')
#1 [internal function]: AIO\Data\ConfigurationManager->getPlaceholderValue('COLLABORA_LOG_L...')
#2 /var/www/docker-aio/php/src/Data/ConfigurationManager.php(1064): array_map(Object(Closure), Array)
#3 /var/www/docker-aio/php/src/Docker/DockerActionManager.php(266): AIO\Data\ConfigurationManager->replaceEnvPlaceholders('HP_LOG_LEVEL=%C...')
#4 /var/www/docker-aio/php/src/Controller/DockerController.php(43): AIO\Docker\DockerActionManager->CreateContainer(Object(AIO\Container\Container))
#5 /var/www/docker-aio/php/src/Controller/DockerController.php(30): AIO\Controller\DockerController->PerformRecursiveContainerStart('nextcloud-aio-h...', true, Object(Closure))
#6 /var/www/docker-aio/php/src/Controller/DockerController.php(268): AIO\Controller\DockerController->PerformRecursiveContainerStart('nextcloud-aio-a...', true, Object(Closure))
#7 /var/www/docker-aio/php/src/Controller/DockerController.php(249): AIO\Controller\DockerController->startTopContainer(true, Object(Closure))
#8 /var/www/docker-aio/php/vendor/slim/slim/Slim/Handlers/Strategies/RequestResponse.php(39): AIO\Controller\DockerController->StartContainer(Object(Slim\Psr7\Request), Object(Slim\Psr7\Response), Array)
#9 /var/www/docker-aio/php/vendor/slim/slim/Slim/Routing/Route.php(362): Slim\Handlers\Strategies\RequestResponse->__invoke(Array, Object(Slim\Psr7\Request), Object(Slim\Psr7\Response), Array)
#10 /var/www/docker-aio/php/vendor/slim/slim/Slim/MiddlewareDispatcher.php(73): Slim\Routing\Route->handle(Object(Slim\Psr7\Request))
#11 /var/www/docker-aio/php/vendor/slim/slim/Slim/MiddlewareDispatcher.php(73): Slim\MiddlewareDispatcher->handle(Object(Slim\Psr7\Request))
#12 /var/www/docker-aio/php/vendor/slim/slim/Slim/Routing/Route.php(321): Slim\MiddlewareDispatcher->handle(Object(Slim\Psr7\Request))
#13 /var/www/docker-aio/php/vendor/slim/slim/Slim/Routing/RouteRunner.php(74): Slim\Routing\Route->run(Object(Slim\Psr7\Request))
#14 /var/www/docker-aio/php/vendor/slim/csrf/src/Guard.php(482): Slim\Routing\RouteRunner->handle(Object(Slim\Psr7\Request))
#15 /var/www/docker-aio/php/vendor/slim/slim/Slim/MiddlewareDispatcher.php(178): Slim\Csrf\Guard->process(Object(Slim\Psr7\Request), Object(Slim\Routing\RouteRunner))
#16 /var/www/docker-aio/php/vendor/slim/twig-view/src/TwigMiddleware.php(117): Psr\Http\Server\RequestHandlerInterface@anonymous->handle(Object(Slim\Psr7\Request))
#17 /var/www/docker-aio/php/vendor/slim/slim/Slim/MiddlewareDispatcher.php(129): Slim\Views\TwigMiddleware->process(Object(Slim\Psr7\Request), Object(Psr\Http\Server\RequestHandlerInterface@anonymous))
#18 /var/www/docker-aio/php/src/Middleware/AuthMiddleware.php(54): Psr\Http\Server\RequestHandlerInterface@anonymous->handle(Object(Slim\Psr7\Request))
#19 /var/www/docker-aio/php/vendor/slim/slim/Slim/MiddlewareDispatcher.php(283): AIO\Middleware\AuthMiddleware->__invoke(Object(Slim\Psr7\Request), Object(Psr\Http\Server\RequestHandlerInterface@anonymous))
#20 /var/www/docker-aio/php/vendor/slim/slim/Slim/Middleware/ErrorMiddleware.php(77): Psr\Http\Server\RequestHandlerInterface@anonymous->handle(Object(Slim\Psr7\Request))
#21 /var/www/docker-aio/php/vendor/slim/slim/Slim/MiddlewareDispatcher.php(129): Slim\Middleware\ErrorMiddleware->process(Object(Slim\Psr7\Request), Object(Psr\Http\Server\RequestHandlerInterface@anonymous))
#22 /var/www/docker-aio/php/vendor/slim/slim/Slim/MiddlewareDispatcher.php(73): Psr\Http\Server\RequestHandlerInterface@anonymous->handle(Object(Slim\Psr7\Request))
#23 /var/www/docker-aio/php/vendor/slim/slim/Slim/App.php(209): Slim\MiddlewareDispatcher->handle(Object(Slim\Psr7\Request))
#24 /var/www/docker-aio/php/vendor/slim/slim/Slim/App.php(193): Slim\App->handle(Object(Slim\Psr7\Request))
#25 /var/www/docker-aio/php/public/index.php(259): Slim\App->run()
#26 {main}
Tips: To display error details in HTTP response set "displayErrorDetails" to true in the ErrorHandler constructor.
```
#### Output of `sudo docker inspect nextcloud-aio-mastercontainer`
```bash
[
    {
        "Id": "96a1f021b9b5666cac02bb42165008e4197a4efc6eb39f516486ed972d41c303",
        "Created": "2026-05-01T03:30:50.497668654Z",
        "Path": "/start.sh",
        "Args": [],
        "State": {
            "Status": "running",
            "Running": true,
            "Paused": false,
            "Restarting": false,
            "OOMKilled": false,
            "Dead": false,
            "Pid": 10441,
            "ExitCode": 0,
            "Error": "",
            "StartedAt": "2026-05-01T03:30:51.029539315Z",
            "FinishedAt": "0001-01-01T00:00:00Z",
            "Health": {
                "Status": "healthy",
                "FailingStreak": 0,
                "Log": [
                    {
                        "Start": "2026-05-01T18:30:48.406120236+02:00",
                        "End": "2026-05-01T18:30:48.72937842+02:00",
                        "ExitCode": 0,
                        "Output": "Connection to 127.0.0.1 80 port [tcp/http] succeeded!\nConnection to 127.0.0.1 8080 port [tcp/http-alt] succeeded!\nConnection to 127.0.0.1 8443 port [tcp/*] succeeded!\nConnection to 127.0.0.1 9876 port [tcp/*] succeeded!\n"
                    },
                    {
                        "Start": "2026-05-01T18:31:18.730800319+02:00",
                        "End": "2026-05-01T18:31:19.018615369+02:00",
                        "ExitCode": 0,
                        "Output": "Connection to 127.0.0.1 80 port [tcp/http] succeeded!\nConnection to 127.0.0.1 8080 port [tcp/http-alt] succeeded!\nConnection to 127.0.0.1 8443 port [tcp/*] succeeded!\nConnection to 127.0.0.1 9876 port [tcp/*] succeeded!\n"
                    },
                    {
                        "Start": "2026-05-01T18:31:49.020060798+02:00",
                        "End": "2026-05-01T18:31:49.168204581+02:00",
                        "ExitCode": 0,
                        "Output": "Connection to 127.0.0.1 80 port [tcp/http] succeeded!\nConnection to 127.0.0.1 8080 port [tcp/http-alt] succeeded!\nConnection to 127.0.0.1 8443 port [tcp/*] succeeded!\nConnection to 127.0.0.1 9876 port [tcp/*] succeeded!\n"
                    },
                    {
                        "Start": "2026-05-01T18:32:19.169628934+02:00",
                        "End": "2026-05-01T18:32:19.345618154+02:00",
                        "ExitCode": 0,
                        "Output": "Connection to 127.0.0.1 80 port [tcp/http] succeeded!\nConnection to 127.0.0.1 8080 port [tcp/http-alt] succeeded!\nConnection to 127.0.0.1 8443 port [tcp/*] succeeded!\nConnection to 127.0.0.1 9876 port [tcp/*] succeeded!\n"
                    },
                    {
                        "Start": "2026-05-01T18:32:49.346739637+02:00",
                        "End": "2026-05-01T18:32:49.507981486+02:00",
                        "ExitCode": 0,
                        "Output": "Connection to 127.0.0.1 80 port [tcp/http] succeeded!\nConnection to 127.0.0.1 8080 port [tcp/http-alt] succeeded!\nConnection to 127.0.0.1 8443 port [tcp/*] succeeded!\nConnection to 127.0.0.1 9876 port [tcp/*] succeeded!\n"
                    }
                ]
            }
        },
        "Image": "sha256:aa13ba3f421e93d2c7d9c1839785d24b263d344598f0563ae8f4bb257417bfcc",
        "ResolvConfPath": "/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/containers/96a1f021b9b5666cac02bb42165008e4197a4efc6eb39f516486ed972d41c303/resolv.conf",
        "HostnamePath": "/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/containers/96a1f021b9b5666cac02bb42165008e4197a4efc6eb39f516486ed972d41c303/hostname",
        "HostsPath": "/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/containers/96a1f021b9b5666cac02bb42165008e4197a4efc6eb39f516486ed972d41c303/hosts",
        "LogPath": "/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/containers/96a1f021b9b5666cac02bb42165008e4197a4efc6eb39f516486ed972d41c303/96a1f021b9b5666cac02bb42165008e4197a4efc6eb39f516486ed972d41c303-json.log",
        "Name": "/nextcloud-aio-mastercontainer",
        "RestartCount": 0,
        "Driver": "overlay2",
        "Platform": "linux",
        "MountLabel": "",
        "ProcessLabel": "",
        "AppArmorProfile": "docker-default",
        "ExecIDs": null,
        "HostConfig": {
            "Binds": [
                "nextcloud_aio_mastercontainer:/mnt/docker-aio-config",
                "/var/run/docker.sock:/var/run/docker.sock:ro"
            ],
            "ContainerIDFile": "",
            "LogConfig": {
                "Type": "json-file",
                "Config": {
                    "max-file": "10",
                    "max-size": "10m"
                }
            },
            "NetworkMode": "bridge",
            "PortBindings": {
                "8080/tcp": [
                    {
                        "HostIp": "",
                        "HostPort": "9080"
                    }
                ]
            },
            "RestartPolicy": {
                "Name": "always",
                "MaximumRetryCount": 0
            },
            "AutoRemove": false,
            "VolumeDriver": "",
            "VolumesFrom": null,
            "ConsoleSize": [
                62,
                237
            ],
            "CapAdd": null,
            "CapDrop": null,
            "CgroupnsMode": "host",
            "Dns": [],
            "DnsOptions": [],
            "DnsSearch": [],
            "ExtraHosts": null,
            "GroupAdd": null,
            "IpcMode": "private",
            "Cgroup": "",
            "Links": null,
            "OomScoreAdj": 0,
            "PidMode": "",
            "Privileged": false,
            "PublishAllPorts": false,
            "ReadonlyRootfs": false,
            "SecurityOpt": null,
            "UTSMode": "",
            "UsernsMode": "",
            "ShmSize": 67108864,
            "Runtime": "runc",
            "Isolation": "",
            "CpuShares": 0,
            "Memory": 0,
            "NanoCpus": 0,
            "CgroupParent": "",
            "BlkioWeight": 0,
            "BlkioWeightDevice": [],
            "BlkioDeviceReadBps": [],
            "BlkioDeviceWriteBps": [],
            "BlkioDeviceReadIOps": [],
            "BlkioDeviceWriteIOps": [],
            "CpuPeriod": 0,
            "CpuQuota": 0,
            "CpuRealtimePeriod": 0,
            "CpuRealtimeRuntime": 0,
            "CpusetCpus": "",
            "CpusetMems": "",
            "Devices": [],
            "DeviceCgroupRules": null,
            "DeviceRequests": null,
            "MemoryReservation": 0,
            "MemorySwap": 0,
            "MemorySwappiness": null,
            "OomKillDisable": false,
            "PidsLimit": null,
            "Ulimits": [
                {
                    "Name": "nofile",
                    "Hard": 65535,
                    "Soft": 65535
                }
            ],
            "CpuCount": 0,
            "CpuPercent": 0,
            "IOMaximumIOps": 0,
            "IOMaximumBandwidth": 0,
            "MaskedPaths": [
                "/proc/asound",
                "/proc/acpi",
                "/proc/kcore",
                "/proc/keys",
                "/proc/latency_stats",
                "/proc/timer_list",
                "/proc/timer_stats",
                "/proc/sched_debug",
                "/proc/scsi",
                "/sys/firmware",
                "/sys/devices/virtual/powercap"
            ],
            "ReadonlyPaths": [
                "/proc/bus",
                "/proc/fs",
                "/proc/irq",
                "/proc/sys",
                "/proc/sysrq-trigger"
            ],
            "Init": true
        },
        "GraphDriver": {
            "Data": {
                "LowerDir": "/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/995d6391cf4b4e77df67cfb5652cf6cc45b201ea4b6b8e50f2a7aed61ea3e0a9-init/diff:/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/58dc7a96dc716e6acd1ac54f4329c9cd8e191faef8cd1a50094f58e49437c612/diff:/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/049552bc652308955c8bcd5478520326fb9e146ad67d9f14fe96ffacea3cf55f/diff:/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/d829b040722e7597334564e94ecee3bc269535e20b71ab0813197e24c0917d96/diff:/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/1c80fd3fa3a2c470d4864d949b2b68bfaa1bf80c7f83bfbfcc7633dcc1a4fc55/diff:/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/6829f2ed453d4c5f372ac0c02ba92cbe196455b9b4d0eeaba78625c167bc56f2/diff:/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/3860ea1478328d00e80fd2705161997e8c52bf08d4e22170092ab1d924418094/diff:/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/69922c34a80afd7a6beb908daf9c70d33cce7bdef1cbe090c0bd8a485e8b8695/diff:/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/16475958ec9296b5ddeb9305ce890a859efe7cf7abddad5fcc868944faf880bd/diff:/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/304a33a23b5656648d5ab113b84c4f2996706de0a3433a1287847800ce7c5a9d/diff:/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/b0105a94893cb0663249528c25e623ffb55fa2ed00d78645fcc4922fcee318a0/diff:/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/e5ebe3e4160a70970facc37680bcfff5d348bebdb946a9bf79bd1ad84cee999d/diff:/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/bac9823dada795165173975583473f1db88859dd5c22ec64139cfb27379d7134/diff:/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/93270d5633126b13504f2a93b0236f46745df5843b984a8473c3241ada88c796/diff:/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/76f74f5e6fe80ea3f18be02b4a10474f5de50528a543ec5c4c490e08cbe8d4c8/diff:/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/f98cae4bc6edd8cc02c6a66c144363a3f695b19695ca3c9fe00ddaf4e6bc45df/diff:/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/6a5702878e19bd9dd9c5201759be6b75b9daa053bbe66289df76b1431cb98c05/diff:/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/e2bec28423eba0d3d86c86f78f1faa42a160f3ad7163de211670ba72076a90bd/diff:/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/91b4c0d3bee805136dea0ae569fbf9eef1fdd19edeeb3ac0fa35399dd476e74f/diff:/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/0fab2603ff6eeac9bfa8ff1741bf234e9ef126b05baf13adb42de3b1393da15d/diff:/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/d1ec11af67f4860e04930e1ea3c73f897192964f540db88538bdfc4f93f2f325/diff",
                "MergedDir": "/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/995d6391cf4b4e77df67cfb5652cf6cc45b201ea4b6b8e50f2a7aed61ea3e0a9/merged",
                "UpperDir": "/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/995d6391cf4b4e77df67cfb5652cf6cc45b201ea4b6b8e50f2a7aed61ea3e0a9/diff",
                "WorkDir": "/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/995d6391cf4b4e77df67cfb5652cf6cc45b201ea4b6b8e50f2a7aed61ea3e0a9/work"
            },
            "Name": "overlay2"
        },
        "Mounts": [
            {
                "Type": "volume",
                "Name": "nextcloud_aio_mastercontainer",
                "Source": "/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/volumes/nextcloud_aio_mastercontainer/_data",
                "Destination": "/mnt/docker-aio-config",
                "Driver": "local",
                "Mode": "z",
                "RW": true,
                "Propagation": ""
            },
            {
                "Type": "bind",
                "Source": "/var/run/docker.sock",
                "Destination": "/var/run/docker.sock",
                "Mode": "ro",
                "RW": false,
                "Propagation": "rprivate"
            }
        ],
        "Config": {
            "Hostname": "5120dddf2f53",
            "Domainname": "",
            "User": "root",
            "AttachStdin": false,
            "AttachStdout": true,
            "AttachStderr": true,
            "ExposedPorts": {
                "80/tcp": {},
                "8080/tcp": {},
                "8443/tcp": {},
                "9000/tcp": {}
            },
            "Tty": false,
            "OpenStdin": false,
            "StdinOnce": false,
            "Env": [
                "APACHE_IP_BINDING=0.0.0.0",
                "APACHE_ADDITIONAL_NETWORK=",
                "SKIP_DOMAIN_VALIDATION=false",
                "NEXTCLOUD_DATADIR=/share/nextcloud_data/data/",
                "APACHE_PORT=11000",
                "PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin",
                "PHPIZE_DEPS=autoconf \t\tdpkg-dev dpkg \t\tfile \t\tg++ \t\tgcc \t\tlibc-dev \t\tmake \t\tpkgconf \t\tre2c",
                "PHP_INI_DIR=/usr/local/etc/php",
                "PHP_CFLAGS=-fstack-protector-strong -fpic -fpie -O2 -D_LARGEFILE_SOURCE -D_FILE_OFFSET_BITS=64",
                "PHP_CPPFLAGS=-fstack-protector-strong -fpic -fpie -O2 -D_LARGEFILE_SOURCE -D_FILE_OFFSET_BITS=64",
                "PHP_LDFLAGS=-Wl,-O1 -pie",
                "GPG_KEYS=1198C0117593497A5EC5C199286AF1F9897469DC 49D9AF6BC72A80D6691719C8AA23F5BE9C7097D4 D95C03BC702BE9515344AE3374E44BC9067701A5",
                "PHP_VERSION=8.5.5",
                "PHP_URL=https://www.php.net/distributions/php-8.5.5.tar.xz",
                "PHP_ASC_URL=https://www.php.net/distributions/php-8.5.5.tar.xz.asc",
                "PHP_SHA256=95bec382f4bd00570a8ef52a58ec04d8d9b9a90494781f1c106d1b274a3902f2",
                "HOME=/var/www"
            ],
            "Cmd": null,
            "Healthcheck": {
                "Test": [
                    "CMD-SHELL",
                    "/healthcheck.sh"
                ]
            },
            "Image": "nextcloud/all-in-one:beta",
            "Volumes": null,
            "WorkingDir": "/var/www/docker-aio",
            "Entrypoint": [
                "/start.sh"
            ],
            "OnBuild": null,
            "Labels": {
                "com.docker.compose.project": "nextcloud-aio",
                "org.opencontainers.image.description": "Easy deployment and maintenance of a Nextcloud server with all dependencies and optional services",
                "org.opencontainers.image.documentation": "https://github.com/nextcloud/all-in-one/blob/main/readme.md",
                "org.opencontainers.image.source": "https://github.com/nextcloud/all-in-one",
                "org.opencontainers.image.title": "Nextcloud All-in-One Mastercontainer",
                "org.opencontainers.image.url": "https://github.com/nextcloud/all-in-one",
                "org.opencontainers.image.vendor": "Nextcloud",
                "wud.watch": "false"
            },
            "StopSignal": "SIGQUIT"
        },
        "NetworkSettings": {
            "Bridge": "lxcbr0",
            "SandboxID": "70392ea192041212c2ffdc839d9e8cced924d6d45f35dd69a065b56b3caf208e",
            "SandboxKey": "/var/run/docker/netns/70392ea19204",
            "Ports": {
                "80/tcp": null,
                "8080/tcp": [
                    {
                        "HostIp": "0.0.0.0",
                        "HostPort": "9080"
                    }
                ],
                "8443/tcp": null,
                "9000/tcp": null
            },
            "HairpinMode": false,
            "LinkLocalIPv6Address": "",
            "LinkLocalIPv6PrefixLen": 0,
            "SecondaryIPAddresses": null,
            "SecondaryIPv6Addresses": null,
            "EndpointID": "",
            "Gateway": "",
            "GlobalIPv6Address": "",
            "GlobalIPv6PrefixLen": 0,
            "IPAddress": "",
            "IPPrefixLen": 0,
            "IPv6Gateway": "",
            "MacAddress": "",
            "Networks": {
                "nextcloud-aio": {
                    "IPAMConfig": {},
                    "Links": null,
                    "Aliases": [],
                    "MacAddress": "02:42:ac:1d:04:02",
                    "DriverOpts": {},
                    "NetworkID": "217ba88943bb96d46e80c47c020fd9f6f86707456f4af3511a6f98bd38c5d790",
                    "EndpointID": "041620fad6ccb0b7c4b5f1efc53a7c1452030f8a9a400298b5741f3b46d510fd",
                    "Gateway": "172.29.4.1",
                    "IPAddress": "172.29.4.2",
                    "IPPrefixLen": 22,
                    "IPv6Gateway": "",
                    "GlobalIPv6Address": "",
                    "GlobalIPv6PrefixLen": 0,
                    "DNSNames": [
                        "nextcloud-aio-mastercontainer",
                        "96a1f021b9b5",
                        "5120dddf2f53"
                    ]
                }
            }
        }
    }
]
(Object(Slim\Psr7\Request))
#25 /var/www/docker-aio/php/public/index.php(259): Slim\App->run()
#26 {main}
Tips: To display error details in HTTP response set "displayErrorDetails" to true in the ErrorHandler constructor.
[~] #
[~] # sudo docker inspect nextcloud-aio-mastercontainer
[
    {
        "Id": "96a1f021b9b5666cac02bb42165008e4197a4efc6eb39f516486ed972d41c303",
        "Created": "2026-05-01T03:30:50.497668654Z",
        "Path": "/start.sh",
        "Args": [],
        "State": {
            "Status": "running",
            "Running": true,
            "Paused": false,
            "Restarting": false,
            "OOMKilled": false,
            "Dead": false,
            "Pid": 10441,
            "ExitCode": 0,
            "Error": "",
            "StartedAt": "2026-05-01T03:30:51.029539315Z",
            "FinishedAt": "0001-01-01T00:00:00Z",
            "Health": {
                "Status": "healthy",
                "FailingStreak": 0,
                "Log": [
                    {
                        "Start": "2026-05-01T18:30:48.406120236+02:00",
                        "End": "2026-05-01T18:30:48.72937842+02:00",
                        "ExitCode": 0,
                        "Output": "Connection to 127.0.0.1 80 port [tcp/http] succeeded!\nConnection to 127.0.0.1 8080 port [tcp/http-alt] succeeded!\nConnection to 127.0.0.1 8443 port [tcp/*] succeeded!\nConnection to 127.0.0.1 9876 port [tcp/*] succeeded!\n"
                    },
                    {
                        "Start": "2026-05-01T18:31:18.730800319+02:00",
                        "End": "2026-05-01T18:31:19.018615369+02:00",
                        "ExitCode": 0,
                        "Output": "Connection to 127.0.0.1 80 port [tcp/http] succeeded!\nConnection to 127.0.0.1 8080 port [tcp/http-alt] succeeded!\nConnection to 127.0.0.1 8443 port [tcp/*] succeeded!\nConnection to 127.0.0.1 9876 port [tcp/*] succeeded!\n"
                    },
                    {
                        "Start": "2026-05-01T18:31:49.020060798+02:00",
                        "End": "2026-05-01T18:31:49.168204581+02:00",
                        "ExitCode": 0,
                        "Output": "Connection to 127.0.0.1 80 port [tcp/http] succeeded!\nConnection to 127.0.0.1 8080 port [tcp/http-alt] succeeded!\nConnection to 127.0.0.1 8443 port [tcp/*] succeeded!\nConnection to 127.0.0.1 9876 port [tcp/*] succeeded!\n"
                    },
                    {
                        "Start": "2026-05-01T18:32:19.169628934+02:00",
                        "End": "2026-05-01T18:32:19.345618154+02:00",
                        "ExitCode": 0,
                        "Output": "Connection to 127.0.0.1 80 port [tcp/http] succeeded!\nConnection to 127.0.0.1 8080 port [tcp/http-alt] succeeded!\nConnection to 127.0.0.1 8443 port [tcp/*] succeeded!\nConnection to 127.0.0.1 9876 port [tcp/*] succeeded!\n"
                    },
                    {
                        "Start": "2026-05-01T18:32:49.346739637+02:00",
                        "End": "2026-05-01T18:32:49.507981486+02:00",
                        "ExitCode": 0,
                        "Output": "Connection to 127.0.0.1 80 port [tcp/http] succeeded!\nConnection to 127.0.0.1 8080 port [tcp/http-alt] succeeded!\nConnection to 127.0.0.1 8443 port [tcp/*] succeeded!\nConnection to 127.0.0.1 9876 port [tcp/*] succeeded!\n"
                    }
                ]
            }
        },
        "Image": "sha256:aa13ba3f421e93d2c7d9c1839785d24b263d344598f0563ae8f4bb257417bfcc",
        "ResolvConfPath": "/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/containers/96a1f021b9b5666cac02bb42165008e4197a4efc6eb39f516486ed972d41c303/resolv.conf",
        "HostnamePath": "/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/containers/96a1f021b9b5666cac02bb42165008e4197a4efc6eb39f516486ed972d41c303/hostname",
        "HostsPath": "/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/containers/96a1f021b9b5666cac02bb42165008e4197a4efc6eb39f516486ed972d41c303/hosts",
        "LogPath": "/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/containers/96a1f021b9b5666cac02bb42165008e4197a4efc6eb39f516486ed972d41c303/96a1f021b9b5666cac02bb42165008e4197a4efc6eb39f516486ed972d41c303-json.log",
        "Name": "/nextcloud-aio-mastercontainer",
        "RestartCount": 0,
        "Driver": "overlay2",
        "Platform": "linux",
        "MountLabel": "",
        "ProcessLabel": "",
        "AppArmorProfile": "docker-default",
        "ExecIDs": null,
        "HostConfig": {
            "Binds": [
                "nextcloud_aio_mastercontainer:/mnt/docker-aio-config",
                "/var/run/docker.sock:/var/run/docker.sock:ro"
            ],
            "ContainerIDFile": "",
            "LogConfig": {
                "Type": "json-file",
                "Config": {
                    "max-file": "10",
                    "max-size": "10m"
                }
            },
            "NetworkMode": "bridge",
            "PortBindings": {
                "8080/tcp": [
                    {
                        "HostIp": "",
                        "HostPort": "9080"
                    }
                ]
            },
            "RestartPolicy": {
                "Name": "always",
                "MaximumRetryCount": 0
            },
            "AutoRemove": false,
            "VolumeDriver": "",
            "VolumesFrom": null,
            "ConsoleSize": [
                62,
                237
            ],
            "CapAdd": null,
            "CapDrop": null,
            "CgroupnsMode": "host",
            "Dns": [],
            "DnsOptions": [],
            "DnsSearch": [],
            "ExtraHosts": null,
            "GroupAdd": null,
            "IpcMode": "private",
            "Cgroup": "",
            "Links": null,
            "OomScoreAdj": 0,
            "PidMode": "",
            "Privileged": false,
            "PublishAllPorts": false,
            "ReadonlyRootfs": false,
            "SecurityOpt": null,
            "UTSMode": "",
            "UsernsMode": "",
            "ShmSize": 67108864,
            "Runtime": "runc",
            "Isolation": "",
            "CpuShares": 0,
            "Memory": 0,
            "NanoCpus": 0,
            "CgroupParent": "",
            "BlkioWeight": 0,
            "BlkioWeightDevice": [],
            "BlkioDeviceReadBps": [],
            "BlkioDeviceWriteBps": [],
            "BlkioDeviceReadIOps": [],
            "BlkioDeviceWriteIOps": [],
            "CpuPeriod": 0,
            "CpuQuota": 0,
            "CpuRealtimePeriod": 0,
            "CpuRealtimeRuntime": 0,
            "CpusetCpus": "",
            "CpusetMems": "",
            "Devices": [],
            "DeviceCgroupRules": null,
            "DeviceRequests": null,
            "MemoryReservation": 0,
            "MemorySwap": 0,
            "MemorySwappiness": null,
            "OomKillDisable": false,
            "PidsLimit": null,
            "Ulimits": [
                {
                    "Name": "nofile",
                    "Hard": 65535,
                    "Soft": 65535
                }
            ],
            "CpuCount": 0,
            "CpuPercent": 0,
            "IOMaximumIOps": 0,
            "IOMaximumBandwidth": 0,
            "MaskedPaths": [
                "/proc/asound",
                "/proc/acpi",
                "/proc/kcore",
                "/proc/keys",
                "/proc/latency_stats",
                "/proc/timer_list",
                "/proc/timer_stats",
                "/proc/sched_debug",
                "/proc/scsi",
                "/sys/firmware",
                "/sys/devices/virtual/powercap"
            ],
            "ReadonlyPaths": [
                "/proc/bus",
                "/proc/fs",
                "/proc/irq",
                "/proc/sys",
                "/proc/sysrq-trigger"
            ],
            "Init": true
        },
        "GraphDriver": {
            "Data": {
                "LowerDir": "/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/995d6391cf4b4e77df67cfb5652cf6cc45b201ea4b6b8e50f2a7aed61ea3e0a9-init/diff:/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/58dc7a96dc716e6acd1ac54f4329c9cd8e191faef8cd1a50094f58e49437c612/diff:/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/049552bc652308955c8bcd5478520326fb9e146ad67d9f14fe96ffacea3cf55f/diff:/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/d829b040722e7597334564e94ecee3bc269535e20b71ab0813197e24c0917d96/diff:/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/1c80fd3fa3a2c470d4864d949b2b68bfaa1bf80c7f83bfbfcc7633dcc1a4fc55/diff:/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/6829f2ed453d4c5f372ac0c02ba92cbe196455b9b4d0eeaba78625c167bc56f2/diff:/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/3860ea1478328d00e80fd2705161997e8c52bf08d4e22170092ab1d924418094/diff:/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/69922c34a80afd7a6beb908daf9c70d33cce7bdef1cbe090c0bd8a485e8b8695/diff:/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/16475958ec9296b5ddeb9305ce890a859efe7cf7abddad5fcc868944faf880bd/diff:/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/304a33a23b5656648d5ab113b84c4f2996706de0a3433a1287847800ce7c5a9d/diff:/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/b0105a94893cb0663249528c25e623ffb55fa2ed00d78645fcc4922fcee318a0/diff:/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/e5ebe3e4160a70970facc37680bcfff5d348bebdb946a9bf79bd1ad84cee999d/diff:/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/bac9823dada795165173975583473f1db88859dd5c22ec64139cfb27379d7134/diff:/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/93270d5633126b13504f2a93b0236f46745df5843b984a8473c3241ada88c796/diff:/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/76f74f5e6fe80ea3f18be02b4a10474f5de50528a543ec5c4c490e08cbe8d4c8/diff:/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/f98cae4bc6edd8cc02c6a66c144363a3f695b19695ca3c9fe00ddaf4e6bc45df/diff:/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/6a5702878e19bd9dd9c5201759be6b75b9daa053bbe66289df76b1431cb98c05/diff:/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/e2bec28423eba0d3d86c86f78f1faa42a160f3ad7163de211670ba72076a90bd/diff:/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/91b4c0d3bee805136dea0ae569fbf9eef1fdd19edeeb3ac0fa35399dd476e74f/diff:/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/0fab2603ff6eeac9bfa8ff1741bf234e9ef126b05baf13adb42de3b1393da15d/diff:/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/d1ec11af67f4860e04930e1ea3c73f897192964f540db88538bdfc4f93f2f325/diff",
                "MergedDir": "/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/995d6391cf4b4e77df67cfb5652cf6cc45b201ea4b6b8e50f2a7aed61ea3e0a9/merged",
                "UpperDir": "/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/995d6391cf4b4e77df67cfb5652cf6cc45b201ea4b6b8e50f2a7aed61ea3e0a9/diff",
                "WorkDir": "/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/overlay2/995d6391cf4b4e77df67cfb5652cf6cc45b201ea4b6b8e50f2a7aed61ea3e0a9/work"
            },
            "Name": "overlay2"
        },
        "Mounts": [
            {
                "Type": "volume",
                "Name": "nextcloud_aio_mastercontainer",
                "Source": "/share/CACHEDEV2_DATA/Container/container-station-data/lib/docker/volumes/nextcloud_aio_mastercontainer/_data",
                "Destination": "/mnt/docker-aio-config",
                "Driver": "local",
                "Mode": "z",
                "RW": true,
                "Propagation": ""
            },
            {
                "Type": "bind",
                "Source": "/var/run/docker.sock",
                "Destination": "/var/run/docker.sock",
                "Mode": "ro",
                "RW": false,
                "Propagation": "rprivate"
            }
        ],
        "Config": {
            "Hostname": "5120dddf2f53",
            "Domainname": "",
            "User": "root",
            "AttachStdin": false,
            "AttachStdout": true,
            "AttachStderr": true,
            "ExposedPorts": {
                "80/tcp": {},
                "8080/tcp": {},
                "8443/tcp": {},
                "9000/tcp": {}
            },
            "Tty": false,
            "OpenStdin": false,
            "StdinOnce": false,
            "Env": [
                "APACHE_IP_BINDING=0.0.0.0",
                "APACHE_ADDITIONAL_NETWORK=",
                "SKIP_DOMAIN_VALIDATION=false",
                "NEXTCLOUD_DATADIR=/share/nextcloud_data/data/",
                "APACHE_PORT=11000",
                "PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin",
                "PHPIZE_DEPS=autoconf \t\tdpkg-dev dpkg \t\tfile \t\tg++ \t\tgcc \t\tlibc-dev \t\tmake \t\tpkgconf \t\tre2c",
                "PHP_INI_DIR=/usr/local/etc/php",
                "PHP_CFLAGS=-fstack-protector-strong -fpic -fpie -O2 -D_LARGEFILE_SOURCE -D_FILE_OFFSET_BITS=64",
                "PHP_CPPFLAGS=-fstack-protector-strong -fpic -fpie -O2 -D_LARGEFILE_SOURCE -D_FILE_OFFSET_BITS=64",
                "PHP_LDFLAGS=-Wl,-O1 -pie",
                "GPG_KEYS=1198C0117593497A5EC5C199286AF1F9897469DC 49D9AF6BC72A80D6691719C8AA23F5BE9C7097D4 D95C03BC702BE9515344AE3374E44BC9067701A5",
                "PHP_VERSION=8.5.5",
                "PHP_URL=https://www.php.net/distributions/php-8.5.5.tar.xz",
                "PHP_ASC_URL=https://www.php.net/distributions/php-8.5.5.tar.xz.asc",
                "PHP_SHA256=95bec382f4bd00570a8ef52a58ec04d8d9b9a90494781f1c106d1b274a3902f2",
                "HOME=/var/www"
            ],
            "Cmd": null,
            "Healthcheck": {
                "Test": [
                    "CMD-SHELL",
                    "/healthcheck.sh"
                ]
            },
            "Image": "nextcloud/all-in-one:beta",
            "Volumes": null,
            "WorkingDir": "/var/www/docker-aio",
            "Entrypoint": [
                "/start.sh"
            ],
            "OnBuild": null,
            "Labels": {
                "com.docker.compose.project": "nextcloud-aio",
                "org.opencontainers.image.description": "Easy deployment and maintenance of a Nextcloud server with all dependencies and optional services",
                "org.opencontainers.image.documentation": "https://github.com/nextcloud/all-in-one/blob/main/readme.md",
                "org.opencontainers.image.source": "https://github.com/nextcloud/all-in-one",
                "org.opencontainers.image.title": "Nextcloud All-in-One Mastercontainer",
                "org.opencontainers.image.url": "https://github.com/nextcloud/all-in-one",
                "org.opencontainers.image.vendor": "Nextcloud",
                "wud.watch": "false"
            },
            "StopSignal": "SIGQUIT"
        },
        "NetworkSettings": {
            "Bridge": "lxcbr0",
            "SandboxID": "70392ea192041212c2ffdc839d9e8cced924d6d45f35dd69a065b56b3caf208e",
            "SandboxKey": "/var/run/docker/netns/70392ea19204",
            "Ports": {
                "80/tcp": null,
                "8080/tcp": [
                    {
                        "HostIp": "0.0.0.0",
                        "HostPort": "9080"
                    }
                ],
                "8443/tcp": null,
                "9000/tcp": null
            },
            "HairpinMode": false,
            "LinkLocalIPv6Address": "",
            "LinkLocalIPv6PrefixLen": 0,
            "SecondaryIPAddresses": null,
            "SecondaryIPv6Addresses": null,
            "EndpointID": "",
            "Gateway": "",
            "GlobalIPv6Address": "",
            "GlobalIPv6PrefixLen": 0,
            "IPAddress": "",
            "IPPrefixLen": 0,
            "IPv6Gateway": "",
            "MacAddress": "",
            "Networks": {
                "nextcloud-aio": {
                    "IPAMConfig": {},
                    "Links": null,
                    "Aliases": [],
                    "MacAddress": "02:42:ac:1d:04:02",
                    "DriverOpts": {},
                    "NetworkID": "217ba88943bb96d46e80c47c020fd9f6f86707456f4af3511a6f98bd38c5d790",
                    "EndpointID": "041620fad6ccb0b7c4b5f1efc53a7c1452030f8a9a400298b5741f3b46d510fd",
                    "Gateway": "172.29.4.1",
                    "IPAddress": "172.29.4.2",
                    "IPPrefixLen": 22,
                    "IPv6Gateway": "",
                    "GlobalIPv6Address": "",
                    "GlobalIPv6PrefixLen": 0,
                    "DNSNames": [
                        "nextcloud-aio-mastercontainer",
                        "96a1f021b9b5",
                        "5120dddf2f53"
                    ]
                }
            }
        }
    }
]
```

#### Output of `sudo docker ps -a`
```bash
CONTAINER ID IMAGE COMMAND CREATED STATUS PORTS NAMES
43edee7a3925 ghcr.io/nextcloud-releases/aio-whiteboard:beta "/start.sh" 32 minutes ago Up 32 minutes (healthy) 3002/tcp nextcloud-aio-whiteboard
ef7f707db4e6 ghcr.io/nextcloud-releases/aio-notify-push:beta "/start.sh" 32 minutes ago Up 32 minutes (healthy) nextcloud-aio-notify-push
1b660edd8d0d ghcr.io/nextcloud-releases/aio-talk:beta "/start.sh superviso…" 32 minutes ago Up 32 minutes (healthy) 0.0.0.0:3478->3478/tcp, 0.0.0.0:3478->3478/udp nextcloud-aio-talk
0aba3862a0a1 ghcr.io/nextcloud-releases/aio-collabora:beta "/start.sh" 32 minutes ago Up 32 minutes (healthy) 9980/tcp nextcloud-aio-collabora
878e064fde24 ghcr.io/nextcloud-releases/aio-apache:beta "/start.sh /usr/bin/…" 48 minutes ago Exited (137) 36 minutes ago nextcloud-aio-apache
c3ed020c95dd ghcr.io/nextcloud-releases/aio-nextcloud:beta "/start.sh /usr/bin/…" 48 minutes ago Exited (0) 35 minutes ago nextcloud-aio-nextcloud
36937f782735 ghcr.io/nextcloud-releases/aio-imaginary:beta "/start.sh" 48 minutes ago Exited (0) 34 minutes ago nextcloud-aio-imaginary
b225da082dd7 ghcr.io/nextcloud-releases/aio-clamav:beta "/start.sh /usr/bin/…" 48 minutes ago Exited (0) 34 minutes ago nextcloud-aio-clamav
08ea63e516d0 ghcr.io/nextcloud-releases/aio-redis:beta "/start.sh" 48 minutes ago Exited (0) 34 minutes ago nextcloud-aio-redis
4d1240b4dea7 ghcr.io/nextcloud-releases/aio-postgresql:beta "/start.sh" 48 minutes ago Exited (0) 34 minutes ago nextcloud-aio-database
b7a73d476c44 ghcr.io/nextcloud-releases/aio-borgbackup:beta "/start.sh" 13 hours ago Exited (0) 13 hours ago nextcloud-aio-borgbackup
2a5cca25bf27 ghcr.io/nextcloud-releases/aio-watchtower:beta "/start.sh" 13 hours ago Exited (0) 13 hours ago nextcloud-aio-watchtower
96a1f021b9b5 nextcloud/all-in-one:beta "/start.sh" 13 hours ago Up 13 hours (healthy) 80/tcp, 8443/tcp, 9000/tcp, 0.0.0.0:9080->8080/tcp nextcloud-aio-mastercontainer
1c64b63caec9 ghcr.io/paperless-ngx/paperless-ngx:latest "/init" 2 days ago Up 2 days (healthy) 0.0.0.0:8000->8000/tcp paperless
399dd14024d8 postgres:16 "docker-entrypoint.s…" 2 days ago Up 2 days 5432/tcp paperless-db
58422d808034 gotenberg/gotenberg:latest "/usr/bin/tini -- go…" 2 days ago Up 2 days 3000/tcp paperless-gotenberg
4c1a2608af8d redis:7 "docker-entrypoint.s…" 2 days ago Up 2 days 6379/tcp paperless-redis
3348d4253812 postgres:16 "bash -c 'bash -s <<…" 2 days ago Up 2 days 5432/tcp paperless-db-backup
924f3a2bb467 ghcr.io/immich-app/immich-server:release "tini -- /bin/bash -…" 2 weeks ago Up 2 weeks (healthy) 0.0.0.0:2283->2283/tcp immich
2675bb5ab752 ghcr.io/immich-app/immich-machine-learning:release "tini -- python -m i…" 2 weeks ago Up 2 weeks (healthy) immich-machine-learning
a446fcf908f9 apache/tika:latest "/bin/sh -c 'exec ja…" 2 weeks ago Up 2 weeks 9998/tcp paperless-tika
7c72fbeae0db redis:7-alpine "docker-entrypoint.s…" 2 months ago Up 2 weeks (healthy) 6379/tcp immich_redis
db234fa81c79 caddy:alpine "caddy run --config …" 2 months ago Up 13 days caddy
2912421fcf7a buanet/iobroker:latest "/bin/bash -c /opt/s…" 2 months ago Exited (137) 2 months ago iobroker-1
edf1292c8b97 ollama/ollama:latest "/bin/ollama serve" 2 months ago Exited (0) 2 months ago ollama-1
83ad71a1f712 prodrigestivill/postgres-backup-local:16 "/init.sh" 6 months ago Up 2 weeks (healthy) 5432/tcp immich_db_backup
64803f85a903 ghcr.io/szaimen/aio-borgbackup-viewer:v1 "/init" 10 months ago Exited (0) 10 months ago nextcloud-aio-borgbackup-viewer
28b796f03772 tensorchord/pgvecto-rs:pg16-v0.3.0 "docker-entrypoint.s…" 10 months ago Up 2 weeks (healthy) 5432/tcp immich_db
4bb2dd36f651 ecomailz/donnie:1.0.8 "/bin/sh -c /opt/eco…" 13 months ago Up 2 weeks 0.0.0.0:8888->8888/tcp ecoMailz
```

#### Other valuable info <!--- (like additional logs, screenshots & Co.) -->
+ Here is a screenshot of the error screen displayed:
<img width="582" height="398" alt="grafik" src="https://github.com/user-attachments/assets/b6923f09-8553-49b4-a518-6e12cec198f5" />
