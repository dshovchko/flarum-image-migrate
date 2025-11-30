# Changelog

## [1.1.2] - 2025-11-30
### Changed
- Added --scale option to the CLI interface (with numeric/positive validation) so migrations can tweak backend scaling on demand.
- Added TypeScript tooling to the admin bundle (new `tsconfig.json` plus the `flarum-tsconfig` dev dependency) so future TypeScript migration work has the required infrastructure.

### Fixed
- SnapGrab uploads now report the original image URL as the `source` and preserve the original filename/MIME type, eliminating `.bin` artifacts and matching manual uploads.

## [1.1.1] - 2025-11-27
### Fixed
- Added the compiled `js/dist/admin.js` bundle to the package so that the admin panel loads without requiring local asset builds on production servers.
- Documented the release checklist (build admin assets, create tag) to avoid missing files in future releases.

## [1.1.0] - 2025-11-26
### Added
- New migration mode powered by the existing `image-migrate:check` command with the `--fix` flag. External images are downloaded, converted according to the configured presets, uploaded to the configured backend, and the post contents are rewritten automatically.
- Dedicated admin settings for the backend API (base URL, environment selector, API key) plus an automatic health-check when saving.
- Persistent audit log stored in the `dshovchko_image_migrate` table containing discussion/post IDs and original/new URLs.
- Health-check is executed automatically before every migration run to fail fast when the backend is unreachable.

### Changed
- The CLI now stops on the first failed migration to prevent partially converted discussions; the error is shown immediately in the console.
- `--fix` re-uses the existing filtering options (`--discussion`, `--post`, `--all`) so moderators can migrate exactly the subset they just reviewed.
- Admin assets were refreshed to keep the configuration UI available after cache clears.

### Fixed
- Prevented double-closing of temporary file descriptors during uploads which previously triggered fatal errors on some environments.

## [1.0.1] - 2025-11-24
### Fixed
- Fixed scheduled command to properly send email reports from cron
- Refactored console commands: separated CheckImagesCommand and ScheduledCheckImagesCommand
- Fixed email sending logic: manual run without --mailto shows only console output, with --mailto sends email

### Added
- Added --fix option placeholder for future image migration feature (will be implemented in next minor version)

## [1.0.0] - 2025-11-23
- Initial release
- Detects external images in posts
- Console command with `--discussion`, `--post`, and `--all` options
- Email reports with detailed statistics
- Scheduled automatic checks (daily/weekly/monthly)
- Admin panel for configuration
- Support for allowed origins (comma-separated domains)
- Batch processing with configurable chunk size
- Ukrainian and English localizations
