# Flarum Image Migrate

[![MIT license](https://img.shields.io/badge/license-MIT-blue.svg)](https://github.com/dshovchko/flarum-image-migrate/blob/main/LICENSE)
[![Latest Stable Version](https://img.shields.io/packagist/v/dshovchko/flarum-image-migrate.svg)](https://packagist.org/packages/dshovchko/flarum-image-migrate)
[![Total Downloads](https://img.shields.io/packagist/dt/dshovchko/flarum-image-migrate.svg)](https://packagist.org/packages/dshovchko/flarum-image-migrate)

A Flarum extension that detects external images in posts and helps migrate them to your own storage.

## Features

- 🔍 Detects external images from non-whitelisted domains
- 🛠 Migration mode (`--fix`) that downloads external files, pushes them to your configured backend, and rewrites post content automatically
- 📧 Email reports with detailed statistics and optional on-demand emails
- ⏰ Scheduled automatic checks (daily/weekly/monthly)
- 🎯 Flexible checking: all posts, specific discussion, or single post
- 🔑 Admin-configurable backend credentials with built-in health-check
- 📊 Grouped reporting by discussions and posts, plus a persistent audit log for migrated URLs
- ⚙️ Configurable allowed domains

## Installation

```bash
composer require dshovchko/flarum-image-migrate
```

## Usage

### Configuration

Configure the extension via the admin panel:

1. Go to **Admin → Extensions → Image Migrate**
2. Set **Allowed Origins** (comma-separated domains, e.g., `example.com, cdn.example.com`)
3. Provide the backend connection details: **Backend Base URL**, **Backend Environment** (production/sandbox/etc.), and **Backend API Key**
4. Enable **Scheduled Checks** (optional)
5. Set **Check Frequency** (daily/weekly/monthly)
6. Add **Email Recipients** (comma-separated)

Saving the settings will ping the backend health endpoint; the form cannot be saved while the backend is unreachable. After upgrading to v1.1.0, run `php flarum migrate` once to create the migration log table.

> ⚠️ Scheduled checks require Flarum scheduler to be configured. Add to your crontab:
> ```bash
> * * * * * cd /path/to/flarum && php flarum schedule:run >> /dev/null 2>&1
> ```

### Manual Console Command

Check for external images using the `image-migrate:check` console command:

```bash
# Check a single discussion
php flarum image-migrate:check --discussion=123

# Check a specific post
php flarum image-migrate:check --post=456

# Scan all discussions
php flarum image-migrate:check --all

# Run the scan and migrate matching images immediately
php flarum image-migrate:check --all --fix

# Run the scan and migrate matching images immediately with scale
php flarum image-migrate:check --all --fix --scaleFactor=1.5

# Email the report
php flarum image-migrate:check --all --mailto=admin@example.com

# Combine options
php flarum image-migrate:check --all --mailto=admin@example.com,moderator@example.com
```

> ℹ️ The command requires one of `--discussion=<id>`, `--post=<id>`, or `--all`.
> 🔐 When using `--fix`, the backend settings must be valid and reachable.

### Migration Mode (`--fix`)

When `--fix` is present, the command will:

1. Run the same detection logic as the regular scan
2. Download each external image to a temporary file
3. Upload it to the configured backend using the selected environment
4. Replace the original `src` attribute in the post with the new URL
5. Store an entry in `dshovchko_image_migrate` for auditing (discussion/post IDs, author, old/new URLs)

The command aborts on the first failed upload so that no discussion is partially migrated. Consider running without `--fix` first to review the report, then re-run with `--fix` for the same filters.

### Email Reports

Reports include:
- Total number of external images found
- Number of discussions with issues
- Detailed breakdown by discussion and post
- Direct links to affected discussions and posts
- List of external image URLs

Example report format:
```
External Images Report

Total external images: 56
⚠️ Discussions with issues: 8
==================================================

⚠️ Discussion 8
https://example.com/d/8
 external images in posts (10)
 posts: 14 15 16 17 18 19
  Post #14: 3 image(s)
    https://example.com/d/8/1
    - https://external-cdn.com/image1.jpg
    - https://external-cdn.com/image2.jpg
```

## How It Works

1. The extension scans post content for `<img>` tags
2. Extracts image URLs from the `src` attribute
3. Compares image domains against your allowed origins list
4. Reports any images hosted on external domains
5. Groups results by discussion and post for easy review

## Allowed Origins

Domains in the allowed origins list are considered "internal" and won't be flagged:
- Exact domain match: `example.com` matches `example.com`
- Subdomain match: `example.com` matches `cdn.example.com`, `images.example.com`
- Case-insensitive matching
- Automatic `www.` prefix handling

## Requirements

- Flarum ^1.0
- PHP 7.4+

## Release Checklist

When tagging a new version:

1. Run `cd js && npm ci && npm run build` to regenerate `js/dist/admin.js`.
2. Commit the updated `js/dist` assets together with any code changes (do **not** rely on production servers having Node.js available).
3. Update `CHANGELOG.md` with the new version and summary.
4. Tag the release (`git tag -a vX.Y.Z -m "vX.Y.Z"`) and push the tag to origin.

This ensures Packagist builds include the already-tested TypeScript admin bundle.

## Development

The admin UI is authored in TypeScript and lives in `js/src/admin` (entry point `js/admin.ts`). To work on it locally:

1. `cd js`
2. `npm ci`
3. `npm run dev` for a watch build or `npm run build` for a production bundle

Webpack reads `tsconfig.json`, so updating the TypeScript sources automatically recompiles the `js/dist/admin.js` asset that ships with the extension.

## Future Features

- Automatic retries/backoff for flaky external hosts
- Extended reporting in the admin panel
- Additional image transformation presets

## Links

- [GitHub Repository](https://github.com/dshovchko/flarum-image-migrate)
- [Packagist](https://packagist.org/packages/dshovchko/flarum-image-migrate)
- [Flarum Community](https://discuss.flarum.org)

## License

[MIT](https://github.com/dshovchko/flarum-image-migrate/blob/main/LICENSE)
