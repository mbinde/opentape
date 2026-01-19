# opentape-3: Harden file upload validation

**Type:** bug
**Priority:** 0 (critical)
**Status:** closed
**Created:** 2026-01-19

## Description

The file upload validation in `code/edit.php` (lines 19-27) only checks file extension with a simple regex. This is insufficient:

- No MIME type validation
- No file content/magic bytes inspection
- Allows potentially dangerous filenames
- Could allow double extension bypasses on misconfigured servers

## Fix

1. Validate MIME type matches audio/mpeg
2. Check file magic bytes for MP3 signature (ID3 or 0xFF 0xFB)
3. Sanitize filename to alphanumeric + basic punctuation only
4. Reject files with multiple extensions
