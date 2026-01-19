# opentape-11: Add input validation for song keys

**Type:** bug
**Priority:** 2 (medium)
**Status:** closed
**Created:** 2026-01-19

## Description

Song keys in `code/ajax.php` (lines 138, 182) are base64-encoded filenames but validation doesn't ensure they map to actual existing files before operations.

## Fix

Validate that song keys:
1. Are valid base64
2. Decode to a valid filename
3. Map to an existing file in the songs directory
4. Don't contain path traversal attempts
