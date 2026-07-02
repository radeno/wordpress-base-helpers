<?php
/**
 * Plugin Name:       C3W base helpers
 * Description:       Fundamental helpers
 * Version:           1.4.6
 * Requires at least: 6.7
 * Requires PHP:      8.2
 * Text Domain:       base-helpers
 */

namespace helper;

require_once __DIR__ . '/vendor/autoload.php';

AdminHelper::initActionsAndFilters();
CacheHelper::initActionsAndFilters();
CapabilityHelper::initActionsAndFilters();
MenuHelper::initActionsAndFilters();
QueryHelper::initActionsAndFilters();
RestApiHelper::initActionsAndFilters();
RewriteHelper::initActionsAndFilters();
SecurityHelper::initActionsAndFilters();
TaxonomyHelper::initActionsAndFilters();
