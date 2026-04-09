<?php
// Mounted as /testdataset/scripts/settings.php in the loader container (see docker/testdataset/run.sh).
// Target Emoncms user for testdataset feeds — override with TESTDATASET_EMONCMS_USER_ID (default 1 = admin from load_dev_env_data).

$userid = (int) (getenv('TESTDATASET_EMONCMS_USER_ID') ?: 1);
$root = rtrim(getenv('EMONCMS_DATADIR') ?: '/var/opt/emoncms', '/');
$emoncms_data_dir = $root . '/';
$phpfina_dir = $emoncms_data_dir . 'phpfina/';
