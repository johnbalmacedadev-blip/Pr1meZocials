<?php

/**
 * Shared-hosting entry point when the web root is the project folder
 * (not /public). Prefer pointing the domain/subdirectory at /public when possible.
 */
require __DIR__.'/public/index.php';
