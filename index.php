<?php
/**
 * Entry point (bootstrap) for Ezmlm REST service
 * 
 * All requests must be redirected to this file; a .htaccess is shipped for Apache
 */

require 'EzmlmService.php';

// Initialize and run service
$svc = new EzmlmService();
$svc->run();
