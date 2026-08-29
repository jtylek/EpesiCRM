<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

// CRM_RoundcubeCommon::multiwin_supported() used to cache its result forever
// (Cache::set() with no expiration), so an install where the self-test failed
// even transiently (e.g. right after deploy, before .htaccess/mod_rewrite was
// fully in effect) was stuck showing the "single mail window" notice forever,
// even after the RCWIN_ rewrite started working. Clear the stale value so it
// gets re-tested (and now expires/self-heals going forward).
Cache::clear('rc_multiwin');
