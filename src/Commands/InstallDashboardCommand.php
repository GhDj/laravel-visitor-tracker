<?php

declare(strict_types=1);

namespace Ghdj\VisitorTracker\Commands;

use Illuminate\Console\Command;

/**
 * Command to install/enable the visitor tracker dashboard.
 */
class InstallDashboardCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'visitor-tracker:install-dashboard
                            {--force : Overwrite existing config}';

    /**
     * The console command description.
     */
    protected $description = 'Install and enable the visitor tracker dashboard';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Installing Visitor Tracker Dashboard...');
        $this->newLine();

        // Step 1: Publish config if not exists
        $configPath = config_path('visitor-tracker.php');
        if (! file_exists($configPath) || $this->option('force')) {
            $this->call('vendor:publish', [
                '--tag' => 'visitor-tracker-config',
                '--force' => $this->option('force'),
            ]);
            $this->info('Configuration file published.');
        } else {
            $this->info('Configuration file already exists.');
        }

        // Step 2: Update config to enable dashboard
        $this->enableDashboardInConfig($configPath);

        // Step 3: Publish migrations if not exists
        $this->call('vendor:publish', [
            '--tag' => 'visitor-tracker-migrations',
        ]);

        $this->newLine();
        $this->info('Dashboard installed successfully!');
        $this->newLine();

        // Step 4: Show next steps
        $this->showNextSteps();

        return self::SUCCESS;
    }

    /**
     * Enable dashboard in the config file.
     */
    protected function enableDashboardInConfig(string $configPath): void
    {
        if (! file_exists($configPath)) {
            return;
        }

        $config = file_get_contents($configPath);
        if ($config === false) {
            $this->warn('Could not read config file.');

            return;
        }

        // Replace 'enabled' => false with 'enabled' => true in dashboard section
        $pattern = "/('dashboard'\s*=>\s*\[\s*\n\s*'enabled'\s*=>\s*)false/";
        $replacement = '${1}true';

        $newConfig = preg_replace($pattern, $replacement, $config);

        if ($newConfig !== null && $newConfig !== $config) {
            file_put_contents($configPath, $newConfig);
            $this->info('Dashboard enabled in configuration.');
        } else {
            $this->warn('Could not automatically enable dashboard. Please set dashboard.enabled = true in config/visitor-tracker.php');
        }
    }

    /**
     * Show next steps to the user.
     */
    protected function showNextSteps(): void
    {
        $prefix = config('visitor-tracker.dashboard.prefix', 'admin/visitor-tracker');

        $this->components->info('Next steps:');
        $this->newLine();

        $this->line('  1. Run migrations if you haven\'t already:');
        $this->line('     <comment>php artisan migrate</comment>');
        $this->newLine();

        $this->line('  2. Access the dashboard at:');
        $this->line("     <comment>/{$prefix}</comment>");
        $this->newLine();

        $this->line('  3. (Optional) Define a gate for additional authorization:');
        $this->newLine();
        $this->line('     <comment>// In AuthServiceProvider or AppServiceProvider</comment>');
        $this->line('     <comment>Gate::define(\'view-visitor-stats\', function ($user) {</comment>');
        $this->line('     <comment>    return $user->isAdmin();</comment>');
        $this->line('     <comment>});</comment>');
        $this->newLine();

        $this->line('  4. Configure middleware in config/visitor-tracker.php:');
        $this->line('     <comment>\'middleware\' => [\'web\', \'auth\'],</comment>');
        $this->newLine();

        $this->line('  5. (Optional) Publish views to customize:');
        $this->line('     <comment>php artisan vendor:publish --tag=visitor-tracker-views</comment>');
    }
}
