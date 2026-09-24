<?php

declare(strict_types=1);

/**
 * FTP Deploy State Generator
 *
 * Generates a `.ftp-deploy-sync-state.json` file compatible with
 * SamKirkland/FTP-Deploy-Action, containing folder and file entries
 * (with SHA-256 hashes) for the given project root.
 *
 * Usage:
 *   php generate-sync-state.php [root] [output]
 *
 * Arguments:
 *   root    Project root directory to scan (default: current directory)
 *   output  Path of the state file to write (default: <root>/.ftp-deploy-sync-state.json)
 *
 * Exit codes:
 *   0  Success
 *   1  Invalid arguments or root directory not found
 *   2  Failed to write output file
 */

const STATE_VERSION = '1.0.0';

const STATE_DESCRIPTION = 'DO NOT DELETE THIS FILE. This file is used to keep track of '
    . 'which files have been synced in the most recent deployment. If you delete this '
    . 'file a resync will need to be done (which can take a while) - read more: '
    . 'https://github.com/SamKirkland/FTP-Deploy-Action';

const DEFAULT_OUTPUT_FILENAME = '.ftp-deploy-sync-state.json';

const EXCLUDE_PATTERNS = [
    '**/.git*/**',
    '**/.idea*/**',
    '**/.vscode*/**',
    '**/.env',
    '**/node_modules/**',
    '**/storage/logs/**',
    '**/storage/framework/cache/**',
    '**/storage/framework/sessions/**',
    '**/storage/framework/views/**',
    '**/tests/**',
    DEFAULT_OUTPUT_FILENAME,
];

/**
 * Convert a glob pattern to an anchored PCRE regex.
 *
 * Supported syntax:
 *   **  matches any number of path segments
 *   *   matches anything except path separators
 *   ?   matches a single non-separator character
 */
function globToRegex(string $glob): string
{
    $regex = '';
    $length = strlen($glob);

    for ($i = 0; $i < $length; $i++) {
        $char = $glob[$i];

        if ($char === '*') {
            if (($glob[$i + 1] ?? '') === '*') {
                $i++;
                if (($glob[$i + 1] ?? '') === '/') {
                    $i++;
                    $regex .= '(?:.*/)?';
                } else {
                    $regex .= '.*';
                }
            } else {
                $regex .= '[^/]*';
            }
        } elseif ($char === '?') {
            $regex .= '[^/]';
        } else {
            $regex .= preg_quote($char, '#');
        }
    }

    return '#^' . $regex . '$#';
}

/**
 * Determine whether a relative path matches any exclude pattern.
 *
 * @param list<string> $regexes
 */
function isExcluded(string $relativePath, bool $isDir, array $regexes): bool
{
    foreach ($regexes as $regex) {
        if (preg_match($regex, $relativePath)
            || ($isDir && preg_match($regex, $relativePath . '/'))
        ) {
            return true;
        }
    }

    return false;
}

/**
 * Convert an absolute path to a normalized (forward-slash) path
 * relative to the root, without a leading separator.
 */
function toRelativePath(string $absolutePath, string $root): string
{
    $normalized = str_replace('\\', '/', $absolutePath);

    return ltrim(substr($normalized, strlen($root)), '/');
}

/**
 * Build the state entries for every non-excluded file and folder
 * under the root, sorted files-first then alphabetically.
 *
 * @param list<string> $excludeRegexes
 * @return list<array{
 *     type: 'folder'|'file',
 *     name: string,
 *     size?: int<0, max>|false,
 *     hash?: string|false,
 * }>
 */
function buildStateEntries(string $root, array $excludeRegexes): array
{
    $entries = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            static function (SplFileInfo $file) use ($root, $excludeRegexes): bool {
                $relativePath = toRelativePath($file->getPathname(), $root);

                return !isExcluded($relativePath, $file->isDir(), $excludeRegexes);
            },
        ),
        RecursiveIteratorIterator::SELF_FIRST,
    );

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo) {
            continue;
        }

        $relativePath = toRelativePath($file->getPathname(), $root);

        if ($file->isDir()) {
            $entries[] = [
                'type' => 'folder',
                'name' => $relativePath,
            ];
            continue;
        }

        $entries[] = [
            'type' => 'file',
            'name' => $relativePath,
            'size' => $file->getSize(),
            'hash' => hash_file('sha256', $file->getPathname()),
        ];
    }

    usort($entries, static function (array $a, array $b): int {
        $typeA = $a['type'] === 'file' ? 0 : 1;
        $typeB = $b['type'] === 'file' ? 0 : 1;

        return $typeA <=> $typeB ?: strcmp($a['name'], $b['name']);
    });

    return $entries;
}

/**
 * Write the state document to disk.
 *
 * @param list<array{
 *     type: 'folder'|'file',
 *     name: string,
 *     size?: int<0, max>|false,
 *     hash?: string|false,
 * }> $entries
 */
function writeStateFile(string $outputPath, array $entries): void
{
    $json = json_encode(
        [
            'description' => STATE_DESCRIPTION,
            'version' => STATE_VERSION,
            'generatedTime' => (int) (microtime(true) * 1000),
            'data' => $entries,
        ],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
    );

    if ($json === false || file_put_contents($outputPath, $json) === false) {
        fwrite(STDERR, sprintf('Error: unable to write state file to "%s".%s', $outputPath, PHP_EOL));
        exit(2);
    }
}

/**
 * Print usage information.
 */
function printUsage(string $scriptName): void
{
    fwrite(STDERR, sprintf(
        "Usage:\n"
        . "    php %s [root] [output]\n"
        . "\n"
        . "Arguments:\n"
        . "    root    Project root directory to scan (default: current directory)\n"
        . "    output  State file path (default: <root>/%s)\n",
        $scriptName,
        DEFAULT_OUTPUT_FILENAME,
    ));
}

/**
 * Main script execution.
 */
$scriptName = basename($argv[0] ?? __FILE__);

if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
    printUsage($scriptName);
    exit(0);
}

$root = rtrim(str_replace('\\', '/', $argv[1] ?? '.'), '/');
$outputPath = $argv[2] ?? $root . '/' . DEFAULT_OUTPUT_FILENAME;

if (!is_dir($root)) {
    fwrite(STDERR, sprintf('Error: root directory "%s" does not exist.%s', $root, PHP_EOL));
    exit(1);
}

$excludeRegexes = array_map(globToRegex(...), EXCLUDE_PATTERNS);
$entries = buildStateEntries($root, $excludeRegexes);

writeStateFile($outputPath, $entries);

printf(
    'Success: wrote %d entries to "%s".%s',
    count($entries),
    $outputPath,
    PHP_EOL,
);

exit(0);
