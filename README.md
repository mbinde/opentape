# Opentape

A simple, self-hosted web application for creating and sharing mixtapes. Modernized for PHP 8.x with HTML5 audio and vanilla JavaScript (no Flash or jQuery required).

## Requirements

- PHP 8.0 or higher (tested on PHP 8.5)
- Web server (Apache, nginx, etc.)
- Writable `userdata/` directory

## Installation

1. Upload all files and folders to a directory on your web server
2. Ensure the `userdata/` directory (and its subdirectories) are writable by the web server
3. Visit your installation URL (e.g., `https://yoursite.com/opentape/`)
4. Create an admin password when prompted
5. Start uploading MP3s!

## Updating

1. Go to **Settings** in your admin panel and click **Check for Updates**
2. If an update is available, download the ZIP file
3. Upload all files **except the `userdata/` folder** to your server, overwriting existing files
4. Your music and settings in `userdata/` will be preserved

## Migrating from Old Opentape (pre-1.1)

If you have an existing Opentape installation with `songs/` and `settings/` folders in the root:

1. Create the new `userdata/songs/` and `userdata/settings/` directories
2. Move your MP3 files from `songs/` to `userdata/songs/`
3. Move your `.json` files from `settings/` to `userdata/settings/`
4. Delete the old `songs/` and `settings/` folders
5. Upload the new Opentape files

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
└── userdata/           # User data (preserve during updates!)
    ├── songs/          # Uploaded MP3 files
    └── settings/       # Configuration and password
```

## Security Notes

- Uses PHP's native `password_hash()` for secure password storage
- CSRF protection on all admin actions
- Content-Security-Policy headers
- File upload validation (magic bytes, not just extension)
- Session management with secure cookie settings
- Settings directory protected from direct web access

## Changelog

### 1.1.0

- Moved user data to `userdata/` folder for safer updates
- Added update checker in Settings
- Security hardening (XSS fixes, CSP headers, upload validation)
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
