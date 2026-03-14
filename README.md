# Comboni Library Management System v4

## Quick Setup
1. Place `comboni-v4/` in `C:\xampp\htdocs\`
2. Import `schema.sql` via phpMyAdmin (`http://localhost/phpmyadmin`)
3. Start Apache + MySQL in XAMPP
4. Visit `http://localhost/comboni-v4/`  ← always use this URL, never open HTML directly
5. Default admin password: `Comboni-library5634`  ← change this in Settings

## Required Assets (download and place manually)
| File | URL | Destination |
|------|-----|-------------|
| `material-icons.woff2` | https://github.com/google/material-design-icons/releases | `assets/fonts/` |
| `NotoSansEthiopic-*.woff2` | https://fonts.google.com/specimen/Noto+Sans+Ethiopic | `assets/fonts/` |
| `chart.umd.min.js` | https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js | `assets/js/` |

Without Material Icons: navigation icons won't render (text fallback only)
Without Noto Sans Ethiopic: Amharic text uses system fallback font
Without Chart.js: Reports page shows an error banner instead of charts

## Pages
| Page | URL |
|------|-----|
| Login | login.html |
| Home (New Borrow) | index.html |
| Teachers' Log | teachers.html |
| Students' Log | students.html |
| Books Inventory | books.html |
| Reports | reports.html |
| Archive | archive.html |
| Export / Import | export-import.html |
| Tutorial | tutorial.html |
| Settings | settings.html |
| Developer | developer.html |

## Cron (Auto-Archive)
Linux/macOS: `0 1 * * * php /path/to/comboni-v4/cron/auto_archive.php`
Windows: `schtasks /create /tn "LibraryArchive" /tr "php C:\xampp\htdocs\comboni-v4\cron\auto_archive.php" /sc daily /st 01:00`
