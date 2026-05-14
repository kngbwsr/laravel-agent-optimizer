<?php

namespace Kngbwsr\LaravelAgentOptimizer\Providers;

use Kngbwsr\LaravelAgentOptimizer\Commands\AgentDirectiveOptimizeCommand;
use Kngbwsr\LaravelAgentOptimizer\Commands\AgentOptimizerInstallCommand;
use Kngbwsr\LaravelAgentOptimizer\Commands\AgentOptimizerResetCommand;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the optimizeAgents commands, configuration defaults, and the
 * post-boost listener that keeps extracted rule files in sync whenever the
 * Boost agent directive files are regenerated.
 *
 * To publish the configuration file for customisation:
 *
 *   php artisan vendor:publish --tag=agent-optimizer-config
 *
 * For first-time setup (publish config + add composer.json post-update-cmd):
 *
 *   php artisan optimizeAgents:install
 */
class AgentOptimizerServiceProvider extends ServiceProvider
{
  /**
   * When true, the next auto-run trigger after a boost command will be
   * suppressed. Set by AgentOptimizerResetCommand to prevent re-optimization
   * after it deliberately triggers a boost run.
   */
  public static bool $suppressAutoRun = false;

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
   * Bootstrap the service: publish config, register the Artisan commands, and
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
        AgentOptimizerResetCommand::class,
      ]);
    }

    if (config('agent-optimizer.auto_run_after_boost', true)) {
      $this->registerPostBoostListener();
    }
  }

  /**
   * After boost:update or boost:install completes, automatically run
   * optimizeAgents:optimize so extracted rule files stay in sync with the
   * freshly regenerated agent directive files.
   *
   * A static re-entrance guard prevents double-firing if the commands are
   * somehow nested. The $suppressAutoRun flag allows the reset command to
   * trigger boost without causing a re-optimization.
   */
  protected function registerPostBoostListener(): void
  {
    $running = false;

    $this->app->make('events')->listen(
      CommandFinished::class,
      function (CommandFinished $event) use (&$running): void {
        if ($running) {
          return;
        }

        if (! in_array($event->command, config('agent-optimizer.boost_trigger_commands', ['boost:update', 'boost:install']), true)) {
          return;
        }

        // Reset command sets this flag to suppress auto-run after its boost call.
        if (AgentOptimizerServiceProvider::$suppressAutoRun) {
          AgentOptimizerServiceProvider::$suppressAutoRun = false;

          return;
        }

        $running = true;

        try {
          Artisan::call('optimizeAgents:optimize', [], $event->output);
        } finally {
          $running = false;
        }
      }
    );
  }
}
