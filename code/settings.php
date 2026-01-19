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
                <h2>Display Options</h2>
                <form name="display_settings" id="display_form">
                    <label>
                        <input type="checkbox" name="display_mp3" id="display_mp3" <?php if (!empty($prefs_struct['display_mp3']) && $prefs_struct['display_mp3'] == 1) echo 'checked'; ?>>
                        Display direct MP3 links on mixtape
                    </label><br>
                    <label>
                        <input type="checkbox" name="use_filename" id="use_filename" <?php if (!empty($prefs_struct['use_filename']) && $prefs_struct['use_filename'] == 1) echo 'checked'; ?>>
                        Use filename as track title (ignore ID3 tags)
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

            <div class="section">
                <h2>Updates</h2>
                <div id="update_check">
                    <p>Current version: <strong><?php echo htmlspecialchars(VERSION, ENT_QUOTES, 'UTF-8'); ?></strong></p>
                    <input type="button" class="small_button" id="check_updates_button" value="Check for Updates">
                </div>
                <div id="update_result" style="display:none; margin-top: 15px;"></div>
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
        const GITHUB_REPO = <?php echo json_encode(GITHUB_REPO); ?>;
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

        // Use filename checkbox - also triggers rescan
        const useFilename = document.getElementById('use_filename');
        if (useFilename) {
            useFilename.addEventListener('change', function() {
                ajaxPost('set_option', { use_filename: this.checked })
                    .then(() => ajaxPost('rescan_songs', {}))
                    .then(() => {
                        fader.flash('Songs will be rescanned', '#008000');
                    });
            });
        }

        // Update checker
        const checkUpdatesButton = document.getElementById('check_updates_button');
        const updateResult = document.getElementById('update_result');

        if (checkUpdatesButton && updateResult) {
            checkUpdatesButton.addEventListener('click', function() {
                checkUpdatesButton.disabled = true;
                checkUpdatesButton.value = 'Checking...';
                updateResult.style.display = 'none';

                ajaxPost('check_updates', {})
                    .then(data => {
                        checkUpdatesButton.disabled = false;
                        checkUpdatesButton.value = 'Check for Updates';
                        updateResult.style.display = 'block';

                        if (!data.status) {
                            updateResult.innerHTML = '<p style="color:#c00;">Could not check for updates. Please try again later.</p>';
                            return;
                        }

                        const info = data.update_info;

                        if (info.update_available) {
                            const releaseDate = info.published_at ? new Date(info.published_at).toLocaleDateString() : '';
                            updateResult.innerHTML = `
                                <div style="background:#ffe; border:1px solid #cc0; padding:15px; border-radius:4px;">
                                    <p><strong style="color:#060;">Update available!</strong></p>
                                    <p>New version: <strong>${escapeHtml(info.latest_version)}</strong> ${releaseDate ? '(released ' + releaseDate + ')' : ''}</p>
                                    ${info.release_notes ? '<div style="background:#fff; padding:10px; margin:10px 0; border:1px solid #ddd; max-height:150px; overflow-y:auto; font-size:13px;"><strong>Release notes:</strong><br>' + escapeHtml(info.release_notes).replace(/\\n/g, '<br>') + '</div>' : ''}
                                    <p><a href="${escapeHtml(info.release_url)}" target="_blank" style="color:#00f;">View release on GitHub</a></p>
                                    <hr style="margin:15px 0; border:none; border-top:1px solid #ddd;">
                                    <p><strong>How to update:</strong></p>
                                    <ol style="margin:10px 0; padding-left:20px; line-height:1.6;">
                                        <li><strong>Backup first:</strong> Your songs and settings are safe in the <code>songs/</code> and <code>settings/</code> folders, but it's always good to have a backup.</li>
                                        <li><a href="${escapeHtml(info.download_url)}" target="_blank">Download the new version</a> (ZIP file)</li>
                                        <li>Extract the ZIP file on your computer</li>
                                        <li>Upload all files <strong>except</strong> the <code>songs/</code> and <code>settings/</code> folders to your server, overwriting existing files</li>
                                        <li>Refresh this page to verify the update</li>
                                    </ol>
                                    <p style="font-size:12px; color:#666; margin-top:10px;"><strong>Note:</strong> Your music files and settings are stored separately and will not be affected by the update.</p>
                                </div>
                            `;
                        } else {
                            updateResult.innerHTML = `
                                <div style="background:#efe; border:1px solid #0a0; padding:15px; border-radius:4px;">
                                    <p><strong style="color:#060;">You're up to date!</strong></p>
                                    <p>You are running the latest version (${escapeHtml(info.current_version)}).</p>
                                </div>
                            `;
                        }
                    })
                    .catch(err => {
                        checkUpdatesButton.disabled = false;
                        checkUpdatesButton.value = 'Check for Updates';
                        updateResult.style.display = 'block';
                        updateResult.innerHTML = '<p style="color:#c00;">Could not check for updates. Please try again later.</p>';
                    });
            });
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    })();
    </script>
</body>
</html>
