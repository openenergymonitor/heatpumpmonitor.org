<?php

defined('EMONCMS_EXEC') or die('Restricted access');

/**
 * Load table definitions from Modules/<module>/<module>_schema.php (same discovery as update_database.php).
 *
 * @return array<string, mixed>
 */
function load_module_schemas()
{
    $schema = array();
    $modules_dir = __DIR__ . '/../Modules';
    if (!is_dir($modules_dir)) {
        return $schema;
    }
    $handle = opendir($modules_dir);
    if ($handle === false) {
        return $schema;
    }
    while (($module = readdir($handle)) !== false) {
        if ($module === '.' || $module === '..') {
            continue;
        }
        $path = $modules_dir . '/' . $module;
        if (!is_dir($path)) {
            continue;
        }
        $schema_file = $path . '/' . $module . '_schema.php';
        if (is_file($schema_file)) {
            require $schema_file;
        }
    }
    closedir($handle);

    return $schema;
}
