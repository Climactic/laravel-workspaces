<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;

class ScaffoldCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workspaces:scaffold
                            {--force : Overwrite existing files}
                            {--all : Generate all scaffolding without prompts}
                            {--ui= : Generate UI components (react, vue, livewire)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate example controllers and routes for workspaces';

    /**
     * The filesystem instance.
     */
    protected Filesystem $files;

    /**
     * Configuration values.
     *
     * @var array<string, mixed>
     */
    protected array $config;

    /**
     * The selected UI stack.
     */
    protected ?string $uiStack = null;

    /**
     * Create a new command instance.
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->components->info('Scaffolding Laravel Workspaces...');
        $this->newLine();

        // Load configuration
        $this->loadConfig();

        // Show detected configuration
        $this->showDetectedConfig();

        $components = $this->gatherComponents();

        if (empty($components)) {
            $this->components->warn('No components selected. Nothing to generate.');

            return self::SUCCESS;
        }

        // Check if UI scaffolding is requested
        if (in_array('ui', $components)) {
            $this->uiStack = $this->gatherUiStack();

            if (! $this->uiStack) {
                $this->components->warn('No UI stack selected. Skipping UI generation.');
                $components = array_diff($components, ['ui']);
            }
        }

        $force = $this->option('force');

        // Generate selected components
        foreach ($components as $component) {
            match ($component) {
                'controllers' => $this->generateControllers($force),
                'routes' => $this->generateRoutes($force),
                'policy' => $this->generatePolicy($force),
                'ui' => $this->generateUiComponents($force),
                default => null,
            };
        }

        $this->showNextSteps($components);

        return self::SUCCESS;
    }

    /**
     * Load configuration values.
     */
    protected function loadConfig(): void
    {
        $this->config = [
            'invitations_enabled' => config('workspaces.invitations.enabled', true),
            'context_resolver' => $this->detectContextResolver(),
            'roles' => array_keys(config('workspaces.roles', ['owner', 'admin', 'member'])),
            'user_model' => config('workspaces.user_model', 'App\\Models\\User'),
            'workspace_model' => config('workspaces.models.workspace', 'Climactic\\Workspaces\\Models\\Workspace'),
            'invitation_model' => config('workspaces.models.invitation', 'Climactic\\Workspaces\\Models\\WorkspaceInvitation'),
            'route_parameter' => config('workspaces.context.route_parameter.name', 'workspace'),
            'route_field' => config('workspaces.context.route_parameter.field', 'slug'),
        ];
    }

    /**
     * Detect the primary context resolver from config.
     */
    protected function detectContextResolver(): string
    {
        $resolvers = config('workspaces.context.resolvers', []);

        if (empty($resolvers)) {
            return 'auth';
        }

        $firstResolver = $resolvers[0] ?? '';

        return match (true) {
            str_contains($firstResolver, 'SubdomainResolver') => 'subdomain',
            str_contains($firstResolver, 'RouteParameterResolver') => 'route',
            str_contains($firstResolver, 'HeaderResolver') => 'header',
            str_contains($firstResolver, 'SessionResolver') => 'session',
            default => 'auth',
        };
    }

    /**
     * Show detected configuration.
     */
    protected function showDetectedConfig(): void
    {
        $this->components->twoColumnDetail('<fg=gray>Invitations</>', $this->config['invitations_enabled'] ? 'Enabled' : 'Disabled');
        $this->components->twoColumnDetail('<fg=gray>Context Resolver</>', ucfirst($this->config['context_resolver']));
        $this->components->twoColumnDetail('<fg=gray>Available Roles</>', implode(', ', $this->config['roles']));
        $this->newLine();
    }

    /**
     * Gather which components to generate.
     *
     * @return array<string>
     */
    protected function gatherComponents(): array
    {
        // If --ui option is provided, include UI in components
        if ($this->option('ui')) {
            return ['controllers', 'routes', 'policy', 'ui'];
        }

        // Determine available options based on config
        $options = [
            'controllers' => 'Controllers (Workspace, Member'.($this->config['invitations_enabled'] ? ', Invitation' : '').')',
            'routes' => 'Routes file',
            'policy' => 'Workspace Policy',
            'ui' => 'UI Components (React/Vue/Livewire)',
        ];

        if ($this->option('all')) {
            return ['controllers', 'routes', 'policy'];
        }

        if ($this->option('no-interaction') || ! $this->input->isInteractive()) {
            return ['controllers', 'routes'];
        }

        return multiselect(
            label: 'What would you like to generate?',
            options: $options,
            default: ['controllers', 'routes'],
            hint: 'Use space to select, enter to confirm'
        );
    }

    /**
     * Gather which UI stack to use.
     */
    protected function gatherUiStack(): ?string
    {
        // If --ui option is provided with a value, use it
        if ($uiOption = $this->option('ui')) {
            if (in_array($uiOption, ['react', 'vue', 'livewire'])) {
                return $uiOption;
            }

            $this->components->warn("Invalid UI stack: {$uiOption}. Valid options: react, vue, livewire");
        }

        if ($this->option('no-interaction') || ! $this->input->isInteractive()) {
            return null;
        }

        return select(
            label: 'Which UI stack are you using?',
            options: [
                'react' => 'React + Shadcn (Inertia)',
                'vue' => 'Vue + Shadcn (Inertia)',
                'livewire' => 'Livewire + Flux UI',
            ],
            hint: 'Select your frontend stack'
        );
    }

    /**
     * Generate controller files.
     */
    protected function generateControllers(bool $force): void
    {
        // Use Inertia controllers if UI stack is react or vue
        $isInertia = in_array($this->uiStack, ['react', 'vue']);
        $stubFolder = $isInertia ? 'controllers-inertia' : 'controllers';

        $controllers = [
            'WorkspaceController',
            'MemberController',
        ];

        // Only include InvitationController if invitations are enabled
        if ($this->config['invitations_enabled']) {
            $controllers[] = 'InvitationController';
        }

        $controllerPath = app_path('Http/Controllers');

        // Ensure the directory exists
        $this->files->ensureDirectoryExists($controllerPath);

        foreach ($controllers as $controller) {
            $this->components->task("Generating {$controller}", function () use ($controller, $controllerPath, $stubFolder, $force) {
                $stubPath = $this->getStubPath("{$stubFolder}/{$controller}.stub");
                $targetPath = "{$controllerPath}/{$controller}.php";

                if ($this->files->exists($targetPath) && ! $force) {
                    return false;
                }

                $stub = $this->files->get($stubPath);
                $stub = $this->processStub($stub);

                $this->files->put($targetPath, $stub);

                return true;
            });
        }
    }

    /**
     * Generate routes file.
     */
    protected function generateRoutes(bool $force): void
    {
        $this->components->task('Generating routes file', function () use ($force) {
            // Use appropriate routes stub based on UI stack
            $stubFolder = match ($this->uiStack) {
                'react', 'vue' => 'routes-inertia',
                'livewire' => 'routes-livewire',
                default => 'routes',
            };

            $stubPath = $this->getStubPath("{$stubFolder}/workspaces.stub");
            $targetPath = base_path('routes/workspaces.php');

            if ($this->files->exists($targetPath) && ! $force) {
                return false;
            }

            $stub = $this->files->get($stubPath);
            $stub = $this->processStub($stub);

            // Remove invitation routes if disabled (only for API routes)
            if (! $this->config['invitations_enabled'] && $stubFolder === 'routes') {
                $stub = $this->removeInvitationRoutes($stub);
            }

            // Adjust routes for route parameter context (only for API routes)
            if ($this->config['context_resolver'] === 'route' && $stubFolder === 'routes') {
                $stub = $this->wrapWithRouteParameter($stub);
            }

            $this->files->put($targetPath, $stub);

            return true;
        });
    }

    /**
     * Generate policy file.
     */
    protected function generatePolicy(bool $force): void
    {
        $this->components->task('Generating WorkspacePolicy', function () use ($force) {
            $stubPath = $this->getStubPath('policies/WorkspacePolicy.stub');
            $policyPath = app_path('Policies');
            $targetPath = "{$policyPath}/WorkspacePolicy.php";

            $this->files->ensureDirectoryExists($policyPath);

            if ($this->files->exists($targetPath) && ! $force) {
                return false;
            }

            $stub = $this->files->get($stubPath);
            $stub = $this->processStub($stub);

            // Remove invitation policy methods if disabled
            if (! $this->config['invitations_enabled']) {
                $stub = $this->removeInvitationPolicyMethods($stub);
            }

            $this->files->put($targetPath, $stub);

            return true;
        });
    }

    /**
     * Process stub with config-aware replacements.
     */
    protected function processStub(string $stub): string
    {
        $namespace = $this->laravel->getNamespace();

        // Get role validation string from config
        $roles = array_diff($this->config['roles'], ['owner']); // Exclude owner from assignable roles
        $roleValidation = implode(',', $roles);

        $replacements = [
            // Namespace replacements
            '{{ namespace }}' => $namespace,
            '{{namespace}}' => $namespace,
            'App\\' => $namespace,

            // Model replacements
            '{{ userModel }}' => $this->config['user_model'],
            '{{ workspaceModel }}' => $this->config['workspace_model'],
            '{{ invitationModel }}' => $this->config['invitation_model'],

            // Role validation (exclude owner from assignable roles)
            "'in:admin,member'" => "'in:{$roleValidation}'",
            '"in:admin,member"' => "\"in:{$roleValidation}\"",

            // Route parameter
            '{{ routeParameter }}' => $this->config['route_parameter'],
            '{workspace}' => '{'.$this->config['route_parameter'].'}',
        ];

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $stub
        );
    }

    /**
     * Remove invitation-related routes from stub.
     */
    protected function removeInvitationRoutes(string $stub): string
    {
        // Remove invitation controller import
        $stub = preg_replace(
            "/use App\\\\Http\\\\Controllers\\\\InvitationController;\n/",
            '',
            $stub
        );

        // Remove invitation routes section
        $stub = preg_replace(
            "/\n\s*\/\/ Invitation routes.*?->name\('invitations\.decline'\);/s",
            '',
            $stub
        );

        return $stub;
    }

    /**
     * Wrap routes with route parameter prefix for route-based context.
     */
    protected function wrapWithRouteParameter(string $stub): string
    {
        $param = $this->config['route_parameter'];

        // Replace the simple auth middleware group with a prefixed version
        $stub = preg_replace(
            "/Route::middleware\(\['auth'\]\)->group\(function \(\) \{/",
            "Route::middleware(['auth'])->prefix('{$param}/{{$param}}')->group(function () {",
            $stub
        );

        return $stub;
    }

    /**
     * Remove invitation-related policy methods.
     */
    protected function removeInvitationPolicyMethods(string $stub): string
    {
        // Remove the invite policy method
        $stub = preg_replace(
            "/\n\s*\/\*\*\n\s*\* Determine whether the user can invite.*?return.*?;\n\s*\}/s",
            '',
            $stub
        );

        return $stub;
    }

    /**
     * Generate UI components for the selected stack.
     */
    protected function generateUiComponents(bool $force): void
    {
        if (! $this->uiStack) {
            return;
        }

        $this->components->info("Generating {$this->uiStack} UI components...");
        $this->newLine();

        match ($this->uiStack) {
            'react' => $this->generateReactComponents($force),
            'vue' => $this->generateVueComponents($force),
            'livewire' => $this->generateLivewireComponents($force),
            default => null,
        };
    }

    /**
     * Generate React components.
     */
    protected function generateReactComponents(bool $force): void
    {
        $this->generateInertiaComponents('react', 'tsx', $force);
    }

    /**
     * Generate Vue components.
     */
    protected function generateVueComponents(bool $force): void
    {
        $this->generateInertiaComponents('vue', 'vue', $force);
    }

    /**
     * Generate Inertia components (React or Vue).
     */
    protected function generateInertiaComponents(string $stack, string $extension, bool $force): void
    {
        $stubBase = __DIR__."/../../stubs/ui/{$stack}";

        // Generate pages
        $pagesPath = resource_path('js/pages/workspaces');
        $this->files->ensureDirectoryExists($pagesPath);

        $pages = ['Index', 'Create', 'Settings', 'Members', 'Invitations'];

        foreach ($pages as $page) {
            $this->components->task("Generating {$page} page", function () use ($stubBase, $pagesPath, $page, $extension, $force) {
                $stubPath = "{$stubBase}/pages/Workspaces/{$page}.{$extension}.stub";
                $targetPath = "{$pagesPath}/{$page}.{$extension}";

                if ($this->files->exists($targetPath) && ! $force) {
                    return false;
                }

                if (! $this->files->exists($stubPath)) {
                    return false;
                }

                $this->files->copy($stubPath, $targetPath);

                return true;
            });
        }

        // Generate components
        $componentsPath = resource_path('js/components/workspaces');
        $this->files->ensureDirectoryExists($componentsPath);

        $components = ['WorkspaceSwitcher', 'CreateWorkspaceModal', 'MembersList', 'InviteMemberModal', 'InvitationsList'];

        foreach ($components as $component) {
            $this->components->task("Generating {$component} component", function () use ($stubBase, $componentsPath, $component, $extension, $force) {
                $stubPath = "{$stubBase}/components/{$component}.{$extension}.stub";
                $targetPath = "{$componentsPath}/{$component}.{$extension}";

                if ($this->files->exists($targetPath) && ! $force) {
                    return false;
                }

                if (! $this->files->exists($stubPath)) {
                    return false;
                }

                $this->files->copy($stubPath, $targetPath);

                return true;
            });
        }
    }

    /**
     * Generate Livewire components.
     */
    protected function generateLivewireComponents(bool $force): void
    {
        $stubBase = __DIR__.'/../../stubs/ui/livewire';

        // Generate Livewire component classes
        $componentsPath = app_path('Livewire/Workspaces');
        $this->files->ensureDirectoryExists($componentsPath);

        $components = [
            'WorkspaceSwitcher',
            'CreateWorkspaceModal',
            'MembersList',
            'InviteMemberModal',
            'InvitationsList',
            'WorkspaceSettings',
        ];

        foreach ($components as $component) {
            $this->components->task("Generating {$component} component", function () use ($stubBase, $componentsPath, $component, $force) {
                $stubPath = "{$stubBase}/components/{$component}.php.stub";
                $targetPath = "{$componentsPath}/{$component}.php";

                if ($this->files->exists($targetPath) && ! $force) {
                    return false;
                }

                if (! $this->files->exists($stubPath)) {
                    return false;
                }

                $stub = $this->files->get($stubPath);
                $stub = $this->processStub($stub);

                $this->files->put($targetPath, $stub);

                return true;
            });
        }

        // Generate Livewire views
        $viewsPath = resource_path('views/livewire/workspaces');
        $this->files->ensureDirectoryExists($viewsPath);

        $views = [
            'workspace-switcher',
            'create-workspace-modal',
            'members-list',
            'invite-member-modal',
            'invitations-list',
            'workspace-settings',
        ];

        foreach ($views as $view) {
            $this->components->task("Generating {$view} view", function () use ($stubBase, $viewsPath, $view, $force) {
                $stubPath = "{$stubBase}/views/{$view}.blade.php.stub";
                $targetPath = "{$viewsPath}/{$view}.blade.php";

                if ($this->files->exists($targetPath) && ! $force) {
                    return false;
                }

                if (! $this->files->exists($stubPath)) {
                    return false;
                }

                $this->files->copy($stubPath, $targetPath);

                return true;
            });
        }
    }

    /**
     * Get the path to a stub file.
     */
    protected function getStubPath(string $stub): string
    {
        $customPath = base_path("stubs/workspaces/{$stub}");

        if ($this->files->exists($customPath)) {
            return $customPath;
        }

        return __DIR__.'/../../stubs/'.$stub;
    }

    /**
     * Show next steps after scaffolding.
     *
     * @param  array<string>  $components
     */
    protected function showNextSteps(array $components): void
    {
        $this->newLine();
        $this->components->info('Scaffolding complete!');
        $this->newLine();

        $step = 1;

        // Routes instructions
        if (in_array('routes', $components)) {
            $routeFile = in_array($this->uiStack, ['react', 'vue', 'livewire'])
                ? 'routes/web.php'
                : 'routes/api.php';

            $this->line("  <fg=yellow>{$step}.</> Include the routes file in your <fg=cyan>{$routeFile}</>:");
            $this->newLine();
            $this->line("     <fg=gray>require __DIR__.'/workspaces.php';</>");
            $this->newLine();
            $step++;
        }

        // Policy instructions
        if (in_array('policy', $components)) {
            $this->line("  <fg=yellow>{$step}.</> Register the policy in <fg=cyan>AppServiceProvider</> boot method:");
            $this->newLine();
            $this->line('     <fg=gray>use App\\Policies\\WorkspacePolicy;</>');
            $this->line('     <fg=gray>use Climactic\\Workspaces\\Models\\Workspace;</>');
            $this->line('     <fg=gray>use Illuminate\\Support\\Facades\\Gate;</>');
            $this->newLine();
            $this->line('     <fg=gray>Gate::policy(Workspace::class, WorkspacePolicy::class);</>');
            $this->newLine();
            $step++;
        }

        // UI-specific instructions
        if (in_array('ui', $components) && $this->uiStack) {
            $this->showUiNextSteps($step);
            $step++;
        }

        // Context-specific notes
        if ($this->config['context_resolver'] === 'route' && ! $this->uiStack) {
            $this->line("  <fg=yellow>{$step}.</> Routes are prefixed with <fg=cyan>/{$this->config['route_parameter']}/{{$this->config['route_parameter']}}</>");
            $this->newLine();
            $step++;
        }

        if (! $this->config['invitations_enabled']) {
            $this->components->warn('Invitation system is disabled. InvitationController was not generated.');
            $this->newLine();
        }

        $this->components->bulletList([
            'Review generated files and customize as needed',
            'Generated controllers use package Actions for business logic',
            'Documentation: <fg=blue>https://github.com/climactic/laravel-workspaces</>',
        ]);
    }

    /**
     * Show UI-specific next steps.
     */
    protected function showUiNextSteps(int $step): void
    {
        match ($this->uiStack) {
            'react' => $this->showReactNextSteps($step),
            'vue' => $this->showVueNextSteps($step),
            'livewire' => $this->showLivewireNextSteps($step),
            default => null,
        };
    }

    /**
     * Show React-specific next steps.
     */
    protected function showReactNextSteps(int $step): void
    {
        $runner = $this->getPackageRunnerCommand();

        $this->line("  <fg=yellow>{$step}.</> Install required Shadcn components:");
        $this->newLine();
        $this->line("     <fg=cyan>{$runner} shadcn@latest add button dialog dropdown-menu select avatar badge table card input label alert-dialog</>");
        $this->newLine();

        $this->line('  <fg=yellow>'.($step + 1).'.</> Update your <fg=cyan>HandleInertiaRequests</> middleware to share workspace data:');
        $this->newLine();
        $this->showInertiaMiddlewareSnippet();

        $this->line('  <fg=yellow>'.($step + 2).'.</> Add the WorkspaceSwitcher component to your layout:');
        $this->newLine();
        $this->line("     <fg=gray>import { WorkspaceSwitcher } from '@/components/workspaces/WorkspaceSwitcher';</>");
        $this->newLine();
    }

    /**
     * Show Vue-specific next steps.
     */
    protected function showVueNextSteps(int $step): void
    {
        $runner = $this->getPackageRunnerCommand();

        $this->line("  <fg=yellow>{$step}.</> Install required Shadcn-Vue components:");
        $this->newLine();
        $this->line("     <fg=cyan>{$runner} shadcn-vue@latest add button dialog dropdown-menu select avatar badge table card input label alert-dialog</>");
        $this->newLine();

        $this->line('  <fg=yellow>'.($step + 1).'.</> Update your <fg=cyan>HandleInertiaRequests</> middleware to share workspace data:');
        $this->newLine();
        $this->showInertiaMiddlewareSnippet();

        $this->line('  <fg=yellow>'.($step + 2).'.</> Add the WorkspaceSwitcher component to your layout:');
        $this->newLine();
        $this->line("     <fg=gray>import WorkspaceSwitcher from '@/components/workspaces/WorkspaceSwitcher.vue';</>");
        $this->newLine();
    }

    /**
     * Show Livewire-specific next steps.
     */
    protected function showLivewireNextSteps(int $step): void
    {
        $this->line("  <fg=yellow>{$step}.</> Flux UI components are built-in. No extra installation needed.");
        $this->newLine();

        $this->line('  <fg=yellow>'.($step + 1).'.</> Add the WorkspaceSwitcher component to your layout:');
        $this->newLine();
        $this->line('     <fg=gray><livewire:workspaces.workspace-switcher /></>');
        $this->newLine();

        $this->line('  <fg=yellow>'.($step + 2).'.</> Create view files for the routes (e.g., <fg=cyan>resources/views/workspaces/*.blade.php</>)');
        $this->newLine();
    }

    /**
     * Show the Inertia middleware code snippet.
     */
    protected function showInertiaMiddlewareSnippet(): void
    {
        $this->line('     <fg=gray>Add this to your share() method in app/Http/Middleware/HandleInertiaRequests.php:</>');
        $this->newLine();
        $this->line("     <fg=gray>'workspaces' => fn () => \$request->user()</>");
        $this->line("     <fg=gray>    ? \$request->user()->workspaces()->get(['id', 'name', 'slug'])</>");
        $this->line("     <fg=gray>    : [],</>");
        $this->line("     <fg=gray>'currentWorkspace' => fn () => \$request->user()?->currentWorkspace,</>");
        $this->newLine();
    }

    /**
     * Detect the package manager used in the project.
     *
     * Priority: bun > pnpm > npm
     */
    protected function detectPackageManager(): string
    {
        $basePath = base_path();

        if ($this->files->exists("{$basePath}/bun.lock") || $this->files->exists("{$basePath}/bun.lockb")) {
            return 'bun';
        }

        if ($this->files->exists("{$basePath}/pnpm-lock.yaml")) {
            return 'pnpm';
        }

        // Default to npm (package-lock.json or no lock file)
        return 'npm';
    }

    /**
     * Get the package runner command for the detected package manager.
     */
    protected function getPackageRunnerCommand(): string
    {
        return match ($this->detectPackageManager()) {
            'bun' => 'bunx',
            'pnpm' => 'pnpm dlx',
            'npm' => 'npx',
            default => 'npx',
        };
    }
}
