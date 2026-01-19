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

// Check for setup issues
$setup_status = get_setup_status();
$setup_errors = [];

if (!$setup_status['settings_dir']) {
    $setup_errors[] = 'The <code>' . htmlspecialchars(SETTINGS_PATH) . '</code> directory could not be created.';
} elseif (!$setup_status['settings_writable']) {
    $setup_errors[] = 'The <code>' . htmlspecialchars(SETTINGS_PATH) . '</code> directory is not writable.';
}

if (!$setup_status['songs_dir']) {
    $setup_errors[] = 'The <code>' . htmlspecialchars(SONGS_PATH) . '</code> directory could not be created.';
} elseif (!$setup_status['songs_writable']) {
    $setup_errors[] = 'The <code>' . htmlspecialchars(SONGS_PATH) . '</code> directory is not writable.';
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

<?php if (!empty($setup_errors)): ?>
            <div class="section" style="background:#fee; border:1px solid #c00; padding:15px;">
                <h2 style="color:#c00;">Setup Required</h2>
                <p>Please fix the following issues before continuing:</p>
                <ul style="margin:10px 0; padding-left:20px;">
                    <?php foreach ($setup_errors as $error): ?>
                        <li style="margin:5px 0;"><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
                <p style="margin-top:15px;"><strong>How to fix:</strong></p>
                <ol style="margin:10px 0; padding-left:20px; line-height:1.6;">
                    <li>Open your hosting control panel (cPanel, Plesk, etc.)</li>
                    <li>Go to <strong>File Manager</strong></li>
                    <li>Navigate to your Opentape folder</li>
                    <li>Create the <code>settings</code> and <code>songs</code> folders if they don't exist</li>
                    <li>Right-click on each folder and select <strong>Permissions</strong></li>
                    <li>Set permissions to <strong>755</strong> or <strong>775</strong></li>
                </ol>
                <p>Contact your web host if you need help with permissions.</p>
                <p><a href="" onclick="location.reload(); return false;">Refresh this page</a> after making changes.</p>
            </div>
<?php else: ?>
            <div class="section" id="setpassword">
                <h2>Set an admin password on your mixtape</h2>
                <p>Enter the new password twice:</p>
                <form name="password_form" id="password_form">
                    <input type="password" id="password1" maxlength="255" size="20" placeholder="Password" autofocus><br>
                    <input type="password" id="password2" maxlength="255" size="20" placeholder="Confirm password"><br>
                    <input type="button" class="small_button" id="password_button" value="Create Password">
                </form>
                <div id="password_error" style="display:none; margin-top:15px; padding:10px; background:#fee; border:1px solid #c00; color:#c00;"></div>
            </div>
<?php endif; ?>

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
        const passwordError = document.getElementById('password_error');

        function showError(message) {
            if (passwordError) {
                passwordError.textContent = message;
                passwordError.style.display = 'block';
            }
            fader.flash(message, '#ff0000');
        }

        function hideError() {
            if (passwordError) {
                passwordError.style.display = 'none';
            }
        }

        if (passwordButton && password1 && password2) {
            passwordButton.addEventListener('click', function() {
                hideError();
                const p1 = password1.value;
                const p2 = password2.value;

                if (!p1 || !p2) {
                    showError('Please enter password twice');
                    return;
                }

                if (p1 !== p2) {
                    showError('Passwords do not match');
                    return;
                }

                fader.set('progress');
                document.body.style.cursor = 'wait';
                passwordButton.disabled = true;

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
                    passwordButton.disabled = false;

                    if (data.status) {
                        fader.flash('Password created!', '#008000');
                        setPasswordSection.style.display = 'none';
                        uploadNextSection.style.display = '';
                    } else {
                        showError(data.error || 'Failed to create password. Please check directory permissions.');
                    }
                })
                .catch(err => {
                    document.body.style.cursor = 'default';
                    passwordButton.disabled = false;
                    showError('Connection error. Please try again.');
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
