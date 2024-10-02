<?php

/**
 * @file
 * Clears memcache and/or redis cache.
 */

$cacheHost = getenv('CACHE_HOST');

if ($cacheHost) {
  print "Clearing object caches...\n";

  if (class_exists("Memcached")) {
    $m = new \Memcached();
    try {
      if ($m->addServer($cacheHost, 11211) && $m->flush(10)) {
        print "Cleared Memcached...\n";
      }
    }
    catch (\Exception $e) {
    }
  }

  if (class_exists("Redis")) {
    $r = new \Redis();
    // Disable error handler to trap redis connection warning.
    set_error_handler(function () {
      return TRUE;
    });
    try {
      if ($r->connect($cacheHost, 6379, 1)) {
        print "Clearing Redis...\n";
        $r->flushAll();
      }
    }
    catch (\Exception $e) {
    }
    // Restore the error handler
    restore_error_handler();
  }

}
