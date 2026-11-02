<?php
/**
 * Plugin Name:       C3W base helpers
 * Description:       Fundamental helpers
 * Version:           1.5.0
 * Requires at least: 7.0
 * Requires PHP:      8.4
 * Text Domain:       base-helpers
 */

namespace helper;

// A site that installs this plugin with Composer already autoloads it, along
// with the libphonenumber version it resolved. The bundled autoloader would be
// prepended and load the bundled copy instead.
if (! class_exists(AdminHelper::class)) {
    require_once __DIR__ . '/vendor/autoload.php';
}

AdminHelper::initActionsAndFilters();
CacheHelper::initActionsAndFilters();
CapabilityHelper::initActionsAndFilters();
MenuHelper::initActionsAndFilters();
QueryHelper::initActionsAndFilters();
RestApiHelper::initActionsAndFilters();
RewriteHelper::initActionsAndFilters();
SecurityHelper::initActionsAndFilters();
TaxonomyHelper::initActionsAndFilters();
