<?php

namespace kngbwsr\LaravelAgentOptimizer\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('agent:install {--remove : Remove the agent:optimize entry from post-update-cmd instead of adding it}')]
#[Description('Add or remove the agent:optimize post-update-cmd entry in composer.json')]
class AgentOptimizerInstallCommand extends Command
{
  /**
   * The composer script line this command manages.
   */
  protected const SCRIPT_LINE = '@php artisan agent:optimize --ansi';

  /**
   * Execute the console command.
   */
  public function handle(): int
  {
    $composerPath = base_path('composer.json');

    if (! file_exists($composerPath)) {
      $this->error('composer.json not found at: ' . $composerPath);

      return self::FAILURE;
    }

    $raw = file_get_contents($composerPath);

    if ($raw === false) {
      $this->error('Could not read composer.json.');

      return self::FAILURE;
    }

    /** @var array<string, mixed>|null $composer */
    $composer = json_decode($raw, true);

    if (! is_array($composer)) {
      $this->error('composer.json contains invalid JSON.');

      return self::FAILURE;
    }

    $remove = (bool) $this->option('remove');

    [$changed, $composer] = $remove
      ? $this->removeScript($composer)
      : $this->addScript($composer);

    if (! $changed) {
      $this->info($remove
        ? 'Script line was not present — nothing to remove.'
        : 'Script line is already present — nothing to add.'
      );

      return self::SUCCESS;
    }

    $encoded = json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($encoded === false) {
      $this->error('Failed to encode composer.json.');

      return self::FAILURE;
    }

    file_put_contents($composerPath, $encoded . "\n");

    if ($remove) {
      $this->info('Removed "' . self::SCRIPT_LINE . '" from post-update-cmd.');
    } else {
      $this->info('Added "' . self::SCRIPT_LINE . '" to post-update-cmd.');
      $this->line('Run <comment>composer update --no-interaction</comment> (or any composer update) to verify the script is invoked.');
    }

    return self::SUCCESS;
  }

  /**
   * Ensure the script line exists in scripts.post-update-cmd.
   *
   * @param  array<string, mixed>  $composer
   * @return array{bool, array<string, mixed>}
   */
  protected function addScript(array $composer): array
  {
    /** @var array<int, string> $scripts */
    $scripts = $composer['scripts']['post-update-cmd'] ?? [];

    if (in_array(self::SCRIPT_LINE, $scripts, true)) {
      return [false, $composer];
    }

    $scripts[] = self::SCRIPT_LINE;
    $composer['scripts']['post-update-cmd'] = $scripts;

    return [true, $composer];
  }

  /**
   * Remove the script line from scripts.post-update-cmd if present.
   *
   * @param  array<string, mixed>  $composer
   * @return array{bool, array<string, mixed>}
   */
  protected function removeScript(array $composer): array
  {
    /** @var array<int, string> $scripts */
    $scripts = $composer['scripts']['post-update-cmd'] ?? [];

    $filtered = array_values(array_filter(
      $scripts,
      fn(string $line): bool => $line !== self::SCRIPT_LINE
    ));

    if (count($filtered) === count($scripts)) {
      return [false, $composer];
    }

    if (empty($filtered)) {
      unset($composer['scripts']['post-update-cmd']);

      // Clean up empty scripts key.
      if (isset($composer['scripts']) && empty($composer['scripts'])) {
        unset($composer['scripts']);
      }
    } else {
      $composer['scripts']['post-update-cmd'] = $filtered;
    }

    return [true, $composer];
  }
}
