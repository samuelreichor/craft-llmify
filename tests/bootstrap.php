<?php

/**
 * Bootstrap file for PHPUnit tests
 *
 * This file sets up the test environment for the LLMify plugin.
 * It loads Composer's autoloader and sets up any necessary mocks
 * for Craft CMS dependencies.
 */

declare(strict_types=1);

// Load Composer autoloader
$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';

if (!file_exists($autoloadPath)) {
    throw new RuntimeException(
        'Could not find vendor/autoload.php. Please run "composer install".'
    );
}

require_once $autoloadPath;

// Set up error reporting for tests
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Define CRAFT_BASE_PATH if not already defined (for unit tests that don't need full Craft)
if (!defined('CRAFT_BASE_PATH')) {
    define('CRAFT_BASE_PATH', dirname(__DIR__));
}
