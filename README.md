# FTP Deploy State Generator

A self-contained PHP tool that generates deploy-state files for [SamKirkland/FTP-Deploy-Action](https://github.com/SamKirkland/FTP-Deploy-Action), enabling fast, hash-based incremental deployments to FTP servers.

<p align="center">
  <img src="https://img.shields.io/badge/version-1.0.0-blue.svg" />
  <img src="https://img.shields.io/badge/PHP-%3E%3D8.1-777BB4.svg" />
  <a href="LICENSE">
    <img alt="License" src="https://img.shields.io/badge/license-MIT-yellow.svg" target="_blank" />
  </a>
</p>

## Description

`FTP-Deploy-Action` tracks already-synced files through a state file so it can skip unchanged content between deployments. Without this file, every run falls back to a full resync, which can take a long time on large codebases. FTP Deploy State Generator recreates that state file locally by scanning a project directory, computing a SHA-256 hash and size for every file, and writing a JSON document that is fully compatible with the action's expected format.

It is built for developers and DevOps engineers who need to bootstrap, restore, or manually maintain the deploy-state file of an FTP-based deployment pipeline — for example after a lost `.ftp-deploy-sync-state.json`, a server migration, or when deploying from environments where the action cannot track its own state.

## Features

- **Full compatibility with SamKirkland/FTP-Deploy-Action** - Produces the exact JSON schema (`version`, `generatedTime`, `data`) the action consumes, so generated files work out of the box
- **SHA-256 content hashing** - Every file entry carries a content hash and size, enabling precise incremental diffing on the next deployment
- **Glob-based exclusion patterns** - Supports `**`, `*`, and `?` wildcards out of the box for `.git`, `node_modules`, Laravel `storage/framework`, `.env`, and more
- **Cross-platform path handling** - Normalizes Windows and Unix separators and emits forward-slash relative paths in the output
- **Deterministic, sorted output** - Entries are sorted files-first, then alphabetically, producing stable, reviewable diffs between runs
- **Zero runtime dependencies** - A single PHP script built only on SPL iterators; no Composer packages are required to run it
- **Strict, static-analysis-verified codebase** - `strict_types` throughout and passing PHPStan level 9 analysis

## Tech Stack

- **Language**: PHP 8.1+ (verified up to PHP 8.4)
- **Core**: SPL (`RecursiveDirectoryIterator`, `RecursiveCallbackFilterIterator`, `RecursiveIteratorIterator`)
- **Quality Tooling**:
  - [PHPStan](https://phpstan.org/) - Static analysis at level 9
  - [PHP-CS-Fixer](https://cs.symfony.com/) - PSR-12 and PHP 8.3 migration rules
  - [Rector](https://getrector.com/) - Automated upgrades and refactoring

## Installation

### Prerequisites

- PHP 8.1 or higher with the standard `hash` and `fileinfo` extensions (included by default)
- Composer (only required for the development toolchain, not to run the generator)

### Steps

1. Clone the repository

```bash
git clone https://github.com/reynaldiarya/FTP-Deploy-State-Generator.git
cd FTP-Deploy-State-Generator
```

2. Run directly - no dependency installation is needed to use the generator

```bash
php generate-sync-state.php /path/to/project
```

Alternatively, install the development toolchain for contributing:

```bash
composer install
```

## Configuration

The generator requires no environment variables. Behavior is controlled through two CLI arguments and one constant in the script.

| Option                   | Location             | Description                                                  | Default                             |
| ------------------------ | -------------------- | ------------------------------------------------------------ | ----------------------------------- |
| Root directory           | First CLI argument   | Project directory to scan (absolute or relative path)        | `.` (current directory)             |
| Output path              | Second CLI argument  | Where the state file is written                              | `<root>/.ftp-deploy-sync-state.json` |
| `EXCLUDE_PATTERNS`       | `generate-sync-state.php` | Glob patterns excluded from the scan (each `'**/pattern/**'` entry) | `.git`, `.idea`, `.vscode`, `.env`, `node_modules`, Laravel `storage/logs`, `storage/framework/{cache,sessions,views}`, `tests`, and the state file itself |

To customize exclusions, edit the `EXCLUDE_PATTERNS` constant at the top of `generate-sync-state.php`:

```php
const EXCLUDE_PATTERNS = [
    '**/.git*/**',
    '**/node_modules/**',
    '**/vendor/**',       // add your own patterns here
    // ...
];
```

## Usage

### Basic generation

Scan the current directory and write the state file to its default location:

```bash
php generate-sync-state.php
```

### Custom root and output path

```bash
php generate-sync-state.php /var/www/my-app /tmp/sync-state.json
```

### Help

```bash
php generate-sync-state.php --help
```

### Exit codes

| Code | Meaning                                   |
| ---- | ----------------------------------------- |
| `0`  | State file generated successfully         |
| `1`  | Invalid arguments or root directory missing |
| `2`  | Failed to write the output file           |

### Output format

```json
{
    "description": "DO NOT DELETE THIS FILE. This file is used to keep track of which files have been synced in the most recent deployment. If you delete this file a resync will need to be done (which can take a while) - read more: https://github.com/SamKirkland/FTP-Deploy-Action",
    "version": "1.0.0",
    "generatedTime": 1790230612941,
    "data": [
        {
            "type": "file",
            "name": "public/index.php",
            "size": 1738,
            "hash": "fffa2bd42c87bcc06646427d3849bac12931cc243128f68b0f2d62bf39f432f3"
        },
        {
            "type": "folder",
            "name": "storage"
        }
    ]
}
```

## Project Structure

```text
/
├── generate-sync-state.php   # The complete generator (single-file CLI tool)
├── phpstan.neon              # PHPStan configuration (level 9)
├── rector.php                # Rector configuration (PHP 8.3 rule sets)
├── .php-cs-fixer.php         # PHP-CS-Fixer configuration (PSR-12)
├── composer.json             # Project metadata and dev tooling scripts
└── vendor/                   # Composer dependencies (dev tools only)
```

## Scripts / Commands

Development tooling is exposed through Composer:

| Command                 | Description                                    |
| ----------------------- | ---------------------------------------------- |
| `composer stan`         | Runs PHPStan static analysis at level 9        |
| `composer cs-check`     | Checks coding style compliance (dry run)       |
| `composer cs-fix`       | Automatically fixes coding style violations    |
| `composer rector`       | Applies Rector refactoring rules               |

## Contributing

Contributions are welcome. Before opening a pull request, ensure the quality gates pass:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/improvement`)
3. Make your changes and verify the quality gates:

```bash
composer cs-fix
composer stan
```

4. Commit your changes (`git commit -m 'Add some improvement'`)
5. Push to the branch (`git push origin feature/improvement`)
6. Open a Pull Request

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for detailed terms and conditions.

## Author

Reynaldi Arya
