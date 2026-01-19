# opentape-6: Add CSRF validation to file uploads

**Type:** bug
**Priority:** 1 (high)
**Status:** closed
**Resolution:** Fixed as part of opentape-3 file upload hardening.
**Created:** 2026-01-19

## Description

The file upload form in `code/edit.php` includes a CSRF token field (line 87), but the upload handler doesn't validate it. Only `is_logged_in()` is checked.

## Fix

Add CSRF token validation in the file upload handler before processing the upload.
