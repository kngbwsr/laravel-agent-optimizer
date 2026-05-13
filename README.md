# Laravel Agent Optimizer

A Laravel package that optimizes AI agent directive files by extracting large guideline sections into modular `.ai/rules/` files, keeping your top-level agent directives lean and focused.

Works seamlessly with [Laravel Boost](https://github.com/laravel-boost/boost)-generated agent directive files and automatically re-runs after `boost:update` or `boost:install`.

---

## Features

- **Automatic section extraction** — scans `*.md` agent directive files for `=== title ===` sections and moves qualifying sections to dedicated rule files
- **Five extraction strategies** — `full_section`, `nested_full`, `nested_subsections`, `nested_split`, and `auto` to suit any section structure
- **Per-section strategy overrides** — apply a different strategy to individual sections without changing the global default
- **Configurable line threshold** — only extract sections that exceed a minimum line count, filtering out trivial sections
- **Exception list** — protect specific sections from ever being extracted
- **Dry-run mode** — preview what would be extracted without writing any files
- **Laravel Boost integration** — automatically re-optimizes after `boost:update` or `boost:install` (toggle via `auto_run_after_boost`)
- **Composer script management** — `agent:install` adds/removes the `post-update-cmd` entry; or set `manage_composer_scripts => true` to automate it
- **Laravel auto-discovery** — zero manual registration required for Laravel 10+

---

## Requirements

- PHP 8.2+
- Laravel 10 or 11

---

## Installation

Install the package via Composer:

```bash
composer require kngbwsr/laravel-agent-optimizer
```

Laravel's auto-discovery will register the service provider automatically.

### Publish the configuration file

```bash
php artisan vendor:publish --tag=agent-optimizer-config
```

This creates `config/agent-optimizer.php` in your application.

### Optional: add the Composer post-update-cmd script

Run the install command to automatically add `agent:optimize` to your `composer.json` `post-update-cmd`:

```bash
php artisan agent:install
```

To remove the entry later:

```bash
php artisan agent:install --remove
```

Alternatively, set `manage_composer_scripts => true` in `config/agent-optimizer.php` and the service provider will call `agent:install` automatically on every console bootstrap (writing `composer.json` only when the entry is missing).

---

## Configuration

After publishing, `config/agent-optimizer.php` exposes the following options:

```php
return [

    /*
     | The directory (relative to base_path()) where extracted rule files
     | will be written. Created automatically if it does not exist.
     */
    'base_path' => '.ai/rules',

    /*
     | Directories (relative to base_path()) scanned for *.md agent directive
     | files. Files must contain at least one === title === section boundary.
     | Use '/' to scan the project root.
     */
    'source_directories' => [
        '/',
    ],

    /*
     | Section titles that are NEVER extracted, regardless of line count or
     | strategy. Values must match the raw title between the === markers
     | exactly (case-sensitive, no leading/trailing whitespace).
     */
    'exceptions' => [
        '.ai/_app-directive rules',
        'foundation rules',
        'boost rules',
    ],

    /*
     | Minimum body line count a section must exceed before it is eligible
     | for extraction. The --min-lines CLI option overrides this at runtime.
     */
    'line_threshold' => 5,

    /*
     | Global extraction strategy. Available values:
     |
     |   full_section       — entire section body → single flat rule file
     |   nested_full        — full section including all sub-headers → master
     |                        file + per-subsection files in a subdirectory
     |   nested_subsections — subsections extracted individually; main header
     |                        and its direct body stay in-place
     |   nested_split       — like nested_subsections but also extracts the
     |                        main body to its own rule file
     |   auto               — full_section when no sub-headers detected,
     |                        nested_full when sub-headers are detected
     */
    'extraction_strategy' => 'nested_subsections',

    /*
     | Minimum line count for a detected sub-header to be extracted when
     | using a nested strategy. Sub-sections below this threshold are kept
     | inline. Must be >= 1.
     */
    'subsection_line_threshold' => 3,

    /*
     | Per-section strategy overrides. Key = raw section title, value = one
     | of the five strategy strings above.
     |
     | Example:
     |   'section_overrides' => [
     |       'filament/filament rules' => 'nested_full',
     |       'laravel/core rules'      => 'full_section',
     |   ],
     */
    'section_overrides' => [],

    /*
     | When true, automatically runs `agent:optimize` after `boost:update` or
     | `boost:install` completes via a CommandFinished event listener.
     | Set to false to disable the listener and run the command manually.
     */
    'auto_run_after_boost' => true,

    /*
     | When true, the service provider calls `agent:install` on every console
     | bootstrap to ensure the `@php artisan agent:optimize --ansi` line is
     | present in composer.json post-update-cmd. composer.json is only written
     | when a change is needed. Set to false (default) to manage this manually
     | via `php artisan agent:install` / `agent:install --remove`.
     */
    'manage_composer_scripts' => false,

];
```

---

## Usage

### Basic run

```bash
php artisan agent:optimize
```

Scans all configured `source_directories` for agent directive Markdown files, extracts qualifying sections to `.ai/rules/`, and replaces each extracted block with a `**RULE:** path/to/file.md` placeholder.

### Preview without writing files

```bash
php artisan agent:optimize --dry-run
```

Reports every section that *would* be extracted without modifying any file.

### Override the root directory

```bash
php artisan agent:optimize --root=/path/to/project
```

Useful when running the command outside the project root.

### Override the minimum line threshold

```bash
php artisan agent:optimize --min-lines=10
```

Overrides `line_threshold` from config for this run only.

### Combined example

```bash
php artisan agent:optimize --min-lines=8 --dry-run
```

---

## How it works

1. The command scans each configured directory for `*.md` files that contain at least one `=== title ===` section boundary.
2. Each section is parsed and checked against the configured `exceptions` list and `line_threshold`.
3. The configured extraction strategy (global or per-section override) determines how the section is extracted:
   - A rule file is written to `base_path` (e.g. `.ai/rules/_rule_my-section.md`).
   - The original section body is replaced with a one-line `**RULE:** …` pointer.
4. If the source file was modified it is written back to disk.

---

## License

MIT
