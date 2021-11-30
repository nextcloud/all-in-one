<?php
$CONFIG = array (
  'apps_paths' => array (
      0 => array (
              'path'     => OC::$SERVERROOT.'/apps',
              'url'      => '/apps',
              'writable' => false,
      ),
      1 => array (
              'path'     => OC::$SERVERROOT.'/custom_apps',
              'url'      => '/custom_apps',
              'writable' => true,
      ),
  ),
);
