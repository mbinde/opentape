<?php
/**
 * Opentape - Admin Edit Interface
 */

require_once("opentape_common.php");

send_security_headers();

// Require login
if (!is_logged_in()) {
    header("Location: " . $REL_PATH . "code/login.php");
    exit();
}

// Handle file upload
$upload_success = null;
$upload_error = '';

if (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
    // Validate CSRF token
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $upload_success = 0;
        $upload_error = 'Invalid request';
    } elseif ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $upload_success = 0;
        $upload_error = 'Upload error';
    } else {
        $upload_result = validate_and_save_upload($_FILES['file']);
        $upload_success = $upload_result['success'] ? 1 : ($upload_result['error'] === 'Not an MP3' ? -1 : 0);
        $upload_error = $upload_result['error'] ?? '';
    }
}

/**
 * Validate and save an uploaded MP3 file
 */
function validate_and_save_upload(array $file): array {
    $original_name = $file['name'];
    $tmp_path = $file['tmp_name'];

    // Check extension
    if (!preg_match('/\.mp3$/i', $original_name)) {
        return ['success' => false, 'error' => 'Not an MP3'];
    }

    // Check for double extensions (e.g., file.php.mp3)
    if (preg_match('/\.[^.]+\.mp3$/i', $original_name)) {
        return ['success' => false, 'error' => 'Invalid filename'];
    }

    // Validate file content - check MP3 magic bytes
    $handle = fopen($tmp_path, 'rb');
    if (!$handle) {
        return ['success' => false, 'error' => 'Cannot read file'];
    }
    $header = fread($handle, 10);
    fclose($handle);

    $is_valid_mp3 = false;

    // Check for ID3v2 tag (starts with "ID3")
    if (substr($header, 0, 3) === 'ID3') {
        $is_valid_mp3 = true;
    }
    // Check for MP3 frame sync (0xFF followed by 0xE0-0xFF for various MPEG versions/layers)
    elseif (strlen($header) >= 2) {
        $byte1 = ord($header[0]);
        $byte2 = ord($header[1]);
        if ($byte1 === 0xFF && ($byte2 & 0xE0) === 0xE0) {
            $is_valid_mp3 = true;
        }
    }

    if (!$is_valid_mp3) {
        return ['success' => false, 'error' => 'Not a valid MP3'];
    }

    // Sanitize filename - keep only safe characters
    $basename = pathinfo($original_name, PATHINFO_FILENAME);
    $basename = preg_replace('/[^a-zA-Z0-9_\-\. ]/', '', $basename);
    $basename = trim($basename);
    if (empty($basename)) {
        $basename = 'upload_' . time();
    }

    // Ensure unique filename
    $filename = $basename . '.mp3';
    $counter = 1;
    while (file_exists(SONGS_PATH . $filename)) {
        $filename = $basename . '_' . $counter . '.mp3';
        $counter++;
    }

    $dest_path = SONGS_PATH . $filename;

    if (move_uploaded_file($tmp_path, $dest_path)) {
        return ['success' => true, 'filename' => $filename];
    }

    return ['success' => false, 'error' => 'Failed to save file'];
}

$songlist_struct = scan_songs();
$songlist_struct_original = $songlist_struct;
$prefs_struct = get_opentape_prefs();

// Get CSRF token for forms
$csrf_token = get_csrf_token();

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Opentape / Edit Mixtape</title>
    <link rel="stylesheet" href="<?php echo $REL_PATH; ?>res/style.css">
</head>
<body>
    <div class="container">
        <div class="banner">
            <h1>OPENTAPE</h1>
            <ul class="nav">
                <li id="active"><a href="<?php echo $REL_PATH; ?>code/edit.php">Edit Tape</a></li>
                <li><a href="<?php echo $REL_PATH; ?>code/settings.php">Settings</a></li>
                <li id="user">
                    <a id="home" href="<?php echo $REL_PATH; ?>">YOUR TAPE &rarr;</a>
                    <a id="logout" href="<?php echo $REL_PATH; ?>code/logout.php">LOG-OUT &rarr;</a>
                </li>
            </ul>
            <div class="ajax_status"></div>
        </div>

        <div class="content">
            <div class="section">
                <h2>The Basics</h2>
                <p>HTML welcome. Leaving these blank will restore them to the defaults (Title: OPENTAPE; Caption: number of tracks, total running time of mix).</p>
                <form name="banner_form" id="banner_form">
                    <label for="banner">Title:</label>
                    <input type="text" id="banner" maxlength="255" size="55" value="<?php echo escape_for_inputs($prefs_struct['banner'] ?? ''); ?>"><br>
                    <label for="caption">Caption:</label>
                    <input type="text" id="caption" maxlength="255" size="55" value="<?php echo escape_for_inputs($prefs_struct['caption'] ?? ''); ?>"><br>
                    <label for="color_input">Color:</label>
                    <input type="text" id="color_input" value="<?php echo htmlspecialchars($prefs_struct['color'] ?? DEFAULT_COLOR, ENT_QUOTES, 'UTF-8'); ?>" style="background:#<?php echo htmlspecialchars($prefs_struct['color'] ?? DEFAULT_COLOR, ENT_QUOTES, 'UTF-8'); ?>;" size="7" maxlength="7">
                    <input type="button" id="color_reset_button" value="Reset">
                    <a style="vertical-align:top;" href="https://www.w3schools.com/colors/colors_picker.asp" target="_blank">(color picker)</a><br>
                    <input id="banner_button" type="button" class="save" value="Save">
                </form>
            </div>

            <div class="section">
                <h2>Upload Songs</h2>
                <p>Choose any <strong>MP3</strong> no larger than <?php echo get_max_upload_mb(); ?> MB (this is the upload_max_filesize set by your web host).</p>
                <p>For larger files, place them into the <span style="color:#00f">songs/</span> folder via FTP.</p>
                <form name="upload" id="upload_form" enctype="multipart/form-data" action="<?php echo get_base_url(); ?>code/edit.php" method="post">
                    <?php echo csrf_field(); ?>
                    <input id="upload_input" name="file" type="file" accept=".mp3,audio/mpeg">
                    <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo get_max_upload_bytes(); ?>"><br>
                    <input type="submit" class="button" id="upload_button" value="Upload">
                </form>
            </div>

            <div class="section">
                <h2>Rearrange Songs</h2>
                <p><strong>Drag &amp; drop</strong> to change the order of your mixtape, it will save automatically.</p>
                <p>
                    <label>
                        <input type="checkbox" id="use_filename" <?php if (!empty($prefs_struct['use_filename']) && $prefs_struct['use_filename'] == 1) echo 'checked'; ?>>
                        Use filenames instead of ID3 tags
                    </label>
                </p>
                <ul class="sortie" id="sortable_list">
<?php foreach ($songlist_struct as $pos => $row):
    if (!is_file(SONGS_PATH . $row['filename'])) {
        unset($songlist_struct[$pos]);
        continue;
    }

    $display_artist = '';
    if (!empty($row['opentape_artist'])) {
        $display_artist = htmlspecialchars($row['opentape_artist'], ENT_QUOTES, 'UTF-8');
    } elseif (!empty($row['artist'])) {
        $display_artist = htmlspecialchars($row['artist'], ENT_QUOTES, 'UTF-8');
    }

    $display_title = !empty($row['opentape_title'])
        ? htmlspecialchars($row['opentape_title'], ENT_QUOTES, 'UTF-8')
        : htmlspecialchars($row['title'] ?? '', ENT_QUOTES, 'UTF-8');

    $filename_display = htmlspecialchars($row['filename'], ENT_QUOTES, 'UTF-8');
?>
                    <li id="<?php echo htmlspecialchars($pos, ENT_QUOTES, 'UTF-8'); ?>" draggable="true">
                        <div class="name">
                            <span class="original_artist"><?php echo $display_artist; ?></span> - <span class="original_title"><?php echo $display_title; ?></span>
                            <span class="original_filename">(<?php echo $filename_display; ?>)</span>
                        </div>
                        <div class="inputs">
                            <input type="text" class="artist field" value="<?php echo $display_artist; ?>"> -
                            <input type="text" class="title field" value="<?php echo $display_title; ?>">
                            <input type="button" class="save button" value="Save">
                            <input type="button" class="cancel button" value="Cancel">
                        </div>
                        <div class="abc">rename</div>
                        <div class="ex">delete</div>
                    </li>
<?php endforeach; ?>
                </ul>
            </div>

            <div class="footer">
                <?php get_version_banner(); ?>
            </div>
        </div>
    </div>

    <script src="<?php echo $REL_PATH; ?>res/statusfader.js"></script>
    <script src="<?php echo $REL_PATH; ?>res/upload.js"></script>
    <script>
    (function() {
        'use strict';

        const CSRF_TOKEN = <?php echo json_encode($csrf_token); ?>;
        const AJAX_URL = <?php echo json_encode($REL_PATH . 'code/ajax.php'); ?>;
        const DEFAULT_COLOR = <?php echo json_encode(DEFAULT_COLOR); ?>;

        const fader = new StatusFader();
        let originalOrder = [];

        // AJAX helper
        function ajaxPost(command, args, extraData) {
            fader.set('progress');
            document.body.style.cursor = 'wait';

            const formData = new FormData();
            formData.append('command', command);
            formData.append('args', JSON.stringify(args));
            formData.append('csrf_token', CSRF_TOKEN);

            if (extraData) {
                for (const key in extraData) {
                    formData.append(key, extraData[key]);
                }
            }

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

        // Banner/Caption/Color save
        const bannerButton = document.getElementById('banner_button');
        if (bannerButton) {
            bannerButton.addEventListener('click', function() {
                const banner = document.getElementById('banner').value;
                const caption = document.getElementById('caption').value;
                // Strip # if present
                const color = document.getElementById('color_input').value.replace(/^#/, '');

                ajaxPost('bannercaptioncolor', 'none', { banner, caption, color })
                    .then(() => {
                        document.getElementById('color_input').value = color;
                        document.getElementById('color_input').style.background = '#' + color;
                    });
            });
        }

        // Color reset
        const colorResetButton = document.getElementById('color_reset_button');
        if (colorResetButton) {
            colorResetButton.addEventListener('click', function() {
                document.getElementById('color_input').value = DEFAULT_COLOR;
                document.getElementById('color_input').style.background = '#' + DEFAULT_COLOR;
            });
        }

        // Rescan songs
        // Use filename toggle - save setting and rescan
        const useFilename = document.getElementById('use_filename');
        if (useFilename) {
            useFilename.addEventListener('change', function() {
                useFilename.disabled = true;
                ajaxPost('set_option', { use_filename: this.checked })
                    .then(() => ajaxPost('rescan_songs', {}))
                    .then(() => {
                        location.reload();
                    })
                    .catch(() => {
                        useFilename.disabled = false;
                    });
            });
        }

        // Song list functionality
        const sortableList = document.getElementById('sortable_list');
        if (sortableList) {
            // Get initial order
            originalOrder = Array.from(sortableList.querySelectorAll('li')).map(li => li.id);

            // Drag and drop
            let draggedItem = null;

            sortableList.addEventListener('dragstart', function(e) {
                if (e.target.tagName === 'LI') {
                    draggedItem = e.target;
                    e.target.style.opacity = '0.5';
                }
            });

            sortableList.addEventListener('dragend', function(e) {
                if (e.target.tagName === 'LI') {
                    e.target.style.opacity = '1';
                    draggedItem = null;

                    // Check if order changed
                    const newOrder = Array.from(sortableList.querySelectorAll('li')).map(li => li.id);
                    if (!arraysEqual(newOrder, originalOrder)) {
                        ajaxPost('reorder', newOrder).then(data => {
                            if (data.status) {
                                originalOrder = newOrder;
                            } else {
                                location.reload();
                            }
                        });
                    }
                }
            });

            sortableList.addEventListener('dragover', function(e) {
                e.preventDefault();
                const target = e.target.closest('li');
                if (target && target !== draggedItem) {
                    const rect = target.getBoundingClientRect();
                    const midY = rect.top + rect.height / 2;
                    if (e.clientY < midY) {
                        sortableList.insertBefore(draggedItem, target);
                    } else {
                        sortableList.insertBefore(draggedItem, target.nextSibling);
                    }
                }
            });

            // Song item events
            sortableList.querySelectorAll('li').forEach(function(li) {
                // Hover effects
                li.addEventListener('mouseenter', function() {
                    this.classList.add('hover');
                });
                li.addEventListener('mouseleave', function() {
                    if (!isRenaming(this)) {
                        this.classList.remove('hover');
                    }
                });

                // Rename button
                const renameBtn = li.querySelector('.abc');
                if (renameBtn) {
                    renameBtn.addEventListener('click', function() {
                        if (isRenaming(li)) {
                            closeRename(li);
                        } else {
                            openRename(li);
                        }
                    });
                }

                // Delete button
                const deleteBtn = li.querySelector('.ex');
                if (deleteBtn) {
                    deleteBtn.addEventListener('click', function() {
                        const name = li.querySelector('.name').textContent.trim();
                        if (confirm('Are you sure you want to delete "' + name + '"?')) {
                            ajaxPost('delete', li.id).then(data => {
                                if (data.status) {
                                    li.remove();
                                }
                            });
                        }
                    });
                }

                // Save rename
                const saveBtn = li.querySelector('input.save');
                if (saveBtn) {
                    saveBtn.addEventListener('click', function() {
                        const artist = li.querySelector('input.artist').value;
                        const title = li.querySelector('input.title').value;
                        const origArtist = li.querySelector('.original_artist').textContent;
                        const origTitle = li.querySelector('.original_title').textContent;

                        if (artist !== origArtist || title !== origTitle) {
                            ajaxPost('rename', { song_key: li.id }, { artist, title })
                                .then(data => {
                                    if (data.status && data.args) {
                                        li.querySelector('.original_artist').textContent = data.args.artist || 'Unknown';
                                        li.querySelector('.original_title').textContent = data.args.title || 'Untitled';
                                        li.querySelector('input.artist').value = data.args.artist || 'Unknown';
                                        li.querySelector('input.title').value = data.args.title || 'Untitled';
                                        closeRename(li);
                                    }
                                });
                        } else {
                            closeRename(li);
                        }
                    });
                }

                // Cancel rename
                const cancelBtn = li.querySelector('input.cancel');
                if (cancelBtn) {
                    cancelBtn.addEventListener('click', function() {
                        li.querySelector('input.artist').value = li.querySelector('.original_artist').textContent;
                        li.querySelector('input.title').value = li.querySelector('.original_title').textContent;
                        closeRename(li);
                    });
                }
            });
        }

        function isRenaming(li) {
            const inputs = li.querySelector('.inputs');
            return inputs && inputs.style.display !== 'none';
        }

        function openRename(li) {
            li.classList.add('hover');
            li.querySelector('.name').style.display = 'none';
            li.querySelector('.inputs').style.display = 'block';
        }

        function closeRename(li) {
            li.querySelector('.inputs').style.display = 'none';
            li.querySelector('.name').style.display = 'block';
            li.classList.remove('hover');
        }

        function arraysEqual(a, b) {
            if (a.length !== b.length) return false;
            for (let i = 0; i < a.length; i++) {
                if (a[i] !== b[i]) return false;
            }
            return true;
        }

        // Handle upload status messages
<?php if ($upload_success === 1): ?>
        fader.stay('Upload OK!', '#008000');
<?php elseif ($upload_success === 0): ?>
        fader.stay(<?php echo json_encode($upload_error ?: 'Upload failed.'); ?>, '#ff0000');
<?php elseif ($upload_success === -1): ?>
        fader.stay('MP3s only!', '#ff0000');
<?php endif; ?>

    })();
    </script>
</body>
</html>
