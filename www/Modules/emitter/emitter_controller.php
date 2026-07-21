<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

// Emitter capacity estimator. The UI consumes the signature module's
// signature/list and signature/systems endpoints (same episodes table),
// so this controller only serves the view.
function emitter_controller() {

    global $route;

    if ($route->action == "") {
        return view("Modules/emitter/views/emitter_view.php", array());
    }
}
