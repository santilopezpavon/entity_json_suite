<?php

/**
 * @file
 * PHPUnit bootstrap for headless_entity_serializer.
 */

$root = dirname(__DIR__, 6);
$loader = require $root . '/vendor/autoload.php';

$loader->addPsr4('Drupal\\headless_entity_serializer\\', __DIR__ . '/../src');
$loader->addPsr4('Drupal\\entity_json_menu\\', dirname(__DIR__, 2) . '/entity_json_menu/src');
$loader->addPsr4('Drupal\\entity_json_serving\\', dirname(__DIR__, 2) . '/entity_json_serving/src');
$loader->addPsr4('Drupal\\path_alias\\', $root . '/web/core/modules/path_alias/src');
$loader->addPsr4('Drupal\\system\\', $root . '/web/core/modules/system/src');

$coreTestDir = $root . '/web/core/tests';
$loader->addPsr4('Drupal\\Tests\\', "$coreTestDir/Drupal/Tests");
$loader->addPsr4('Drupal\\KernelTests\\', "$coreTestDir/Drupal/KernelTests");
$loader->addPsr4('Drupal\\FunctionalTests\\', "$coreTestDir/Drupal/FunctionalTests");
$loader->addPsr4('Drupal\\FunctionalJavascriptTests\\', "$coreTestDir/Drupal/FunctionalJavascriptTests");
$loader->addPsr4('Drupal\\BuildTests\\', "$coreTestDir/Drupal/BuildTests");
$loader->addPsr4('Drupal\\TestTools\\', "$coreTestDir/Drupal/TestTools");
$loader->addPsr4('Drupal\\TestSite\\', "$coreTestDir/Drupal/TestSite");

error_reporting(E_ALL);
ini_set('memory_limit', '-1');
date_default_timezone_set('UTC');
