# Architecture

A single Laravel application owns authentication, HTML rendering, authorization, persistence, imports and downloads. Blade renders the server UI; Vite bundles local fonts, application JS, Tiptap and StPageFlip. No API secret is sent to the client, and no account/content data relies on localStorage. LocalStorage holds display preferences only.

| Area | Source | Data / boundary |
|---|---|---|
| Public catalogue | CatalogController, SearchCatalog | Only currently public works and chapters; explicit preview authorization |
| Writing | WriterController, ChapterWriter | Owner/admin policy, optimistic revision, snapshots, work-level locks |
| Community | CommunityController | Verified sessions; work-scoped replies/progress; user-owned shelves/bookmarks |
| Files | Uploads, ScanWorkFile, VirusScanner, EpubImporter | Private quarantine, content checks, scan + integrity check before availability |
| Downloads | DownloadController, BookExport | Authorization on each request, published text only, safe attachments |
| Administration | AdminController, Staff/StaffTwoFactor | Role-specific controls, rights review, audit trail |
| Identity | Fortify actions, User, AccountController | Verification, reset, TOTP, data export/deletion, session revocation |
| Reading | resources/js/reader | Unicode offsets; measured pagination; logical/physical RTL indices |
| Scheduling | NotifyChapters, routes/console.php | Once-only follower notifications after release time |

`Author` is separate from `User`: administrators can catalogue licensed works by external authors without creating false login accounts. `Work.owner_id` controls management. Rights status and downloadable status are independent.

The database migration contains accounts/extensions, authors, categories, works, chapters and versions, work files, comments, reviews, favorites, follows, progress, bookmarks, shelves, reports, views, downloads, audits, notifications, posts and settings. Foreign keys and unique indexes enforce the core associations. SQLite is supported locally; PostgreSQL is the production target.

Queue reservations default to 150 seconds; the worker timeout is 120 seconds and each scan job 90 seconds. A scanner process has a 45-second limit. Repeat execution of an already completed file import is a no-op. Failures preserve quarantine. Very large imported chapters are divided into editable text parts rather than silently truncated.

The book reader stores offsets as Unicode code points, consistent with PHP mb_strlen. Text is never sliced by UTF-16 code units. Page fit is measured with the actual loaded font; viewport/font changes rebuild pages around the reading offset. Arabic physical page order is reversed. Reduced-motion users get near-instant transitions, and the scroll reader remains a fallback.

No cloud hosting account, object storage service, external search index, payment flow or AI content service is required by the application. A larger deployment can replace the database-backed cache/queue/search with dedicated services after measuring real load; that is not silently assumed here.
