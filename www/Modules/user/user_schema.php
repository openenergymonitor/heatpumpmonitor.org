<?php

// HeatpumpMonitor has no user tables of its own.
//
// Accounts live in the emoncms `users` table and remember me tokens in the
// emoncms `rememberme` table, both reached over $emoncms_mysqli and both
// declared by emoncms's own Modules/user/user_schema.php. Sharing the token
// table is deliberate: it is what makes a password reset on emoncms.org revoke
// the persistent cookies held here. See Lib/SHARED.md.
//
// The local `user_sessions` table this file used to declare held the tokens for
// the old selector and validator scheme, which the shared model replaced.
// Nothing reads it any more. It is not declared here so that update_database.php
// stops maintaining it, and not dropped either, so that rolling back does not
// need a restore. Drop it by hand once the switch has bedded in.
