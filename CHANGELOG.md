# Changelog

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
