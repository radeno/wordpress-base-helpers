<?php
/**
 * Plugin Name:       C3W base helpers
 * Description:       Fundamental helpers
 * Version:           1.4.3
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Text Domain:       base-helpers
 */

namespace helper;

require_once __DIR__ . '/vendor/autoload.php';

require_once "inc/AdminHelper.php";
require_once "inc/AttachmentHelper.php";
require_once "inc/CacheHelper.php";
require_once "inc/CapabilityHelper.php";
require_once "inc/DateTimeHelper.php";
require_once "inc/FileHelper.php";
require_once "inc/FormatterHelper.php";
require_once "inc/LocaleHelper.php";
require_once "inc/MenuHelper.php";
require_once "inc/QueryHelper.php";
require_once "inc/RestApiHelper.php";
require_once "inc/RewriteHelper.php";
require_once "inc/TaxonomyHelper.php";
require_once "inc/PostTypeHelper.php";
require_once "inc/ContentTypeHelper.php";
require_once "inc/SecurityHelper.php";

AdminHelper::initActionsAndFilters();
CacheHelper::initActionsAndFilters();
CapabilityHelper::initActionsAndFilters();
MenuHelper::initActionsAndFilters();
QueryHelper::initActionsAndFilters();
FileHelper::initActionsAndFilters();
RestApiHelper::initActionsAndFilters();
RewriteHelper::initActionsAndFilters();
SecurityHelper::initActionsAndFilters();
TaxonomyHelper::initActionsAndFilters();
