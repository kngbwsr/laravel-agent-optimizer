# Laravel Agent Optimizer

A Laravel package that optimizes AI agent directive files by extracting large guideline sections into modular rule files, keeping your top-level agent directives lean and focused.

Works seamlessly with [Laravel Boost](https://github.com/laravel-boost/boost)-generated agent directive files and automatically re-runs after `boost:update` or `boost:install`.

---

## Features

- **Automatic section extraction** — scans `*.md` agent directive files for `=== title ===` sections and moves qualifying sections to dedicated rule files
- **Five extraction strategies** — `full_section`, `nested_full`, `nested_subsections`, `nested_split`, and `auto` to suit any section structure
- **Per-section strategy overrides** — apply a different strategy to individual sections without changing the global default
- **Configurable line threshold** — only extract sections that exceed a minimum line count, filtering out trivial sections
- **Configurable file naming** — control the prefix and extension for generated rule and subsection files (e.g. `.mdc` for Cursor IDE)
- **Configurable reference pretext** — customise the bold label inserted before every file-path reference, globally or per-strategy
- **Exception list** — protect specific sections from ever being extracted
- **Dry-run mode** — preview what would be extracted without writing any files
- **Laravel Boost integration** — automatically re-optimizes after configured trigger commands (default: `boost:update`, `boost:install`)
- **Composer script management** — `agent:install` adds/removes the `post-update-cmd` entry; or set `manage_composer_scripts => true` to automate it
- **Laravel auto-discovery** — zero manual registration required for Laravel 10+

---

## Requirements

- PHP 8.2+
- Laravel 13+

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

Alternatively, set `manage_composer_scripts => true` in `config/agent-optimizer.php` and the service provider will manage the entry automatically on every console bootstrap.

---

## Configuration

After publishing, `config/agent-optimizer.php` exposes the following options:

### Paths & file discovery

```php
// Directory (relative to base_path()) where extracted rule files are written.
'base_path' => '.ai/rules',

// Directories scanned for *.md agent directive files (relative to base_path()).
// Files must contain at least one === title === section boundary.
'source_directories' => ['/'],
```

### Generated file naming

```php
// Prefix for top-level rule file names. Default produces _rule_my-section.md.
// Also used as the subdirectory prefix for nested strategies (_rule_my-section/).
'rule_file_prefix' => '_rule_',

// Prefix for subsection file names inside a rule subdirectory.
// Default produces _subsection_my-header.md.
'subsection_file_prefix' => '_subsection_',

// Extension for all generated rule files (without leading dot).
// Use 'mdc' for Cursor IDE, 'txt' if your tooling requires it.
'rule_file_extension' => 'md',
```

### Extraction behaviour

```php
// Section titles that are NEVER extracted (case-sensitive, exact match).
'exceptions' => [
    '.ai/_app-directive rules',
    'foundation rules',
],

// Minimum body line count a section must exceed to be eligible for extraction.
// The --min-lines CLI flag overrides this at runtime.
'line_threshold' => 5,

// Global extraction strategy. Available values:
//   full_section       — entire section body → single flat rule file
//   nested_full        — full section + sub-headers → master file + per-subsection files
//   nested_subsections — subsections extracted individually; main header stays in-place
//   nested_split       — like nested_subsections but also extracts the main body
//   auto               — full_section when no sub-headers, auto_nested_strategy otherwise
'extraction_strategy' => 'nested_subsections',

// When strategy is 'auto' and sub-headers are detected, this concrete nested
// strategy is applied. Valid: 'nested_full', 'nested_subsections', 'nested_split'.
'auto_nested_strategy' => 'nested_full',

// Minimum line count for a sub-header to be extracted in a nested strategy.
'subsection_line_threshold' => 3,

// Per-section strategy overrides. Key = raw section title, value = strategy string.
'section_overrides' => [
    // 'filament/filament rules' => 'nested_full',
    // 'laravel/core rules'      => 'full_section',
],
```

### Reference pretext labels

Controls the bold label inserted before every rule file path in placeholder lines.

```php
'reference_pretext' => [

    // Fallback labels used when no strategy-specific override is set.
    // 'section'     — top-level === title === replacement lines.
    // 'sub_section' — inline Markdown sub-header replacement lines.
    'defaults' => [
        'section'     => '**RULE:**',
        'sub_section' => '**RULE:**',
    ],

    // Per-strategy overrides. Set a key to null to inherit from defaults.
    // Supply an array to override 'section' and/or 'sub_section' individually
    // (a null value for an individual key still falls back to the default).
    'strategies' => [
        'full_section'       => null,
        'nested_full'        => null,
        'nested_subsections' => null,
        'nested_split'       => null,
        'auto'               => null,
    ],
],
```

Example — different labels per strategy:

```php
'reference_pretext' => [
    'defaults' => [
        'section'     => '**Rules Directory:**',
        'sub_section' => '**RULE:**',
    ],
    'strategies' => [
        'nested_subsections' => [
            'sub_section' => '**Directive:**',
        ],
        'nested_split' => [
            'section'     => '**Rules Directory:**',
            'sub_section' => '**Directive:**',
        ],
    ],
],
```

### Laravel Boost integration

```php
// When true, agent:optimize runs automatically after any command listed in
// boost_trigger_commands via a CommandFinished event listener.
'auto_run_after_boost' => true,

// Artisan commands that trigger the automatic agent:optimize re-run.
// Add custom wrappers around standard Boost commands here if needed.
'boost_trigger_commands' => [
    'boost:update',
    'boost:install',
],

// XML-like tag name that Laravel Boost wraps its generated content block with.
// Change only if you use a custom Boost fork with a different tag name.
'boost_wrapper_tag' => 'laravel-boost-guidelines',

// When true, the service provider ensures @php artisan agent:optimize --ansi
// is present in composer.json post-update-cmd on every console bootstrap.
'manage_composer_scripts' => false,
```

---

## Usage

### Basic run

```bash
php artisan agent:optimize
```

Scans all configured `source_directories` for agent directive Markdown files, extracts qualifying sections to `base_path`, and replaces each extracted block with a configurable reference placeholder.

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
   - A rule file is written to `base_path` using the configured `rule_file_prefix` and `rule_file_extension`.
   - The original section body is replaced with a reference placeholder using the configured pretext label and the `base_path`-relative path.
4. If the source file was modified it is written back to disk.

### Extraction strategies

| Strategy | Behaviour |
|---|---|
| `full_section` | Entire section body → single flat rule file. One placeholder replaces the whole block. |
| `nested_full` | Full section extracted to a master file; each qualifying sub-header gets its own file in a `rule_file_prefix + slug/` subdirectory. One placeholder at the extraction site. |
| `nested_subsections` | Qualifying sub-headers extracted individually into a subdirectory. The main header and its direct body stay in the source file; each sub-header is replaced with an inline reference. |
| `nested_split` | Like `nested_subsections` but the main section body is also extracted to its own root-level rule file. Only the header title and sub-header references remain in-place. |
| `auto` | No sub-headers detected → `full_section`. Sub-headers detected → strategy from `auto_nested_strategy` (default `nested_full`). |

---

## License

MIT

