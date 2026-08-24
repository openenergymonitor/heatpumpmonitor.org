# Files shared with emoncms.org

HeatpumpMonitor.org and emoncms.org run on the same server and share one `users`
table. HeatpumpMonitor has no user records of its own: it reads and writes
emoncms's, over the `$emoncms_mysqli` connection. That makes a handful of files
load bearing in both codebases at once, and it means a change to one copy that
does not reach the other locks users out of a site.

The files below are duplicated byte for byte between the two repositories.

| File here | File in emoncms.org |
| --- | --- |
| `www/Lib/password.php` | `Lib/password.php` |
| `www/Lib/EmonLogger.php` | `Lib/EmonLogger.php` |
| `www/Modules/user/rememberme_model.php` | `Modules/user/rememberme_model.php` |

## Why these three

**`www/Lib/password.php`** decides how a stored password hash is produced and
checked. A password written by one site has to be readable by the other. If
these copies disagree, every account that authenticates on one site stops being
able to authenticate on the other. This is not theoretical: before it was
shared, HeatpumpMonitor verified passwords as a single sha256 round, so
emoncms.org's move to bcrypt would have locked every migrated user out of
HeatpumpMonitor the first time they logged in there.

`settings['password']` must also match the linked emoncms install. It only
affects what gets written, never what can be read, so a mismatch does not lock
anyone out, but the two sites would then rewrite each other's hashes on every
login, paying the cost of a rehash each time for nothing.

**`www/Modules/user/rememberme_model.php`** stores persistent login tokens. Both
sites store them in the same emoncms `rememberme` table, which is what lets a
password reset revoke the cookies held on both. Cookies are host scoped, so a
token issued by one site is never presented to the other; only the revocation
and the storage format are shared. The cookie name is a constructor argument, so
the file itself stays identical.

**`www/Lib/EmonLogger.php`** is only here because the remember me model uses it.

## Changing one of them

Edit it in one repository, copy it to the other, and commit both. Then:

    scripts/check_shared.sh /var/www/emoncmsorg-main

which reports any file that has drifted. The same script ships in the emoncms
repository, pointing the other way.

## Where this should end up

Duplication with a checker is the pragmatic arrangement, not the right one. The
right one is a small shared repository holding these files plus the rest of the
credential layer (login verification, hash upgrade, reset token issue and
redeem), pulled into both as a submodule. The split above was chosen so that
that move is mostly mechanical when it happens: nothing in these files depends
on anything else in either codebase except `$settings`.
