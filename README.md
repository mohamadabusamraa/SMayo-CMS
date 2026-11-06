# SMayo New

Independent PHP/MySQL website and administration panel. For the InfinityFree layout, upload the contents of `public/` into `htdocs/`. The private application files live in `htdocs/smayo_private/`, which is blocked from direct HTTP access by its own `.htaccess`; `uploads/` stays beside it in `htdocs/` so images remain publicly viewable.

## Requirements

- PHP 8.1 or newer with PDO MySQL, Fileinfo, Mbstring, and ZipArchive extensions.
- MySQL 5.7+ or a compatible MariaDB release.
- HTTPS recommended for production login sessions.
- Writable `htdocs/smayo_private/config/`, `htdocs/smayo_private/storage/`, and `htdocs/uploads/` directories during installation and uploads.

## Installation

1. Create an empty MySQL/MariaDB database and a database user with table permissions.
2. Upload the contents of this project's `public/` directory into the hosting `htdocs/`. This includes `index.php`, `admin/`, `install/`, `assets/`, `uploads/`, and `smayo_private/`.
3. Make `htdocs/smayo_private/config/` and `htdocs/smayo_private/storage/` writable during installation. Keep the included `smayo_private/.htaccess` in place; it denies direct browser access to all files below that directory while PHP can still read and write them through filesystem paths.
4. Visit `/install/` and enter the database connection details and the first administrator account.
5. Sign in at `/admin/`. The installer writes credentials to `htdocs/smayo_private/config/database.php` and creates `htdocs/smayo_private/storage/installed.lock`.
6. Keep the `smayo_private/.htaccess` rule intact and test that a browser request to `/smayo_private/` is denied before using the installation.

The installer creates the schema and editable starter content. Starter wording is illustrative and editable; no company address, phone, client, certification, or achievement is asserted.

For an existing installation, the homepage loader recognizes the unchanged original starter documents and presents the new SMayo content through the existing section/settings/design editors. This compatibility path does not write to the database. Saving a draft and publishing it through the CMS persists the content using the current revision workflow; documents that no longer match the old starter content are left intact.

## Editing and publishing

- **Sections:** edit content, choose media, reorder, hide/show, duplicate, and delete sections. Saving creates a draft; preview with the admin session, then publish.
- **Website content:** manage brand identity, logo, contact details, navigation, social links, and footer.
- **Media library:** upload JPG/JPEG, PNG, WebP, or GIF images up to 15 MB, edit alternative text, and archive unused records. Files are stored in `htdocs/uploads/`; content stores media record IDs.
- **Design settings:** set global colors, fonts, base type size, radius, and spacing. Values are validated before the public page emits CSS variables.
- **Users:** admins can create and enable/disable accounts. Passwords use PHP's password hashing functions.
- **Backups:** admins can download a ZIP containing all CMS tables and uploaded media, and restore an archive. Restore first creates a safety backup. Keep backups off the server as well.

## Local development

From the project root, run `php -S 127.0.0.1:8080 -t public` with a MySQL/MariaDB database configured. Open `http://127.0.0.1:8080/install/` and follow the installer. The built-in PHP server is for local development only.

## Moving to another host

1. Create a backup in the admin panel and download it.
2. Upload the public files and the `smayo_private/` directory, preserving `smayo_private/` inside the hosting `htdocs/` and keeping its `.htaccess` protection in place.
3. Create a new database and run the installer with the new credentials.
4. Sign in and restore the backup archive. Confirm the restored media thumbnails and published pages.
5. Change passwords if the hosting environment or backup was exposed, and remove any temporary backup copies from the web server.

## Security notes

- Database credentials, logs, and backup archives are stored below `smayo_private/`; direct HTTP requests to that directory are denied by `smayo_private/.htaccess`.
- Admin forms use session CSRF tokens; uploads are MIME/content checked and get random server filenames.
- Uploaded files cannot execute PHP through the included public `.htaccess` rule.
- Archived media in use by published or draft content is protected from archival.

Do not treat a successful page load as verification of database connectivity, uploads, restore, or publishing. Exercise those flows on a test database before production use.
