<?php

return [

  /*
      |--------------------------------------------------------------------------
      | Base Path
      |--------------------------------------------------------------------------
      |
      | The directory (relative to base_path()) where all extracted rule files
      | will be written. The directory is created automatically if it does not
      | exist. Individual rule files are placed directly in this directory.
      | When a section is extracted using a nested strategy, a sub-directory
      | named after the rule slug is also created inside this path.
      |
      | Example: '.ai/rules' → extracted files live at <project>/.ai/rules/
      |
      */
  'base_path' => '.ai/rules',

  /*
      |--------------------------------------------------------------------------
      | Source Directories
      |--------------------------------------------------------------------------
      |
      | An array of directory paths (relative to base_path()) that will be
      | scanned for agent directive Markdown files (*.md). The scanner looks for
      | files containing at least one `=== title ===` section boundary, which is
      | the standard marker used by Boost-generated agent directive files.
      |
      | Use '/' to scan the project root. Add additional paths to also scan
      | subdirectories, e.g. ['/', 'docs', '.ai/guidelines'].
      |
      */
  'source_directories' => [
    '/',
  ],

  /*
      |--------------------------------------------------------------------------
      | Exceptions
      |--------------------------------------------------------------------------
      |
      | Section titles that should NEVER be extracted, regardless of their line
      | count or any configured strategy. These are merged at runtime with the
      | hard-coded exceptions list defined on the command class itself.
      |
      | Use this to protect sections that must remain inline — for example,
      | framework-level directives that other tools expect to find verbatim
      | inside the agent directive file.
      |
      | Values must match the raw section title exactly as it appears between the
      | `=== ... ===` markers (case-sensitive, no leading/trailing whitespace).
      |
      | Example:
      |   'exceptions' => [
      |       'my custom rules',
      |       'project setup notes',
      |   ],
      |
      */
  'exceptions' => [
    '.ai/_app-directive rules',
    '.ai/laravel-filament-directive rules',
    'foundation rules',
    'boost rules',
    'php rules',
    'laravel/core rules',
  ],

  /*
      |--------------------------------------------------------------------------
      | Line Threshold
      |--------------------------------------------------------------------------
      |
      | The minimum number of body lines a section must contain before it is
      | eligible for extraction. Sections at or below this threshold are left
      | in-place, regardless of the configured strategy.
      |
      | This acts as a noise filter — very short sections (e.g. a single link or
      | a one-sentence note) are unlikely to benefit from being split into a
      | separate file.
      |
      | The CLI `--min-lines` option overrides this value at runtime when set.
      |
      */
  'line_threshold' => 5,

  /*
      |--------------------------------------------------------------------------
      | Extraction Strategy
      |--------------------------------------------------------------------------
      |
      | Controls how qualifying sections are extracted. Applies globally to all
      | sections unless overridden per-section via `section_overrides`.
      |
      | Available strategies:
      |
      |   'full_section'
      |       The default. The entire section body (all content between the
      |       `=== title ===` markers) is written to a single flat rule file
      |       (_rule_{slug}.md) and replaced with a one-line placeholder.
      |
      |   'nested_full'
      |       The full section (including all Markdown sub-headers) is extracted.
      |       A master rule file (_rule_{slug}.md) is created at the base_path
      |       root containing the senior-most header content and an index of
      |       sub-sections. Each sub-section is written to its own file inside a
      |       _rule_{slug}/ subdirectory. Only a single placeholder is inserted
      |       at the extraction site.
      |
      |   'nested_subsections'
      |       Only the subsection bodies are extracted. The main header and its
      |       direct (non-subsection) content remains in-place in the source
      |       file. Each subsection is written to _rule_{slug}/_subsection_{sub}.md
      |       and replaced inline with a RULE reference pointing to that file.
      |
      |   'nested_split'
      |       Like 'nested_subsections', but the main header's own body content
      |       is also extracted to a root-level _rule_{slug}.md file, leaving
      |       only the header title and subsection references in the source file.
      |
      |   'auto'
      |       Smart selection. If the section body contains no Markdown headers
      |       (# / ## / ### etc.), behaves as 'full_section'. If headers are
      |       detected, behaves as 'nested_full'.
      |
      */
  'extraction_strategy' => 'nested_subsections',

  /*
      |--------------------------------------------------------------------------
      | Subsection Line Threshold
      |--------------------------------------------------------------------------
      |
      | When using a nested strategy ('nested_full', 'nested_subsections', or
      | 'nested_split'), this is the minimum number of lines a detected sub-
      | section must contain to be extracted into its own file.
      |
      | Sub-sections below this threshold are kept inline (included in the
      | master file content rather than written to individual files), preventing
      | trivially small headers from proliferating the rules directory.
      |
      | Must be >= 1. Setting to 1 extracts all detected sub-sections.
      |
      */
  'subsection_line_threshold' => 3,

  /*
      |--------------------------------------------------------------------------
      | Section Overrides
      |--------------------------------------------------------------------------
      |
      | Allows individual sections to use a different extraction strategy from
      | the global `extraction_strategy` setting. The key is the raw section
      | title exactly as it appears between the `=== ... ===` markers, and the
      | value is one of the five strategy strings documented above.
      |
      | This is useful when most sections should use the default strategy but
      | one or two large, well-structured sections benefit from nested extraction.
      |
      | Example:
      |   'section_overrides' => [
      |       'filament/filament rules'        => 'nested_full',
      |       'spatie/laravel-activitylog rules' => 'nested_subsections',
      |       'laravel/core rules'             => 'full_section',
      |   ],
      |
      */
  'section_overrides' => [],

  /*
      |--------------------------------------------------------------------------
      | Auto-Run After Boost
      |--------------------------------------------------------------------------
      |
      | When true, the service provider registers a listener on the
      | CommandFinished event that automatically runs `agent:optimize` after
      | `boost:update` or `boost:install` completes.
      |
      | Set to false to disable the listener entirely and run the command
      | manually (or via your own composer scripts).
      |
      */
  'auto_run_after_boost' => true,

  /*
      |--------------------------------------------------------------------------
      | Manage Composer Scripts
      |--------------------------------------------------------------------------
      |
      | When true, the service provider will ensure that the line
      |
      |   "@php artisan agent:optimize --ansi"
      |
      | is present in the `post-update-cmd` array of your application's
      | composer.json. When false (or when the key is absent), the line is
      | removed if it exists.
      |
      | The composer.json file is only written when a change is actually needed.
      | This action runs once per console bootstrap, so it is lightweight.
      |
      | Note: enabling this will re-encode your composer.json through PHP's
      | json_encode, which normalises indentation to 4 spaces and may reorder
      | nothing but will standardise whitespace.
      |
      */
  'manage_composer_scripts' => false,

];
