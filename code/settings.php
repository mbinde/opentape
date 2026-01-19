<?php
/**
 * Opentape - Settings Page
 */

require_once("opentape_common.php");

send_security_headers();

// Require login
if (!is_logged_in()) {
    header("Location: " . $REL_PATH . "code/login.php");
    exit();
}

$prefs_struct = get_opentape_prefs();
$csrf_token = get_csrf_token();

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Opentape / Settings</title>
    <link rel="stylesheet" href="<?php echo $REL_PATH; ?>res/style.css">
</head>
<body>
    <div class="container">
        <div class="banner">
            <h1>OPENTAPE</h1>
            <ul class="nav">
                <li><a href="<?php echo $REL_PATH; ?>code/edit.php">Edit Tape</a></li>
                <li id="active"><a href="<?php echo $REL_PATH; ?>code/settings.php">Settings</a></li>
                <li id="user">
                    <a id="home" href="<?php echo $REL_PATH; ?>">YOUR TAPE &rarr;</a>
                    <a id="logout" href="<?php echo $REL_PATH; ?>code/logout.php">LOG-OUT &rarr;</a>
                </li>
            </ul>
            <div class="ajax_status"></div>
        </div>

        <div class="content">
            <div class="section">
                <h2>Sharing Options</h2>
                <form name="sharing_settings" id="sharing_form">
                    <label>
                        <input type="checkbox" name="display_mp3" id="display_mp3" <?php if (!empty($prefs_struct['display_mp3']) && $prefs_struct['display_mp3'] == 1) echo 'checked'; ?>>
                        Display direct MP3 links on mixtape
                    </label>
                </form>
            </div>

            <div class="section">
                <h2>Change Your Password</h2>
                <form name="password_form" id="password_form">
                    <label for="password1">New PW:</label>
                    <input type="password" id="password1" maxlength="255" size="20"><br>
                    <label for="password2">Re-type:</label>
                    <input type="password" id="password2" maxlength="255" size="20"><br>
                    <input type="button" class="small_button" id="password_button" value="Save New Password">
                </form>
            </div>

            <div class="footer">
                <?php get_version_banner(); ?>
            </div>
        </div>
    </div>

    <script src="<?php echo $REL_PATH; ?>res/statusfader.js"></script>
    <script>
    (function() {
        'use strict';

        const CSRF_TOKEN = <?php echo json_encode($csrf_token); ?>;
        const AJAX_URL = <?php echo json_encode($REL_PATH . 'code/ajax.php'); ?>;
        const fader = new StatusFader();

        // AJAX helper
        function ajaxPost(command, args) {
            fader.set('progress');
            document.body.style.cursor = 'wait';

            const formData = new FormData();
            formData.append('command', command);
            formData.append('args', JSON.stringify(args));
            formData.append('csrf_token', CSRF_TOKEN);

            return fetch(AJAX_URL, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.body.style.cursor = 'default';
                fader.set(data.status ? 'success' : 'failure');
                return data;
            })
            .catch(err => {
                document.body.style.cursor = 'default';
                fader.set('failure');
                console.error('AJAX error:', err);
                throw err;
            });
        }

        // Password change
        const passwordButton = document.getElementById('password_button');
        if (passwordButton) {
            passwordButton.addEventListener('click', function() {
                const p1 = document.getElementById('password1').value;
                const p2 = document.getElementById('password2').value;

                if (!p1 || !p2) {
                    fader.flash('Please fill in both fields', '#ff0000');
                    return;
                }

                if (p1 !== p2) {
                    fader.flash('Passwords do not match', '#ff0000');
                    return;
                }

                ajaxPost('change_password', { password1: p1, password2: p2 })
                    .then(data => {
                        if (data.status) {
                            document.getElementById('password1').value = '';
                            document.getElementById('password2').value = '';
                        }
                    });
            });
        }

        // Display MP3 checkbox
        const displayMp3 = document.getElementById('display_mp3');
        if (displayMp3) {
            displayMp3.addEventListener('change', function() {
                ajaxPost('set_option', { display_mp3: this.checked });
            });
        }
    })();
    </script>
</body>
</html>
