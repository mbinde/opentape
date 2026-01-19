# opentape-2: Fix XSS vulnerabilities in HTML output

**Type:** bug
**Priority:** 0 (critical)
**Status:** closed
**Created:** 2026-01-19

## Description

Banner and caption data are output directly to HTML without escaping in `code/mixtape.php`.

**Affected lines:**
- Lines 25-26: Banner text used in `<h1>` tag without escaping
- Lines 30-31: Caption text used in `<h2>` tag without escaping

## Fix

Use `htmlspecialchars($text, ENT_QUOTES, 'UTF-8')` for all user-controlled HTML output.
