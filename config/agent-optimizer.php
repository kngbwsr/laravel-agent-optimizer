<?php

return [

  /*
      |--------------------------------------------------------------------------
      | Base Path
      |--------------------------------------------------------------------------
      |
      | The parent directory (relative to base_path()) that holds the package's
      | dedicated output folder. The package always creates a fixed sub-folder
      | named "agent-optimized" inside this path and writes all extracted rule
      | files there.
      |
      | Example: '.ai/rules' → extracted files live at <project>/.ai/rules/agent-optimized/
      |
      | The preflight step on each run deletes and recreates the agent-optimized
      | folder entirely, ensuring no stale files remain when config changes.
      |
      */
  'base_path' => '.ai/rules',

  /*
      |--------------------------------------------------------------------------
      | Rule File Prefix
      |--------------------------------------------------------------------------
      |
      | The prefix prepended to every top-level rule file slug when generating
      | file names. For example, the default '_rule_' produces file names like
      | _rule_my-section.md.
      |
      | Also used as the subdirectory prefix when nested strategies create a
      | per-section folder (e.g. _rule_my-section/).
      |
      */
  'rule_file_prefix' => '_rule_',

  /*
      |--------------------------------------------------------------------------
      | Subsection File Prefix
      |--------------------------------------------------------------------------
      |
      | The prefix prepended to subsection file slugs when generating file
      | names inside a rule subdirectory. The default '_subsection_' produces
      | names like _subsection_my-header.md.
      |
      */
  'subsection_file_prefix' => '_subsection_',

  /*
      |--------------------------------------------------------------------------
      | Rule File Extension
      |--------------------------------------------------------------------------
      |
      | The file extension (without the leading dot) used for every generated
      | rule file. The default 'md' works for standard Markdown tooling.
      |
      | Set to 'mdc' for Cursor IDE rule files, or 'txt' if your AI tooling
      | does not require a Markdown extension.
      |
      */
  'rule_file_extension' => 'md',

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
  'exceptions' => [],

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
      | Auto Nested Strategy
      |--------------------------------------------------------------------------
      |
      | When the global (or per-section override) strategy is 'auto' and the
      | section body contains Markdown headers, the command must pick a concrete
      | nested strategy to apply. This setting controls that choice.
      |
      | Valid values: 'nested_full', 'nested_subsections', 'nested_split'.
      | Falls back to 'nested_full' for any unrecognised value.
      |
      */
  'auto_nested_strategy' => 'nested_full',

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
      | CommandFinished event that automatically runs `optimizeAgents:optimize`
      | after `boost:update` or `boost:install` completes.
      |
      | Set to false to disable the listener entirely and run the command
      | manually (or via your own composer scripts).
      |
      */
  'auto_run_after_boost' => true,

  /*
      |--------------------------------------------------------------------------
      | Boost Trigger Commands
      |--------------------------------------------------------------------------
      |
      | The Artisan command names that trigger an automatic
      | `optimizeAgents:optimize` run when `auto_run_after_boost` is true. Add any additional Boost-like
      | commands here if your workflow uses custom wrappers around the standard
      | Boost commands.
      |
      */
  'boost_trigger_commands' => [
    'boost:update',
    'boost:install',
  ],

  /*
      |--------------------------------------------------------------------------
      | Boost Wrapper Tag
      |--------------------------------------------------------------------------
      |
      | The XML-like tag name that Laravel Boost wraps its generated guidelines
      | block with. The optimizer uses this to locate and update only the Boost-
      | managed portion of an agent directive file, leaving any content outside
      | the block untouched.
      |
      | Change this only if you use a custom Boost fork or a different tool that
      | wraps guidelines with a different tag name.
      |
      | Default: 'laravel-boost-guidelines'
      |
      */
  'boost_wrapper_tag' => 'laravel-boost-guidelines',

  /*
      |--------------------------------------------------------------------------
      | Reference Pretext Labels
      |--------------------------------------------------------------------------
      |
      | Controls the bold label text inserted before the file-path in every
      | reference line written into agent directive files during extraction.
      |
      | Two reference types are distinguished:
      |
      |   'section'     — the single-line placeholder that replaces an entire
      |                   === title === block (top-level section reference).
      |   'sub_section' — the inline reference that replaces a Markdown sub-
      |                   header (## / ### etc.) block within a section.
      |
      | 'defaults' defines the fallback label for each type. These are used
      | whenever a strategy entry is null or does not specify that type.
      |
      | 'strategies' allows per-strategy overrides. Set a strategy key to null
      | to inherit both defaults. Supply an array with 'section' and/or
      | 'sub_section' keys to override individually (a null value for an
      | individual key still falls back to the corresponding default).
      |
      | Example:
      |   'strategies' => [
      |       'nested_subsections' => [
      |           'section'     => null,                   // use defaults.section
      |           'sub_section' => '**Directive:**',
      |       ],
      |       'nested_split' => [
      |           'section'     => '**Rules Directory:**',
      |           'sub_section' => '**Directive:**',
      |       ],
      |   ],
      |
      */
  'reference_pretext' => [
    'defaults' => [
      'section'     => '**RULE:**',
      'sub_section' => '**RULE:**',
    ],
    'strategies' => [
      'full_section'       => null,
      'nested_full'        => null,
      'nested_subsections' => null,
      'nested_split'       => null,
      'auto'               => null,
    ],
  ],

];
