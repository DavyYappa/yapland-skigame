<?php

/*
 * Front controller on Combell. deploy:rsync_public_to_www copies public/ to the www root,
 * next to deploy/, and deploy:copy_index renames this file to index.php in the release.
 */

use App\Kernel;

require_once dirname(__DIR__).'/deploy/current/vendor/autoload_runtime.php';

return static fn (array $context) => new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
