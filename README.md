# Opentape

A simple, self-hosted web application for creating and sharing mixtapes. Modernized for PHP 8.x with HTML5 audio and vanilla JavaScript (no Flash or jQuery required).

## Requirements

- PHP 8.0 or higher (tested on PHP 8.5)
- Web server (Apache, nginx, etc.)
- Writable `songs/` and `settings/` directories (auto-created on first run)

## Installation

1. Upload all files and folders to a directory on your web server
2. Visit your installation URL (e.g., `https://yoursite.com/opentape/`)
3. The `songs/` and `settings/` directories will be created automatically
4. Create an admin password when prompted
5. Start uploading MP3s!

If automatic directory creation fails, create `songs/` and `settings/` manually and set permissions to 755 or 775.

## Updating

1. Go to **Settings** in your admin panel and click **Check for Updates**
2. If an update is available, download the ZIP file
3. Upload all files **except `songs/` and `settings/`** to your server, overwriting existing files
4. Your music and settings will be preserved

## Migrating from Old Opentape (pre-1.0)

Old Opentape installations should work with minimal changes:

1. Upload the new code files (index.php, code/, res/)
2. Keep your existing `songs/` and `settings/` folders
3. You will need to set a new password (old MD5 passwords are not supported)

## Features

- Upload and organize MP3 files into playlists
- Automatic ID3 tag reading for artist/title info
- Drag-and-drop reordering
- Customizable banner, caption, and color
- Crossfade between tracks
- RSS feed for your mixtape
- Built-in update checker
- No database required - all data stored in JSON files

## Directory Structure

```
opentape/
├── index.php           # Entry point
├── code/               # PHP application files
│   ├── getid3/         # ID3 tag parsing library
│   └── *.php           # Application logic
├── res/                # CSS and JavaScript
├── songs/              # Uploaded MP3 files (preserve during updates!)
└── settings/           # Configuration and password (preserve during updates!)
```

## Security Notes

- Uses PHP's native `password_hash()` for secure password storage
- CSRF protection on all admin actions
- Content-Security-Policy headers
- File upload validation (magic bytes, not just extension)
- Session management with secure cookie settings
- Settings directory protected from direct web access

## Changelog

### 1.0.1

- Added update checker in Settings
- Security hardening (XSS fixes, CSP headers, upload validation)
- Improved error messages for setup/permissions issues
- Auto-create `songs/` and `settings/` directories on first run
- Removed legacy MD5 password support
- Removed old serialized PHP migration code

### 1.0.0

Complete modernization for PHP 8.x and modern browsers:

- Replaced MooTools with vanilla JavaScript
- Replaced SoundManager 2 with native HTML5 Audio
- Updated to latest getID3 library
- Switched from serialized PHP to JSON file storage
- Added CSRF protection
- Upgraded password hashing from MD5 to bcrypt
- Native PHP session management
- HTML5 doctype and modern markup
- Removed dead opentape.fm integration

### 0.13

- Removed Flash support
- Updates to PHP ID3 libs
- Minor fixes for PHP 7/8

## License

Opentape is licensed under the GNU Affero GPL v3. See the LICENSE file for details.

### Third-party code

- getID3 - GNU GPL: https://github.com/JamesHeinrich/getID3
