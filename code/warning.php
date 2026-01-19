<?php
require_once("opentape_common.php");

$setup_status = get_setup_status();
$php_version_ok = version_compare(PHP_VERSION, '8.0.0', '>=');
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Setup Required / Opentape</title>
    <link rel="stylesheet" href="<?php echo $REL_PATH; ?>res/style.css">
</head>
<body>
    <div class="container">
        <div class="banner">
            <h1>OPENTAPE</h1>
            <div class="ajax_status"></div>
        </div>

        <div class="content">
            <div class="section" style="background:#fee; border:1px solid #c00; padding:15px;">
                <h2 style="color:#c00;">Setup Required</h2>

<?php if (!$php_version_ok): ?>
                <p>Opentape requires <strong>PHP 8.0 or higher</strong>. You are running PHP <?php echo htmlspecialchars(PHP_VERSION); ?>.</p>
                <p>Please contact your web host to upgrade PHP.</p>

<?php elseif (!$setup_status['settings_writable'] || !$setup_status['songs_writable']): ?>
                <p>Opentape needs to create some directories to store your music and settings, but doesn't have permission.</p>

                <p><strong>What needs to be fixed:</strong></p>
                <ul style="margin:10px 0; padding-left:20px;">
<?php if (!$setup_status['settings_dir']): ?>
                    <li>Create the <code>settings/</code> directory</li>
<?php elseif (!$setup_status['settings_writable']): ?>
                    <li>Make <code>settings/</code> writable (chmod 755 or 775)</li>
<?php endif; ?>
<?php if (!$setup_status['songs_dir']): ?>
                    <li>Create the <code>songs/</code> directory</li>
<?php elseif (!$setup_status['songs_writable']): ?>
                    <li>Make <code>songs/</code> writable (chmod 755 or 775)</li>
<?php endif; ?>
                </ul>

                <p><strong>How to fix (using your hosting control panel):</strong></p>
                <ol style="margin:10px 0; padding-left:20px; line-height:1.6;">
                    <li>Open your hosting control panel (cPanel, Plesk, etc.)</li>
                    <li>Go to <strong>File Manager</strong></li>
                    <li>Navigate to your Opentape folder</li>
                    <li>Create the <code>settings</code> and <code>songs</code> folders if they don't exist</li>
                    <li>Right-click on each and select <strong>Permissions</strong></li>
                    <li>Set permissions to <strong>755</strong> (or 775 if 755 doesn't work)</li>
                </ol>

                <p><a href="<?php echo $REL_PATH; ?>">Refresh this page</a> after making changes.</p>
<?php endif; ?>
            </div>

            <div class="footer">
                <?php get_version_banner(); ?>
            </div>
        </div>
    </div>
</body>
</html>
