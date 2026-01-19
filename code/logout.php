<?php
/**
 * Opentape - Logout
 */

require_once("opentape_common.php");

if (is_logged_in()) {
    remove_session();
}

header("Location: " . $REL_PATH);
exit();
