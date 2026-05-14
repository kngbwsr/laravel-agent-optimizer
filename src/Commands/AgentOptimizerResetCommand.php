<?php

namespace Kngbwsr\LaravelAgentOptimizer\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Kngbwsr\LaravelAgentOptimizer\Providers\AgentOptimizerServiceProvider;

#[Signature('optimizeAgents:reset')]
#[Description('Remove all generated rule files and re-run Boost to restore original agent directive files')]
class AgentOptimizerResetCommand extends Command
{
  /**
   * Execute the console command.
   */
  public function handle(): int
  {
    // Step 1: delete the agent-optimized folder.
    $basePath = trim((string) config('agent-optimizer.base_path', '.ai/rules'), '/\\');
    $rulesDir = base_path($basePath . '/agent-optimized');

    if (is_dir($rulesDir)) {
      $this->deleteDirectory($rulesDir);
      $this->info('Removed the agent-optimized folder.');
    } else {
      $this->line('No agent-optimized folder found — nothing to clean.');
    }

    // Step 2: run the first configured boost trigger command, suppressing
    // the auto-run listener so optimizeAgents:optimize does not fire afterwards.
    /** @var array<int, string> $boostCommands */
    $boostCommands = config('agent-optimizer.boost_trigger_commands', ['boost:update', 'boost:install']);
    $boostCommand  = $boostCommands[0] ?? 'boost:update';

    $this->line("Running <comment>{$boostCommand}</comment>...");

    AgentOptimizerServiceProvider::$suppressAutoRun = true;

    $exitCode = Artisan::call($boostCommand, [], $this->output);

    if ($exitCode !== self::SUCCESS) {
      $this->error("{$boostCommand} exited with code {$exitCode}.");

      return self::FAILURE;
    }

    $this->info('Reset complete. Run <comment>optimizeAgents:optimize</comment> when you are ready to re-optimize.');

    return self::SUCCESS;
  }

  /**
   * Recursively delete a directory and all of its contents.
   */
  protected function deleteDirectory(string $dir): void
  {
    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
      \RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
      /** @var \SplFileInfo $item */
      $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($dir);
  }
}
