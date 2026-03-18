<?php

/*
 * Local configuration — overrides values from app.php.
 *
 * Copy this file to app_local.php and adjust as needed.
 * This file is DEEP-MERGED into app.php:
 *   - You only need to define the values you want to CHANGE.
 *   - All other values from app.php remain unchanged.
 *   - Nested arrays (like 'Manual') are merged key-by-key, not replaced.
 *
 * Do NOT commit app_local.php to version control.
 */
return [
    /*
     * Debug: false for production, true for development.
     */
    'debug' => false,

    /*
     * Security salt — MUST be unique per installation.
     * Generate with: php -r "echo bin2hex(random_bytes(32));"
     *
     * IMPORTANT: Set this here, NOT as environment variable.
     * Environment variables are not available to the webserver process.
     */
    'Security' => [
        'salt' => '__SALT__',
    ],

    /*
     * Database connection.
     */
    'Datasources' => [
        'default' => [
            'host' => 'localhost',
            'username' => 'guidevera',
            'password' => 'secret',
            'database' => 'guidevera',
        ],
    ],

    /*
     * Override any Manual settings from app.php.
     * Only include the keys you want to change.
     *
     * Example: Change app name and enable review process:
     *
     * 'Manual' => [
     *     'appName' => 'My Knowledge Base',
     *     'enableReviewProcess' => true,
     * ],
     *
     * All other Manual keys from app.php remain unchanged.
     */
];
