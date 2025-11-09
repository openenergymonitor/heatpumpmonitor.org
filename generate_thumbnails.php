#!/usr/bin/env php
<?php

/**
 * Generate Missing Thumbnails Script
 * 
 * This script finds images without thumbnails and generates them.
 * Can be run manually or via cron job.
 * 
 * Usage:
 *   php generate_thumbnails.php [options] [system_id]
 *   
 * Options:
 *   --force-all             Force regeneration of all thumbnails (even existing ones)
 *   --verbose               Enable verbose output (default: silent for cron usage)
 *   
 * Arguments:
 *   system_id (optional)    Only process images for this specific system
 *   
 * Examples:
 *   php generate_thumbnails.php                          # Generate missing/incomplete thumbnails for all systems (silent)
 *   php generate_thumbnails.php --verbose                # Generate missing/incomplete thumbnails with output
 *   php generate_thumbnails.php 123                      # Generate missing/incomplete thumbnails for system ID 123 (silent)
 *   php generate_thumbnails.php --force-all              # Regenerate ALL thumbnails for all systems (silent)
 *   php generate_thumbnails.php --verbose --force-all    # Regenerate ALL thumbnails with output
 */

// Change to www directory
$dir = dirname(__FILE__);

if(is_dir("/var/www/heatpumpmonitororg")) {
    chdir("/var/www/heatpumpmonitororg");
} elseif(is_dir("$dir/www")) {
    chdir("$dir/www");
} else {
    die("Error: could not find heatpumpmonitor.org directory");
}

define('EMONCMS_EXEC', 1);

// Require core files
require "core.php";

// Load database settings
require "Lib/load_database.php";

// Include required classes
require ("Modules/system/system_model.php");
require ("Modules/system/system_photos_model.php");

// Parse command line arguments
$system_id = null;
$force_all = false;
$verbose = false;

// Check for command line options
if (php_sapi_name() === 'cli' && isset($argv)) {
    for ($i = 1; $i < count($argv); $i++) {
        if ($argv[$i] === '--force-all') {
            $force_all = true;
        } elseif ($argv[$i] === '--verbose') {
            $verbose = true;
        } elseif (is_numeric($argv[$i])) {
            $system_id = (int)$argv[$i];
        }
    }
}

// Initialize models (mysqli connection is already established in load_database.php)
$system = new System($mysqli);
$system_photos = new SystemPhotos($mysqli, $system);

if ($verbose) {
    echo "Starting thumbnail generation script...\n";
    if ($force_all) {
        echo "Mode: Force regeneration of ALL thumbnails\n";
    } else {
        echo "Mode: Generate missing/incomplete thumbnails\n";
    }
    if ($system_id) {
        echo "Processing system ID: $system_id\n";
    } else {
        echo "Processing all systems\n";
    }
    echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
    echo "----------------------------------------\n";
}

// Generate thumbnails 
$results = $system_photos->generateThumbnails($system_id, $force_all);

// Output results
if ($verbose) {
    echo "Results:\n";
    echo "  Total images processed: " . $results['total_processed'] . "\n";
    echo "  Successful: " . $results['successful'] . "\n";
    echo "  Failed: " . $results['failed'] . "\n";
    if (isset($results['skipped'])) {
        echo "  Skipped (already complete): " . $results['skipped'] . "\n";
    }

    if (!empty($results['errors'])) {
        echo "\nErrors:\n";
        foreach ($results['errors'] as $error) {
            echo "  - $error\n";
        }
    }

    echo "\nScript completed at: " . date('Y-m-d H:i:s') . "\n";
}

// Exit with appropriate code
if ($results['failed'] > 0) {
    exit(2); // Some failures occurred
} elseif ($results['total_processed'] == 0) {
    exit(0); // No images to process (normal)
} else {
    exit(0); // All successful
}