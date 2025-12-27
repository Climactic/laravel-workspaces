<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

use function Laravel\Prompts\multiselect;

class ScaffoldCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workspaces:scaffold
                            {--force : Overwrite existing files}
                            {--all : Generate all scaffolding without prompts}';

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

        $force = $this->option('force');

        // Generate selected components
        foreach ($components as $component) {
            match ($component) {
                'controllers' => $this->generateControllers($force),
                'routes' => $this->generateRoutes($force),
                'policy' => $this->generatePolicy($force),
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
        // Determine available options based on config
        $options = [
            'controllers' => 'Controllers (Workspace, Member'.($this->config['invitations_enabled'] ? ', Invitation' : '').')',
            'routes' => 'Routes file',
            'policy' => 'Workspace Policy',
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
     * Generate controller files.
     */
    protected function generateControllers(bool $force): void
    {
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
            $this->components->task("Generating {$controller}", function () use ($controller, $controllerPath, $force) {
                $stubPath = $this->getStubPath("controllers/{$controller}.stub");
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
            $stubPath = $this->getStubPath('routes/workspaces.stub');
            $targetPath = base_path('routes/workspaces.php');

            if ($this->files->exists($targetPath) && ! $force) {
                return false;
            }

            $stub = $this->files->get($stubPath);
            $stub = $this->processStub($stub);

            // Remove invitation routes if disabled
            if (! $this->config['invitations_enabled']) {
                $stub = $this->removeInvitationRoutes($stub);
            }

            // Adjust routes for route parameter context
            if ($this->config['context_resolver'] === 'route') {
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

        if (in_array('routes', $components)) {
            $this->line("  <fg=yellow>{$step}.</> Include the routes file in your <fg=cyan>routes/api.php</>:");
            $this->newLine();
            $this->line("     <fg=gray>require __DIR__.'/workspaces.php';</>");
            $this->newLine();
            $step++;
        }

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

        // Context-specific notes
        if ($this->config['context_resolver'] === 'route') {
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
}
