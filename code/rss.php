<?php

require_once("opentape_common.php");
$songlist_struct = scan_songs();
$songlist_struct_original = $songlist_struct;

$prefs_struct = get_opentape_prefs();

/**
 * Escape text for XML output
 */
function xml_escape(string $text): string {
    // Remove invalid XML characters (control chars except tab, newline, carriage return)
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
    return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

header("Content-type: application/rss+xml; charset=UTF-8");

echo '<?xml version="1.0" encoding="UTF-8" ?>' . "\n";
echo '<rss version="2.0">' . "\n";
echo '<channel>' . "\n";

echo '<title>';
if (isset($prefs_struct['banner'])) {
    echo xml_escape($prefs_struct['banner']);
} else {
    echo 'Opentape!';
}
echo '</title>' . "\n";

echo '<description>';
if (isset($prefs_struct['caption'])) {
    echo xml_escape($prefs_struct['caption']);
} else {
    echo count($songlist_struct) . " songs, " . get_total_runtime_string();
}
echo '</description>' . "\n";

echo '<link>' . xml_escape(get_base_url()) . '</link>' . "\n";

foreach ($songlist_struct as $pos => $row) {

    if (!is_file(constant("SONGS_PATH") . $row['filename'])) {
        unset($songlist_struct[$pos]);
        continue;
    }

    echo '<item>' . "\n";

    echo '<title>';
    $artist = $row['opentape_artist'] ?? $row['artist'] ?? '';
    $title = $row['opentape_title'] ?? $row['title'] ?? '';
    echo xml_escape($artist . ' - ' . $title);
    echo '</title>' . "\n";

    $song_url = get_base_url() . constant("SONGS_PATH") . rawurlencode($row['filename']);
    echo '<link>' . xml_escape($song_url) . '</link>' . "\n";
    echo '<enclosure url="' . xml_escape($song_url) . '" length="' . (int)$row['size'] . '" type="audio/mpeg"></enclosure>' . "\n";
    echo '<guid isPermaLink="false">' . xml_escape($pos) . '</guid>' . "\n";

    echo '<description>' . xml_escape($row['playtime_string'] ?? '') . '</description>' . "\n";

    echo '</item>' . "\n";

}

echo '</channel>' . "\n";
echo '</rss>' . "\n";
