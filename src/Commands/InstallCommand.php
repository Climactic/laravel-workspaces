<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workspaces:install
                            {--force : Overwrite existing files}
                            {--migrate : Run migrations after installation}
                            {--key-type= : Primary key type (id, uuid, ulid)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install the Laravel Workspaces package';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->components->info('Installing Laravel Workspaces...');
        $this->newLine();

        // Run interactive wizard unless --no-interaction is passed
        $config = $this->gatherConfiguration();

        // Publish config
        $this->publishConfig($config);

        // Publish migrations
        $this->publishMigrations();

        // Run migrations if requested
        if ($this->shouldRunMigrations()) {
            $this->runMigrations();
        }

        // Show next steps
        $this->showNextSteps($config);

        return self::SUCCESS;
    }

    /**
     * Gather configuration from user input or options.
     *
     * @return array<string, mixed>
     */
    protected function gatherConfiguration(): array
    {
        $isInteractive = ! $this->option('no-interaction') && $this->input->isInteractive();

        // Primary key type
        $keyType = $this->option('key-type');
        if (! $keyType && $isInteractive) {
            $keyType = select(
                label: 'What primary key type do you want to use?',
                options: [
                    'id' => 'Auto-increment ID (default)',
                    'uuid' => 'UUID',
                    'ulid' => 'ULID',
                ],
                default: 'id'
            );
        }
        $keyType = $keyType ?: 'id';

        // User model path
        $userModel = 'App\\Models\\User';
        if ($isInteractive) {
            $detectedModel = $this->detectUserModel();
            $userModel = text(
                label: 'What is your User model class?',
                default: $detectedModel,
                hint: 'The fully qualified class name of your User model'
            );
        }

        // Context resolver
        $contextResolver = 'auth';
        if ($isInteractive) {
            $contextResolver = select(
                label: 'How should the current workspace be determined?',
                options: [
                    'auth' => 'From authenticated user (default)',
                    'subdomain' => 'From request subdomain (e.g., acme.yourapp.com)',
                    'route' => 'From route parameter (e.g., /workspace/{workspace}/...)',
                    'header' => 'From HTTP header (X-Workspace-Id)',
                    'session' => 'From session',
                ],
                default: 'auth',
                hint: 'You can configure multiple resolvers later in config/workspaces.php'
            );
        }

        // Soft deletes
        $softDeletes = true;
        if ($isInteractive) {
            $softDeletes = confirm(
                label: 'Enable soft deletes for workspaces?',
                default: true,
                hint: 'Deleted workspaces can be restored if enabled'
            );
        }

        // Auto-create workspace on registration
        $autoCreate = true;
        if ($isInteractive) {
            $autoCreate = confirm(
                label: 'Automatically create a workspace when a user registers?',
                default: true,
                hint: 'Creates a personal workspace for new users'
            );
        }

        // Invitations
        $invitationsEnabled = true;
        $invitationExpiry = 7;
        if ($isInteractive) {
            $invitationsEnabled = confirm(
                label: 'Enable the invitation system?',
                default: true,
                hint: 'Allows workspace admins to invite users via email'
            );

            if ($invitationsEnabled) {
                $invitationExpiry = (int) select(
                    label: 'How long should invitations be valid?',
                    options: [
                        '1' => '1 day',
                        '3' => '3 days',
                        '7' => '7 days (default)',
                        '14' => '14 days',
                        '30' => '30 days',
                    ],
                    default: '7'
                );
            }
        }

        // Scope behavior
        $throwWhenMissing = false;
        if ($isInteractive) {
            $scopeBehavior = select(
                label: 'What should happen when querying without a workspace context?',
                options: [
                    'empty' => 'Return empty results (safer default)',
                    'throw' => 'Throw an exception (stricter)',
                ],
                default: 'empty',
                hint: 'Applies to models using the BelongsToWorkspace trait'
            );
            $throwWhenMissing = $scopeBehavior === 'throw';
        }

        return [
            'key_type' => $keyType,
            'user_model' => $userModel,
            'context_resolver' => $contextResolver,
            'soft_deletes' => $softDeletes,
            'auto_create' => $autoCreate,
            'invitations_enabled' => $invitationsEnabled,
            'invitation_expiry' => $invitationExpiry,
            'throw_when_missing' => $throwWhenMissing,
        ];
    }

    /**
     * Publish the config file.
     *
     * @param  array<string, mixed>  $config
     */
    protected function publishConfig(array $config): void
    {
        $this->components->task('Publishing config file', function () {
            $this->callSilently('vendor:publish', [
                '--tag' => 'workspaces-config',
                '--force' => $this->option('force'),
            ]);

            return true;
        });

        // Update config with user's choices
        $configPath = config_path('workspaces.php');
        if (File::exists($configPath)) {
            $contents = File::get($configPath);

            // Update primary key type
            if ($config['key_type'] !== 'id') {
                $contents = preg_replace(
                    "/'primary_key_type' => 'id'/",
                    "'primary_key_type' => '{$config['key_type']}'",
                    $contents
                );
            }

            // Update user model
            $escapedModel = str_replace('\\', '\\\\', $config['user_model']);
            $contents = preg_replace(
                "/'user_model' => .*?,/",
                "'user_model' => \\{$escapedModel}::class,",
                $contents
            );

            // Update soft deletes
            if (! $config['soft_deletes']) {
                $contents = preg_replace(
                    "/'soft_deletes' => true/",
                    "'soft_deletes' => false",
                    $contents
                );
            }

            // Update auto-create on registration
            if (! $config['auto_create']) {
                $contents = preg_replace(
                    "/'enabled' => true,(\s*'name_from')/",
                    "'enabled' => false,$1",
                    $contents
                );
            }

            // Update invitations
            if (! $config['invitations_enabled']) {
                $contents = preg_replace(
                    "/'invitations' => \[\s*'enabled' => true/",
                    "'invitations' => [\n        'enabled' => false",
                    $contents
                );
            }

            // Update invitation expiry
            if ($config['invitation_expiry'] !== 7) {
                $contents = preg_replace(
                    "/'expires_after_days' => 7/",
                    "'expires_after_days' => {$config['invitation_expiry']}",
                    $contents
                );
            }

            // Update scope behavior
            if ($config['throw_when_missing']) {
                $contents = preg_replace(
                    "/'throw_when_missing' => false/",
                    "'throw_when_missing' => true",
                    $contents
                );
            }

            // Update context resolver
            $resolverClass = $this->getResolverClass($config['context_resolver']);
            if ($resolverClass) {
                $contents = preg_replace(
                    "/'resolvers' => \[\s*\\\\Climactic\\\\Workspaces\\\\ContextResolvers\\\\AuthUserResolver::class,\s*\]/",
                    "'resolvers' => [\n            {$resolverClass},\n        ]",
                    $contents
                );
            }

            File::put($configPath, $contents);
        }
    }

    /**
     * Get the resolver class for the selected context resolver.
     */
    protected function getResolverClass(string $resolver): ?string
    {
        return match ($resolver) {
            'auth' => null, // Keep default
            'subdomain' => '\\Climactic\\Workspaces\\ContextResolvers\\SubdomainResolver::class',
            'route' => '\\Climactic\\Workspaces\\ContextResolvers\\RouteParameterResolver::class',
            'header' => '\\Climactic\\Workspaces\\ContextResolvers\\HeaderResolver::class',
            'session' => '\\Climactic\\Workspaces\\ContextResolvers\\SessionResolver::class',
            default => null,
        };
    }

    /**
     * Publish the migration files.
     */
    protected function publishMigrations(): void
    {
        $this->components->task('Publishing migrations', function () {
            $this->callSilently('vendor:publish', [
                '--tag' => 'workspaces-migrations',
                '--force' => $this->option('force'),
            ]);

            return true;
        });
    }

    /**
     * Determine if migrations should be run.
     */
    protected function shouldRunMigrations(): bool
    {
        if ($this->option('migrate')) {
            return true;
        }

        if ($this->option('no-interaction') || ! $this->input->isInteractive()) {
            return false;
        }

        return confirm(
            label: 'Do you want to run migrations now?',
            default: true
        );
    }

    /**
     * Run the migrations.
     */
    protected function runMigrations(): void
    {
        $this->components->task('Running migrations', function () {
            $this->callSilently('migrate');

            return true;
        });
    }

    /**
     * Show the next steps after installation.
     *
     * @param  array<string, mixed>  $config
     */
    protected function showNextSteps(array $config): void
    {
        $this->newLine();
        $this->components->info('Laravel Workspaces installed successfully!');
        $this->newLine();

        $this->components->twoColumnDetail('<fg=green>Primary Key Type</>', $config['key_type']);
        $this->components->twoColumnDetail('<fg=green>User Model</>', $config['user_model']);
        $this->components->twoColumnDetail('<fg=green>Context Resolver</>', $this->getResolverLabel($config['context_resolver']));
        $this->components->twoColumnDetail('<fg=green>Soft Deletes</>', $config['soft_deletes'] ? 'Enabled' : 'Disabled');
        $this->components->twoColumnDetail('<fg=green>Auto-create Workspace</>', $config['auto_create'] ? 'Enabled' : 'Disabled');
        $this->components->twoColumnDetail('<fg=green>Invitations</>', $config['invitations_enabled'] ? "Enabled ({$config['invitation_expiry']} days)" : 'Disabled');

        $this->newLine();
        $this->components->info('Next Steps:');
        $this->newLine();

        $step = 1;

        // Step: Add trait to User model
        $this->line("  <fg=yellow>{$step}.</> Add the <fg=cyan>HasWorkspaces</> trait to your User model:");
        $this->newLine();
        $this->line('     <fg=gray>use Climactic\\Workspaces\\Concerns\\HasWorkspaces;</>');
        $this->newLine();
        $this->line('     <fg=gray>class User extends Authenticatable</>');
        $this->line('     <fg=gray>{</>');
        $this->line('         <fg=gray>use HasWorkspaces;</>');
        $this->line('     <fg=gray>}</>');
        $this->newLine();
        $step++;

        // Step: Add middleware
        $this->line("  <fg=yellow>{$step}.</> Add the <fg=cyan>workspace</> middleware to your routes:");
        $this->newLine();
        $this->line("     <fg=gray>Route::middleware(['auth', 'workspace'])->group(function () {</>");
        $this->line('         <fg=gray>// Your workspace routes...</>');
        $this->line('     <fg=gray>});</>');
        $this->newLine();
        $step++;

        // Step: Context-specific instructions
        if ($config['context_resolver'] === 'subdomain') {
            $this->line("  <fg=yellow>{$step}.</> Configure your domain in <fg=cyan>.env</>:");
            $this->newLine();
            $this->line('     <fg=gray>WORKSPACES_DOMAIN=yourapp.com</>');
            $this->newLine();
            $step++;
        } elseif ($config['context_resolver'] === 'route') {
            $this->line("  <fg=yellow>{$step}.</> Use workspace route parameter:");
            $this->newLine();
            $this->line("     <fg=gray>Route::prefix('workspace/{workspace}')->group(function () {</>");
            $this->line('         <fg=gray>// Routes with workspace parameter...</>');
            $this->line('     <fg=gray>});</>');
            $this->newLine();
            $step++;
        }

        // Step: Optional scaffolding
        $this->line("  <fg=yellow>{$step}.</> (Optional) Generate example controllers and routes:");
        $this->newLine();
        $this->line('     <fg=gray>php artisan workspaces:scaffold</>');
        $this->newLine();
        $step++;

        $this->components->bulletList([
            'Documentation: <fg=blue>https://github.com/climactic/laravel-workspaces</>',
            'Discord: <fg=blue>http://go.climactic.co/discord</>',
        ]);

        // Offer to run scaffold command
        $this->offerScaffolding($config);
    }

    /**
     * Offer to run the scaffold command.
     *
     * @param  array<string, mixed>  $config
     */
    protected function offerScaffolding(array $config): void
    {
        if ($this->option('no-interaction') || ! $this->input->isInteractive()) {
            return;
        }

        $this->newLine();

        $runScaffold = confirm(
            label: 'Would you like to generate example controllers, routes, and policies now?',
            default: false,
            hint: 'This will run the workspaces:scaffold command'
        );

        if ($runScaffold) {
            $this->newLine();
            $this->call('workspaces:scaffold', [
                '--force' => $this->option('force'),
            ]);
        }
    }

    /**
     * Get a human-readable label for the context resolver.
     */
    protected function getResolverLabel(string $resolver): string
    {
        return match ($resolver) {
            'auth' => 'Authenticated User',
            'subdomain' => 'Subdomain',
            'route' => 'Route Parameter',
            'header' => 'HTTP Header',
            'session' => 'Session',
            default => $resolver,
        };
    }

    /**
     * Detect the user model path.
     */
    protected function detectUserModel(): string
    {
        // Check common locations
        $possiblePaths = [
            'App\\Models\\User',
            'App\\User',
        ];

        foreach ($possiblePaths as $path) {
            if (class_exists($path)) {
                return $path;
            }
        }

        return 'App\\Models\\User';
    }
}
