<?php
/**
 * Opentape - Main Playlist View
 */

require_once("opentape_common.php");

send_security_headers();
init_session();

$songlist_struct = scan_songs();
$songlist_struct_original = $songlist_struct;

$prefs_struct = get_opentape_prefs();

// Set page variables with fallbacks
$page_title = !empty($prefs_struct['banner'])
    ? strip_tags($prefs_struct['banner'])
    : "Opentape / " . count($songlist_struct) . " songs, " . get_total_runtime_string();

$header_bg_color = !empty($prefs_struct['color'])
    ? htmlspecialchars($prefs_struct['color'], ENT_QUOTES, 'UTF-8')
    : DEFAULT_COLOR;

$banner_header_text = !empty($prefs_struct['banner'])
    ? htmlspecialchars($prefs_struct['banner'], ENT_QUOTES, 'UTF-8')
    : "OPENTAPE";

$banner_caption_text = !empty($prefs_struct['caption'])
    ? htmlspecialchars($prefs_struct['caption'], ENT_QUOTES, 'UTF-8')
    : count($songlist_struct) . " songs, " . get_total_runtime_string();

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="<?php echo $REL_PATH; ?>res/tape.css">
    <link rel="alternate" type="application/rss+xml" href="<?php echo $REL_PATH; ?>code/rss.php">
    <style>
        div.banner { background: #<?php echo $header_bg_color; ?>; }
    </style>
</head>
<body>
    <div class="container">
        <div class="banner">
            <div class="flag">
                <h1><?php echo $banner_header_text; ?></h1>
                <h2><?php echo $banner_caption_text; ?></h2>
            </div>
        </div>

        <ul class="songs">
<?php
$i = 0;
foreach ($songlist_struct as $pos => $row) {
    if (!is_file(SONGS_PATH . $row['filename'])) {
        unset($songlist_struct[$pos]);
        continue;
    }

    $display_artist = '';
    if (!empty($row['opentape_artist'])) {
        $display_artist = htmlspecialchars($row['opentape_artist'], ENT_QUOTES, 'UTF-8') . " - ";
    } elseif (!empty($row['artist'])) {
        $display_artist = htmlspecialchars($row['artist'], ENT_QUOTES, 'UTF-8') . " - ";
    }

    $display_title = !empty($row['opentape_title'])
        ? htmlspecialchars($row['opentape_title'], ENT_QUOTES, 'UTF-8')
        : htmlspecialchars($row['title'] ?? '', ENT_QUOTES, 'UTF-8');

    $playtime = htmlspecialchars($row['playtime_string'] ?? '0:00', ENT_QUOTES, 'UTF-8');
?>
            <li class="song" id="song<?php echo $i; ?>">
                <div class="name"><?php echo $display_artist . $display_title; ?></div>
<?php if (!empty($prefs_struct['display_mp3']) && $prefs_struct['display_mp3'] == 1): ?>
                <a class="mp3" href="<?php echo $REL_PATH . SONGS_PATH . rawurlencode($row['filename']); ?>" target="_blank">MP3</a>
<?php else: ?>
                &nbsp;
<?php endif; ?>
                <div class="info">
                    <div class="clock"></div> <strong><?php echo $playtime; ?></strong>
                </div>
            </li>
<?php
    $i++;
}

// Save if any songs were removed
if ($songlist_struct !== $songlist_struct_original) {
    write_songlist_struct($songlist_struct);
}
?>
        </ul>

        <div class="footer">
            <?php get_version_banner(); ?> &infin; <a href="<?php echo $REL_PATH; ?>code/edit.php">Admin</a>
        </div>
    </div>

    <script src="<?php echo $REL_PATH; ?>res/player.js"></script>
    <script>
        var openPlaylist = [<?php
            $list_items = [];
            foreach ($songlist_struct as $pos => $row) {
                $list_items[] = "'" . $pos . "'";
            }
            echo implode(',', $list_items);
        ?>];
        var pageTitle = <?php echo json_encode($page_title); ?>;

        // Initialize player events
        if (typeof event_init === 'function') {
            event_init();
        }
    </script>
</body>
</html>
