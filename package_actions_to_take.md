### add to composer.json
  ```
  "post-update-cmd": [
      ....
              "@php artisan boost:update --ansi",
              "@php artisan agent:optimize --ansi"
          ],
  ```

### add to providers.php
  ```php
  use App\Providers\AgentOptimizerServiceProvider;


  return [
      ....
      AgentOptimizerServiceProvider::class,
  ];
  ```

### create `config\agent-optimizer.php`
  ```php
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

  ];

  ```

### Create command and command file: 
  `app\Console\Commands\AgentDirectiveOptimizeCommand.php`
  ```php
  <?php

  namespace App\Console\Commands;

  use Illuminate\Console\Attributes\Description;
  use Illuminate\Console\Attributes\Signature;
  use Illuminate\Console\Command;

  #[Signature('agent:optimize {--root= : Root directory to scan (defaults to base_path())} {--min-lines= : Minimum section line count before extraction (overrides config line_threshold)} {--dry-run : Show what would be extracted without writing files}')]
  #[Description('Extract long guideline sections from agent directive files into modular .ai/rules/ files')]
  class AgentDirectiveOptimizeCommand extends Command
  {
      /**
      * Section titles that are ALWAYS excluded from extraction, regardless of
      * config or line count. Merged with config('agent-optimizer.exceptions').
      *
      * @var array<int, string>
      */
      protected array $exceptions = [
          // 'example section title to always exclude',
      ];

      /**
      * Valid extraction strategy identifiers.
      *
      * @var array<int, string>
      */
      protected array $validStrategies = [
          'full_section',
          'nested_full',
          'nested_subsections',
          'nested_split',
          'auto',
      ];

      // -------------------------------------------------------------------------
      // Entry point
      // -------------------------------------------------------------------------

      /**
      * Execute the console command.
      */
      public function handle(): int
      {
          $root = rtrim((string) ($this->option('root') ?: base_path()), DIRECTORY_SEPARATOR);

          // CLI --min-lines overrides config when explicitly provided.
          $minLines = $this->option('min-lines') !== null
              ? (int) $this->option('min-lines')
              : (int) config('agent-optimizer.line_threshold', 5);

          $dryRun = (bool) $this->option('dry-run');
          $rulesFolder = trim((string) config('agent-optimizer.base_path', '.ai/rules'), '/\\');
          $rulesDir = $root.'/'.$rulesFolder;

          if (! $dryRun && ! is_dir($rulesDir)) {
              mkdir($rulesDir, 0755, true);
          }

          $files = $this->resolveAgentDirectiveFiles($root);

          if (empty($files)) {
              $this->info('No agent directive files found.');

              return self::SUCCESS;
          }

          $this->line('<info>Agent files found:</info>');
          foreach ($files as $filePath) {
              $this->line('  '.basename($filePath));
          }
          $this->newLine();

          /** @var array<string, array{title: string, ruleFile: string, appliedTo: array<int, string>}> $extractions */
          $extractions = [];
          $writtenSlugs = [];

          foreach ($files as $filePath) {
              $this->processFile($filePath, $minLines, $rulesDir, $dryRun, $writtenSlugs, $extractions);
          }

          if (! empty($extractions)) {
              $this->line('<comment>Rules Directory:</comment> '.$rulesFolder);
              $this->newLine();
              $this->line('<info>Optimizations:</info>');
              foreach ($extractions as $entry) {
                  $verb = $dryRun ? '[dry-run] Would extract' : 'Extracted';
                  $this->line("  <comment>{$verb}:</comment> === {$entry['title']} ===");
                  $this->line('  <comment>From:</comment> '.implode(', ', $entry['appliedTo']));
                  $this->line("  <comment>Created:</comment> {$entry['ruleFile']}.\n");
              }
          } else {
              $this->info('No sections met the criteria for extraction.');
          }

          $count = count($extractions);

          if ($dryRun) {
              $this->info("Dry run: {$count} section(s) would be extracted.");
          } else {
              $this->info("Optimization complete. {$count} section(s) extracted from each of ".count($files).' file(s).');
          }

          return self::SUCCESS;
      }

      // -------------------------------------------------------------------------
      // File discovery
      // -------------------------------------------------------------------------

      /**
      * Scan configured source directories for *.md files containing === sections.
      *
      * @return array<int, string>
      */
      protected function resolveAgentDirectiveFiles(string $root): array
      {
          /** @var array<int, string> $sourceDirs */
          $sourceDirs = config('agent-optimizer.source_directories', ['/']);
          $files = [];

          foreach ($sourceDirs as $dir) {
              $scanPath = rtrim($root.'/'.ltrim((string) $dir, '/\\'), '/\\');

              foreach (glob($scanPath.'/*.md') ?: [] as $path) {
                  $content = file_get_contents($path);

                  if ($content !== false && preg_match('/^=== .+ ===/m', $content)) {
                      $files[] = $path;
                  }
              }
          }

          // Deduplicate in case source_directories entries overlap.
          return array_values(array_unique($files));
      }

      // -------------------------------------------------------------------------
      // File processing
      // -------------------------------------------------------------------------

      /**
      * Process a single agent directive file: parse sections, resolve strategy
      * per section, extract qualifying sections, and replace with placeholders.
      *
      * @param  array<string, bool>  $writtenSlugs
      * @param  array<string, array{title: string, ruleFile: string, appliedTo: array<int, string>}>  $extractions
      */
      protected function processFile(
          string $filePath,
          int $minLines,
          string $rulesDir,
          bool $dryRun,
          array &$writtenSlugs,
          array &$extractions
      ): void {
          $content = file_get_contents($filePath);

          if ($content === false) {
              $this->warn("Could not read: {$filePath}");

              return;
          }

          $workingContent = $this->extractBoostBlock($content);
          $hasWrapper = $workingContent !== $content;

          $sections = $this->parseSections($workingContent);
          $modified = $workingContent;
          $exceptions = $this->resolveExceptions();
          $filename = basename($filePath);

          foreach ($sections as $section) {
              $title = $section['title'];
              $body = $section['body'];
              $slug = $this->slugifyTitle($title);

              if (in_array($title, $exceptions, true)) {
                  continue;
              }

              if ($section['line_count'] <= $minLines) {
                  continue;
              }

              $strategy = $this->resolveStrategy($title, $body);

              if ($strategy === 'full_section') {
                  $modified = $this->applyFullSection(
                      $title, $body, $section['raw'], $slug, $rulesDir, $dryRun,
                      $filename, $writtenSlugs, $extractions, $modified
                  );
              } else {
                  $modified = $this->applyNestedStrategy(
                      $strategy, $title, $body, $section['raw'], $slug, $rulesDir, $dryRun,
                      $filename, $writtenSlugs, $extractions, $modified
                  );
              }
          }

          if (! $dryRun && $modified !== $workingContent) {
              $newContent = $hasWrapper
                  ? preg_replace(
                      '/<laravel-boost-guidelines>.*?<\/laravel-boost-guidelines>/s',
                      "<laravel-boost-guidelines>\n".rtrim($modified)."\n</laravel-boost-guidelines>",
                      $content
                  )
                  : $modified;

              file_put_contents($filePath, (string) $newContent);
          }
      }

      // -------------------------------------------------------------------------
      // Strategy: full_section
      // -------------------------------------------------------------------------

      /**
      * Extract an entire section body to a single flat rule file.
      *
      * @param  array<string, bool>  $writtenSlugs
      * @param  array<string, array{title: string, ruleFile: string, appliedTo: array<int, string>}>  $extractions
      */
      protected function applyFullSection(
          string $title,
          string $body,
          string $raw,
          string $slug,
          string $rulesDir,
          bool $dryRun,
          string $filename,
          array &$writtenSlugs,
          array &$extractions,
          string $modified
      ): string {
          $ruleFile = '_rule_'.$slug.'.md';
          $placeholder = "=== {$title} ===\n\n**RULE:** {$title}: .ai/rules/{$ruleFile}\n";

          if (str_contains($modified, $placeholder)) {
              return $modified;
          }

          if ($dryRun) {
              if (! isset($writtenSlugs[$slug])) {
                  $writtenSlugs[$slug] = true;
                  $extractions[$slug] = ['title' => $title, 'ruleFile' => $ruleFile, 'appliedTo' => []];
              }
              $extractions[$slug]['appliedTo'][] = $filename;

              return $modified;
          }

          if (! isset($writtenSlugs[$slug])) {
              file_put_contents($rulesDir.'/'.$ruleFile, $raw);
              $writtenSlugs[$slug] = true;
              $extractions[$slug] = ['title' => $title, 'ruleFile' => $ruleFile, 'appliedTo' => []];
          }

          $extractions[$slug]['appliedTo'][] = $filename;

          return str_replace($raw, $placeholder."\n", $modified);
      }

      // -------------------------------------------------------------------------
      // Strategy: nested variants
      // -------------------------------------------------------------------------

      /**
      * Dispatch nested strategy extraction based on the resolved strategy name.
      *
      * @param  array<string, bool>  $writtenSlugs
      * @param  array<string, array{title: string, ruleFile: string, appliedTo: array<int, string>}>  $extractions
      */
      protected function applyNestedStrategy(
          string $strategy,
          string $title,
          string $body,
          string $raw,
          string $slug,
          string $rulesDir,
          bool $dryRun,
          string $filename,
          array &$writtenSlugs,
          array &$extractions,
          string $modified
      ): string {
          $subHeaders = $this->parseMarkdownHeaders($body);
          $subThreshold = (int) config('agent-optimizer.subsection_line_threshold', 3);

          // Filter subsections that meet the subsection threshold.
          $qualifyingHeaders = array_values(array_filter(
              $subHeaders,
              fn (array $h): bool => $h['line_count'] >= $subThreshold
          ));

          // If no qualifying subsections exist, fall back to full_section.
          if (empty($qualifyingHeaders)) {
              return $this->applyFullSection(
                  $title, $body, $raw, $slug, $rulesDir, $dryRun,
                  $filename, $writtenSlugs, $extractions, $modified
              );
          }

          return match ($strategy) {
              'nested_full' => $this->applyNestedFull(
                  $title, $body, $raw, $slug, $rulesDir, $dryRun,
                  $filename, $subHeaders, $qualifyingHeaders, $writtenSlugs, $extractions, $modified
              ),
              'nested_subsections' => $this->applyNestedSubsections(
                  $title, $raw, $slug, $rulesDir, $dryRun,
                  $filename, $subHeaders, $qualifyingHeaders, $writtenSlugs, $extractions, $modified,
                  false
              ),
              'nested_split' => $this->applyNestedSubsections(
                  $title, $raw, $slug, $rulesDir, $dryRun,
                  $filename, $subHeaders, $qualifyingHeaders, $writtenSlugs, $extractions, $modified,
                  true
              ),
              default => $this->applyFullSection(
                  $title, $body, $raw, $slug, $rulesDir, $dryRun,
                  $filename, $writtenSlugs, $extractions, $modified
              ),
          };
      }

      /**
      * nested_full: Extract the full section. Write a master rule file at the
      * rules root and individual subsection files inside _rule_{slug}/.
      * Insert a single placeholder at the extraction site.
      *
      * @param  array<int, array{level: int, title: string, body: string, raw: string, line_count: int}>  $allHeaders
      * @param  array<int, array{level: int, title: string, body: string, raw: string, line_count: int}>  $qualifying
      * @param  array<string, bool>  $writtenSlugs
      * @param  array<string, array{title: string, ruleFile: string, appliedTo: array<int, string>}>  $extractions
      */
      protected function applyNestedFull(
          string $title,
          string $body,
          string $raw,
          string $slug,
          string $rulesDir,
          bool $dryRun,
          string $filename,
          array $allHeaders,
          array $qualifying,
          array &$writtenSlugs,
          array &$extractions,
          string $modified
      ): string {
          $masterFile = '_rule_'.$slug.'.md';
          $placeholder = "=== {$title} ===\n\n**RULE:** {$title}: .ai/rules/{$masterFile}\n";

          if (str_contains($modified, $placeholder)) {
              return $modified;
          }

          if ($dryRun) {
              if (! isset($writtenSlugs[$slug])) {
                  $writtenSlugs[$slug] = true;
                  $extractions[$slug] = ['title' => $title, 'ruleFile' => $masterFile, 'appliedTo' => []];
              }
              $extractions[$slug]['appliedTo'][] = $filename;

              return $modified;
          }

          if (! isset($writtenSlugs[$slug])) {
              $subDir = $rulesDir.'/_rule_'.$slug;

              if (! is_dir($subDir)) {
                  mkdir($subDir, 0755, true);
              }

              $seniorLevel = $this->getSeniorHeaderLevel($allHeaders);
              $subsectionSlugs = [];

              // Write qualifying subsections to the subdirectory.
              foreach ($qualifying as $header) {
                  $subSlug = $this->slugifyTitle($header['title']);
                  $subFile = $subDir.'/_subsection_'.$subSlug.'.md';
                  file_put_contents($subFile, $header['raw']);
                  $subsectionSlugs[$header['title']] = $subSlug;
              }

              // Build and write master file.
              $masterContent = $this->buildMasterContent(
                  $title, $slug, $allHeaders, $qualifying, $seniorLevel, $subsectionSlugs
              );
              file_put_contents($rulesDir.'/'.$masterFile, $masterContent);

              $writtenSlugs[$slug] = true;
              $extractions[$slug] = ['title' => $title, 'ruleFile' => $masterFile, 'appliedTo' => []];
          }

          $extractions[$slug]['appliedTo'][] = $filename;

          return str_replace($raw, $placeholder."\n", $modified);
      }

      /**
      * nested_subsections / nested_split: Optionally extract the main body to
      * its own file (split=true) and always extract qualifying subsections to
      * _rule_{slug}/ with inline RULE references replacing each subsection.
      *
      * @param  array<int, array{level: int, title: string, body: string, raw: string, line_count: int}>  $allHeaders
      * @param  array<int, array{level: int, title: string, body: string, raw: string, line_count: int}>  $qualifying
      * @param  array<string, bool>  $writtenSlugs
      * @param  array<string, array{title: string, ruleFile: string, appliedTo: array<int, string>}>  $extractions
      */
      protected function applyNestedSubsections(
          string $title,
          string $raw,
          string $slug,
          string $rulesDir,
          bool $dryRun,
          string $filename,
          array $allHeaders,
          array $qualifying,
          array &$writtenSlugs,
          array &$extractions,
          string $modified,
          bool $splitMain
      ): string {
          // Dry-run: record one extraction entry per subsection.
          if ($dryRun) {
              foreach ($qualifying as $header) {
                  $subSlug = $this->slugifyTitle($header['title']);
                  $compositeSlug = $slug.'--'.$subSlug;
                  $subFile = '_rule_'.$slug.'/_subsection_'.$subSlug.'.md';

                  if (! isset($writtenSlugs[$compositeSlug])) {
                      $writtenSlugs[$compositeSlug] = true;
                      $extractions[$compositeSlug] = [
                          'title' => $title.' > '.$header['title'],
                          'ruleFile' => $subFile,
                          'appliedTo' => [],
                      ];
                  }
                  $extractions[$compositeSlug]['appliedTo'][] = $filename;
              }

              if ($splitMain) {
                  $mainCompositeSlug = $slug.'--main';
                  $mainFile = '_rule_'.$slug.'.md';

                  if (! isset($writtenSlugs[$mainCompositeSlug])) {
                      $writtenSlugs[$mainCompositeSlug] = true;
                      $extractions[$mainCompositeSlug] = [
                          'title' => $title.' (main body)',
                          'ruleFile' => $mainFile,
                          'appliedTo' => [],
                      ];
                  }
                  $extractions[$mainCompositeSlug]['appliedTo'][] = $filename;
              }

              return $modified;
          }

          $subDir = $rulesDir.'/_rule_'.$slug;

          if (! is_dir($subDir)) {
              mkdir($subDir, 0755, true);
          }

          // Extract qualifying subsections and build inline replacements.
          $replacedRaw = $raw;

          foreach ($qualifying as $header) {
              $subSlug = $this->slugifyTitle($header['title']);
              $compositeSlug = $slug.'--'.$subSlug;
              $subFile = '_rule_'.$slug.'/_subsection_'.$subSlug.'.md';
              $headerPrefix = str_repeat('#', $header['level']);
              $subPlaceholder = "{$headerPrefix} {$header['title']}\n\n**RULE:** .ai/rules/{$subFile}\n";

              if (! isset($writtenSlugs[$compositeSlug])) {
                  file_put_contents($subDir.'/_subsection_'.$subSlug.'.md', $header['raw']);
                  $writtenSlugs[$compositeSlug] = true;
                  $extractions[$compositeSlug] = [
                      'title' => $title.' > '.$header['title'],
                      'ruleFile' => $subFile,
                      'appliedTo' => [],
                  ];
              }

              $extractions[$compositeSlug]['appliedTo'][] = $filename;

              // Replace the subsection's full raw block with an inline reference.
              $replacedRaw = str_replace($header['raw'], $subPlaceholder."\n", $replacedRaw);
          }

          // nested_split: also extract the main section body to its own rule file.
          if ($splitMain) {
              $mainCompositeSlug = $slug.'--main';
              $mainFile = '_rule_'.$slug.'.md';

              if (! isset($writtenSlugs[$mainCompositeSlug])) {
                  // Write the modified section (subsections replaced by references) to the rule file.
                  file_put_contents($rulesDir.'/'.$mainFile, $replacedRaw);
                  $writtenSlugs[$mainCompositeSlug] = true;
                  $extractions[$mainCompositeSlug] = [
                      'title' => $title.' (main body)',
                      'ruleFile' => $mainFile,
                      'appliedTo' => [],
                  ];
              }
              $extractions[$mainCompositeSlug]['appliedTo'][] = $filename;

              // Replace the entire original raw block with a single placeholder.
              $masterPlaceholder = "=== {$title} ===\n\n**RULE:** {$title}: .ai/rules/{$mainFile}\n";

              return str_replace($raw, $masterPlaceholder."\n", $modified);
          }

          // nested_subsections: leave main header + direct body in-place, only
          // replace the subsection blocks with inline RULE references.
          return str_replace($raw, $replacedRaw, $modified);
      }

      // -------------------------------------------------------------------------
      // Master file builder
      // -------------------------------------------------------------------------

      /**
      * Build the master index file content for a nested_full extraction.
      *
      * The most senior (lowest # level) header content is included verbatim.
      * All other headers become index entries linking to their subsection files.
      * If all headers share the same level, the master is a pure index.
      *
      * @param  array<int, array{level: int, title: string, body: string, raw: string, line_count: int}>  $allHeaders
      * @param  array<int, array{level: int, title: string, body: string, raw: string, line_count: int}>  $qualifying
      * @param  array<string, string>  $subsectionSlugs  title => slug
      */
      protected function buildMasterContent(
          string $sectionTitle,
          string $slug,
          array $allHeaders,
          array $qualifying,
          int $seniorLevel,
          array $subsectionSlugs
      ): string {
          $qualifyingTitles = array_column($qualifying, 'title');
          $lines = ["# {$sectionTitle}", ''];

          // Determine if any senior-level header has meaningful body content.
          $hasSeniorContent = false;

          foreach ($allHeaders as $header) {
              if ($header['level'] === $seniorLevel && trim($header['body']) !== '') {
                  $hasSeniorContent = true;
                  break;
              }
          }

          foreach ($allHeaders as $header) {
              $isSenior = $header['level'] === $seniorLevel;
              $isQualifying = in_array($header['title'], $qualifyingTitles, true);

              if ($isSenior && $hasSeniorContent) {
                  // Include senior-level content verbatim.
                  $lines[] = $header['raw'];
              } elseif ($isQualifying && isset($subsectionSlugs[$header['title']])) {
                  $subSlug = $subsectionSlugs[$header['title']];
                  $headerPrefix = str_repeat('#', $header['level']);
                  $subRef = ".ai/rules/_rule_{$slug}/_subsection_{$subSlug}.md";
                  $lines[] = "{$headerPrefix} {$header['title']}";
                  $lines[] = '';
                  $lines[] = "**RULE:** {$header['title']}: {$subRef}";
                  $lines[] = '';
              } else {
                  // Non-qualifying header: include raw content as-is.
                  $lines[] = $header['raw'];
              }
          }

          return implode("\n", $lines);
      }

      // -------------------------------------------------------------------------
      // Markdown header parser
      // -------------------------------------------------------------------------

      /**
      * Parse Markdown headers (# through ######) from a section body.
      * Returns each header with its title, level, body content, and line count.
      *
      * @return array<int, array{level: int, title: string, body: string, raw: string, line_count: int}>
      */
      protected function parseMarkdownHeaders(string $body): array
      {
          $pattern = '/^(#{1,6})\s+(.+?)\s*$\n?(.*?)(?=^#{1,6}\s|\z)/ms';
          preg_match_all($pattern, $body, $matches, PREG_SET_ORDER);

          $headers = [];

          foreach ($matches as $match) {
              $level = strlen($match[1]);
              $title = trim($match[2]);
              $content = $match[3];
              $raw = $match[0];
              $lineCount = substr_count(rtrim($content), "\n") + 1;

              $headers[] = [
                  'level' => $level,
                  'title' => $title,
                  'body' => $content,
                  'raw' => $raw,
                  'line_count' => $lineCount,
              ];
          }

          return $headers;
      }

      /**
      * Return the most senior (lowest numeric level) header level from a set.
      *
      * @param  array<int, array{level: int, title: string, body: string, raw: string, line_count: int}>  $headers
      */
      protected function getSeniorHeaderLevel(array $headers): int
      {
          if (empty($headers)) {
              return 1;
          }

          return min(array_column($headers, 'level'));
      }

      // -------------------------------------------------------------------------
      // Strategy resolution
      // -------------------------------------------------------------------------

      /**
      * Determine the extraction strategy for a given section.
      *
      * Resolution order:
      *   1. config section_overrides keyed by raw section title
      *   2. global config extraction_strategy
      *   3. 'auto' is resolved here: inspect body for headers, return full_section or nested_full
      */
      protected function resolveStrategy(string $title, string $body): string
      {
          /** @var array<string, string> $overrides */
          $overrides = config('agent-optimizer.section_overrides', []);

          $strategy = $overrides[$title]
              ?? (string) config('agent-optimizer.extraction_strategy', 'full_section');

          if (! in_array($strategy, $this->validStrategies, true)) {
              $strategy = 'full_section';
          }

          if ($strategy === 'auto') {
              $headers = $this->parseMarkdownHeaders($body);
              $strategy = empty($headers) ? 'full_section' : 'nested_full';
          }

          return $strategy;
      }

      // -------------------------------------------------------------------------
      // Helpers
      // -------------------------------------------------------------------------

      /**
      * Return only the content inside <laravel-boost-guidelines> if the wrapper
      * exists, otherwise return the full content unchanged.
      */
      protected function extractBoostBlock(string $content): string
      {
          if (preg_match('/<laravel-boost-guidelines>(.*?)<\/laravel-boost-guidelines>/s', $content, $matches)) {
              return $matches[1];
          }

          return $content;
      }

      /**
      * Parse the content into sections keyed by === title === boundaries.
      *
      * @return array<int, array{title: string, body: string, raw: string, line_count: int}>
      */
      protected function parseSections(string $content): array
      {
          $pattern = '/^(=== (.+?) ===\s*\n)(.*?)(?=^=== .+ ===|\z)/ms';
          preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

          $sections = [];

          foreach ($matches as $match) {
              $titleHeader = $match[1];
              $title = trim($match[2]);
              $body = $match[3];
              $raw = $titleHeader.$body;
              $lineCount = substr_count(rtrim($body), "\n");

              $sections[] = [
                  'title' => $title,
                  'body' => $body,
                  'raw' => $raw,
                  'line_count' => $lineCount,
              ];
          }

          return $sections;
      }

      /**
      * Merge class-level exceptions with any defined in config('agent-optimizer.exceptions').
      *
      * @return array<int, string>
      */
      protected function resolveExceptions(): array
      {
          return array_merge(
              $this->exceptions,
              config('agent-optimizer.exceptions', [])
          );
      }

      /**
      * Convert a section title to a filesystem-safe slug.
      */
      protected function slugifyTitle(string $title): string
      {
          $slug = strtolower($title);
          $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? $slug;

          return trim($slug, '-');
      }
  }
  ```

### Create Service Provider `app\Providers\AgentOptimizerServiceProvider.php`
  ```php
  <?php

  namespace App\Providers;

  use App\Console\Commands\AgentDirectiveOptimizeCommand;
  use Illuminate\Console\Events\CommandFinished;
  use Illuminate\Support\Facades\Artisan;
  use Illuminate\Support\ServiceProvider;

  /**
  * Registers the agent:optimize command, its configuration defaults, and the
  * post-boost listener that keeps extracted rule files in sync whenever the
  * Boost agent directive files are regenerated.
  *
  * To publish the configuration file for customisation:
  *
  *   php artisan vendor:publish --tag=ai-rules-config
  */
  class AgentOptimizerServiceProvider extends ServiceProvider
  {
      /**
      * Register config defaults so callers can use config('agent-optimizer.*')
      * even before the user has published the config file.
      */
      public function register(): void
      {
          $this->mergeConfigFrom(
              config_path('agent-optimizer.php'),
              'agent-optimizer'
          );
      }

      /**
      * Bootstrap the service: publish config, register the Artisan command, and
      * wire the automatic post-boost optimisation listener.
      */
      public function boot(): void
      {
          $this->publishes(
              [config_path('agent-optimizer.php') => config_path('agent-optimizer.php')],
              'ai-rules-config'
          );

          if ($this->app->runningInConsole()) {
              $this->commands([
                  AgentDirectiveOptimizeCommand::class,
              ]);
          }

          $this->registerPostBoostListener();
      }

      /**
      * After boost:update or boost:install completes, automatically run
      * agent:optimize so extracted rule files stay in sync with the freshly
      * regenerated agent directive files.
      *
      * A static re-entrance guard prevents double-firing if the commands
      * are somehow nested.
      */
      protected function registerPostBoostListener(): void
      {
          static $running = false;

          $this->app->make('events')->listen(
              CommandFinished::class,
              function (CommandFinished $event) use (&$running): void {
                  if ($running) {
                      return;
                  }

                  if (! in_array($event->command, ['boost:update', 'boost:install'], true)) {
                      return;
                  }

                  $running = true;

                  try {
                      Artisan::call('agent:optimize', [], $event->output);
                  } finally {
                      $running = false;
                  }
              }
          );
      }
  }
  ```

### Create publishable `config\agent-optimizer.php`
  ```php
  php artisan vendor:publish --tag=ai-rules-config
  ```

