# opentape-8: Add Content-Security-Policy header

**Type:** task
**Priority:** 1 (high)
**Status:** closed
**Created:** 2026-01-19

## Description

The `send_security_headers()` function in `code/opentape_common.php` (lines 567-571) sets some security headers but is missing Content-Security-Policy.

CSP helps prevent XSS attacks by restricting where scripts and resources can be loaded from.

## Fix

Add a CSP header that:
- Restricts script sources to 'self'
- Restricts style sources to 'self' and 'unsafe-inline' (if needed for existing styles)
- Restricts media sources to 'self' (for MP3 playback)
- Sets default-src to 'self'
