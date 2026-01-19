<?php
/**
 * Opentape - Welcome / Initial Password Setup
 */

require_once("opentape_common.php");

send_security_headers();
init_session();

// If password already set, redirect to login
if (is_password_set()) {
    header("Location: " . $REL_PATH . "code/login.php");
    exit();
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Welcome - Set Password / Opentape</title>
    <link rel="stylesheet" href="<?php echo $REL_PATH; ?>res/style.css">
</head>
<body>
    <div class="container">
        <div class="banner">
            <h1>OPENTAPE</h1>
            <div class="ajax_status"></div>
        </div>

        <div class="content">
            <div class="section">
                <h2>Welcome!</h2>
            </div>

            <div class="section" id="setpassword">
                <h2>Set an admin password on your mixtape</h2>
                <p>Enter the new password twice:</p>
                <form name="password_form" id="password_form">
                    <input type="password" id="password1" maxlength="255" size="20" placeholder="Password" autofocus><br>
                    <input type="password" id="password2" maxlength="255" size="20" placeholder="Confirm password"><br>
                    <input type="button" class="small_button" id="password_button" value="Create Password">
                </form>
            </div>

            <div class="section" style="display:none;" id="uploadnext">
                <a style="font-size: 24px; font-weight: bold;" href="<?php echo $REL_PATH; ?>code/edit.php">Now, add songs!</a>
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

        const AJAX_URL = <?php echo json_encode($REL_PATH . 'code/ajax.php'); ?>;
        const fader = new StatusFader();

        const passwordButton = document.getElementById('password_button');
        const password1 = document.getElementById('password1');
        const password2 = document.getElementById('password2');
        const setPasswordSection = document.getElementById('setpassword');
        const uploadNextSection = document.getElementById('uploadnext');

        if (passwordButton && password1 && password2) {
            passwordButton.addEventListener('click', function() {
                const p1 = password1.value;
                const p2 = password2.value;

                if (!p1 || !p2) {
                    fader.flash('Please enter password twice', '#ff0000');
                    return;
                }

                if (p1 !== p2) {
                    fader.flash('Passwords do not match', '#ff0000');
                    return;
                }

                fader.set('progress');
                document.body.style.cursor = 'wait';

                const formData = new FormData();
                formData.append('command', 'create_password');
                formData.append('args', JSON.stringify({ password1: p1, password2: p2 }));

                fetch(AJAX_URL, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    document.body.style.cursor = 'default';

                    if (data.status) {
                        fader.flash('Password created!', '#008000');
                        setPasswordSection.style.display = 'none';
                        uploadNextSection.style.display = '';
                    } else {
                        fader.set('failure');
                    }
                })
                .catch(err => {
                    document.body.style.cursor = 'default';
                    fader.set('failure');
                    console.error('Error:', err);
                });
            });

            // Allow Enter key to submit
            password2.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    passwordButton.click();
                }
            });
        }
    })();
    </script>
</body>
</html>
