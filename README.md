# Portfolio — photo gallery extension for phpBB

[![Checks](https://github.com/verturin/portfolio/actions/workflows/ci.yml/badge.svg)](https://github.com/verturin/portfolio/actions/workflows/ci.yml)
[![Latest release](https://img.shields.io/github/v/release/verturin/portfolio?label=version)](https://github.com/verturin/portfolio/releases/latest)
![phpBB](https://img.shields.io/badge/phpBB-3.3.15%2B%20%C2%B7%204.x-blue)
![PHP](https://img.shields.io/badge/PHP-7.2%20%E2%80%93%208.4-777bb4)
[![License GPL-2.0](https://img.shields.io/badge/license-GPL--2.0-green)](license.txt)

🇫🇷 [Version française](README.fr.md) · 📜 [Changelog](CHANGELOG.md)

A modern photo gallery for phpBB: personal and public albums with sub-albums, albums managed by several members, ratings, reactions, comments, a traceable watermark, a recycle bin, per-group quotas and approval, phpBB notifications, BBCode to show photos in posts, and a one-click migration from **phpBB Gallery**. Every feature is controlled by phpBB permissions.

| | |
|---|---|
| **Extension name** | `verturin/portfolio` |
| **Version** | 1.10.8 |
| **phpBB** | 3.3.15 or later, ready for phpBB 4 |
| **PHP** | 7.2 to 8.4 · GD extension required (EXIF recommended, ZIP for exports and backups) |
| **Databases tested** | MySQL / MariaDB (strict mode included), SQLite |
| **Languages** | English, French |
| **License** | [GPL-2.0-only](license.txt) |
| **Author** | [verturin](https://github.com/verturin) |

![Home page](docs/screenshots/en/01-home.png)

## Features

### Albums
- A **personal portfolio for every member**, plus **public albums** created by administrators (contests, introductions, events…).
- **Sub-albums** on several levels (maximum depth set in the ACP), cover photo, default sort order (date, title, rating, views), lock.
- **Visibility** per album: everyone, registered users, selected groups, the owner's **phpBB friends**, or private. A sub-album is never more open than its parent.
- A clear message when access is refused, with a button to ask the owner for access.
- An album can only be deleted once it is empty, so nothing is lost by mistake.

| Member portfolio | Album |
|---|---|
| ![Member portfolio](docs/screenshots/en/02-member-portfolio.png) | ![Album](docs/screenshots/en/03-album.png) |

### Photos
- **Multiple upload by drag and drop**, progress bar, title editable before upload.
- **EXIF, GPS, IPTC and XMP data removed**: photos are auto-rotated, then re-encoded without metadata.
- Real file type check, oversized originals reduced automatically, square thumbnails, display size and original.
- Images are **never served directly**: every file goes through a controller that checks permissions.
- One-click **rotation**, **move** to another album (one by one or in bulk), original **download** that can be disabled per album.
- **Full-screen viewer** (keyboard and swipe) and **quick view** with ratings, reactions and comments without leaving the page.
- Navigation: first / previous / next / last photo, film strip, album tree, previous / next album.

| Photo page | Quick view |
|---|---|
| ![Photo](docs/screenshots/en/04-photo.png) | ![Quick view](docs/screenshots/en/05-quick-view.png) |

| Upload | Album settings |
|---|---|
| ![Upload](docs/screenshots/en/06-upload.png) | ![Album settings](docs/screenshots/en/07-album-settings.png) |

### Ratings, reactions, comments and notifications
- **1–5 star rating**, **emoji reactions** (👍 ❤️ 😀 😲 😢 👏…, list chosen in the ACP), comments with BBCode and smilies.
- **phpBB notifications** when another member rates, comments or reacts to your photo, with a direct link. One notification per action, or grouped every 1 to 60 minutes. Members can switch them off in their preferences.

![Notifications](docs/screenshots/en/10-notifications.png)

### Albums managed together
- Share an album (sub-albums included) with other members as **guest viewer**, **contributor** (uploads and manages their own photos) or **co-manager** (manages the album like the owner).
- Sub-albums inherit the sharing of their parent. The *Sharing* page lists and edits every share.

![Sharing](docs/screenshots/en/08-sharing.png)

### Show photos in forum posts
- On each photo, a block gives three ready-to-copy BBCodes (`[portfolio=mini]`, `[portfolio=medium]`, `[portfolio=large]`) and the page address.
- A **portfolio** button is added to the post editor. Album permissions and the watermark still apply in posts; readers without access see a neutral “Restricted photo” image.

| Insert block | Result in a post |
|---|---|
| ![Embed codes](docs/screenshots/en/09a-embed-codes.png) | ![Photo in a post](docs/screenshots/en/09b-photo-in-post.png) |

### Traceable watermark
- Text and/or logo, position, size, opacity, outline, **tile mode** with adjustable density.
- The watermark is built **for each viewer** (site name, viewer ID, date…), so a leaked copy can be traced. Originals are never modified.
- Albums can be shown without watermark (with their sub-albums). Selected members or groups may remove the watermark from **their own photos**, album by album, after a security warning.

![Watermark](docs/screenshots/en/13-watermark.png)

### Recycle bin and safety
- Deleting a photo moves it to the **recycle bin** with its **reason** (required or optional, ACP setting). Only an administrator deletes permanently.
- Members can delete their own photos only if they have the permission (off by default).
- Mass-deletion alert, and optional blocking above a threshold.

![Recycle bin](docs/screenshots/en/11-recycle-bin.png)

### Profiles and posts
- Photo count and latest photos in the **member profile**, link to the portfolio in profiles and next to posts.

![Profile](docs/screenshots/en/12-profile.png)

### Portfolio pages in the forum
- Create pages (latest photos, top rated, latest comments, random photos, all members' albums, or one album) and publish them in the forum index as a forum link, with phpBB forum permissions deciding who sees them. A page on one album is ideal for an “Introduce yourself in a photo” or contest board.

### Members who leave
- When an account is deleted, its photos are not lost: they are kept under the member's name, or moved to a chosen member.

## Administration

| Settings | Albums & permissions |
|---|---|
| ![ACP settings](docs/screenshots/en/14-acp-settings.png) | ![ACP albums](docs/screenshots/en/15-acp-albums.png) |

- **Settings**: display, member album options, image sizes, limits, safety, social features, export.
- **Albums & permissions**: every album of the board, sortable by album, owner or number of photos; search a member's albums with phpBB's member finder; per-group rights for each album; **bulk changes** (visibility, download, watermark, lock, protection), sub-albums included.
- **Watermark**: live preview, albums without watermark, members allowed to remove it (with a one-time private message explaining the rules).
- **Quotas & approval**: photo and album quotas **per group**, **mandatory approval** per group (even in the member's own albums), and a private message to administrators, moderators, groups or chosen members when a photo awaits approval.
- **Notifications**, **Pages in the forum**, **Departed members**, **Recycle bin**.
- **Backup**: full ZIP backup and restore (albums, photos, settings, ratings, comments). Members can also export their own albums as a ZIP.

| Watermark | Quotas & approval |
|---|---|
| ![ACP watermark](docs/screenshots/en/16-acp-watermark.png) | ![ACP quotas](docs/screenshots/en/17-acp-quotas-approval.png) |

| Notifications | Backup |
|---|---|
| ![ACP notifications](docs/screenshots/en/18-acp-notifications.png) | ![ACP backup](docs/screenshots/en/20-acp-backup.png) |

### Migration from phpBB Gallery
*ACP › Portfolio › phpBB Gallery switch* copies albums, sub-albums, photos, titles, descriptions, views, **ratings** and **comments**, in batches limited in time, with a diagnosis of any failure. Old links (`/gallery/image/…`, `/gallery/album/…`) and images in existing posts keep working through redirects. phpBB Gallery data is left untouched.

![phpBB Gallery import](docs/screenshots/en/19-acp-phpbb-gallery-import.png)

## Permissions

All rights are set **per group** (or per user) in *ACP › Permissions*, category **Portfolio**.

![Permissions](docs/screenshots/en/21-acp-permissions.png)

| Permission | Allows |
|---|---|
| `u_portfolio_view` | View the portfolio |
| `u_portfolio_create_album` | Create own albums and sub-albums |
| `u_portfolio_upload` | Upload photos |
| `u_portfolio_delete` | Delete own photos and albums (to the recycle bin) — *No* by default |
| `u_portfolio_noapprove` | Post photos without approval |
| `u_portfolio_rate` | Rate photos |
| `u_portfolio_comment` | Comment on photos |
| `u_portfolio_react` | React to photos |
| `u_portfolio_share` | Share albums with co-managers |
| `u_portfolio_restore` | Restore own photos from the recycle bin |
| `u_portfolio_download` | Download original photos |
| `u_portfolio_unlimited` | Not subject to quotas |
| `u_portfolio_export` | Export own albums as ZIP |
| `u_portfolio_profile_link` | See the gallery link in profiles and posts |
| `u_portfolio_vis_groups` | Restrict an album to selected groups |
| `m_portfolio_approve` / `_edit` / `_delete` / `_comments` | Moderation |
| `a_portfolio_manage` | Settings, public albums, per-album rights |
| `a_portfolio_trash` | Permanent deletion and restore |

## Installation

1. Download the latest release and copy it to `ext/verturin/portfolio/` (you must get `ext/verturin/portfolio/composer.json`).
2. *ACP › Customise › Manage extensions* › **Portfolio** › **Enable**.
3. Make sure `files/portfolio/` is writable (the ACP shows a warning otherwise).
4. Set the group permissions, then the settings in *ACP › Extensions › Portfolio*.

**Update**: disable the extension, replace the files, enable it again (migrations run automatically).
**Uninstall**: disable the extension, then *Delete data*. Tables, settings, permissions **and photo files** are removed.

## Technical notes

- Own database tables (`phpbb_portfolio_*`) — no core table is altered. Files in `files/portfolio/`, protected from direct access.
- Templates **100 % Twig**, no jQuery and no icon font (inline SVG). Works with prosilver and its child styles.
- CLI commands: backup, restore and phpBB Gallery migration.
- Events for developers: `verturin.portfolio.album_create_before`, `verturin.portfolio.photo_add_before`, `verturin.portfolio.photo_trash_before`.

## Support

Bug reports and suggestions: [GitHub issues](https://github.com/verturin/portfolio/issues).

## License

[GNU General Public License v2](license.txt)
