<?php

namespace kngbwsr\LaravelAgentOptimizer\Providers;

use kngbwsr\LaravelAgentOptimizer\Commands\AgentDirectiveOptimizeCommand;
use kngbwsr\LaravelAgentOptimizer\Commands\AgentOptimizerInstallCommand;
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
      __DIR__ . '/../../config/agent-optimizer.php',
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
      [__DIR__ . '/../../config/agent-optimizer.php' => config_path('agent-optimizer.php')],
      'agent-optimizer-config'
    );

    if ($this->app->runningInConsole()) {
      $this->commands([
        AgentDirectiveOptimizeCommand::class,
        AgentOptimizerInstallCommand::class,
      ]);

      if (config('agent-optimizer.manage_composer_scripts', false)) {
        Artisan::call('agent:install');
      }
    }

    if (config('agent-optimizer.auto_run_after_boost', true)) {
      $this->registerPostBoostListener();
    }
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
