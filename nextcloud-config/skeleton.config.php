<?php
// Mounted into /var/www/html/config/ by docker-compose.yml. AIO's entrypoint only
// applies NEXTCLOUD_SKELETON_DIRECTORY after `occ maintenance:install`, which has
// already copied the ~62 MB of sample files into the admin account by then. That is
// more than DEFAULT_QUOTA (50 MB), so a fresh admin starts over quota and every upload
// and Collabora save fails with "Not enough storage". Nextcloud reads this file during
// install too, so the setting is in place before any account gets files.
if (getenv('NEXTCLOUD_SKELETON_DIRECTORY') === 'empty') {
  $CONFIG = array(
    'skeletondirectory' => '',
  );
}
