# UI Scaffolding

Laravel Workspaces includes pre-built UI components for workspace management. The scaffolding system supports three UI stacks and generates all the necessary pages, components, controllers, and routes.

## Supported UI Stacks

| Stack | Frontend | Components Library |
|-------|----------|-------------------|
| **React** | Inertia.js + React | [Shadcn UI](https://ui.shadcn.com/) |
| **Vue** | Inertia.js + Vue 3 | [Shadcn Vue](https://www.shadcn-vue.com/) |
| **Livewire** | Livewire 3 | [Flux UI](https://fluxui.dev/) |

## Quick Start

### Interactive Scaffolding

The recommended approach is to use the interactive scaffold command:

```bash
php artisan workspaces:scaffold
```

This will prompt you to select which components to generate, including UI components.

### Direct UI Generation

You can also generate UI components directly:

```bash
# React + Shadcn
php artisan workspaces:scaffold --ui=react

# Vue + Shadcn
php artisan workspaces:scaffold --ui=vue

# Livewire + Flux UI
php artisan workspaces:scaffold --ui=livewire
```

### Generate Everything

To generate all scaffolding including UI:

```bash
php artisan workspaces:scaffold --all --ui=react
```

## What Gets Generated

### For React/Vue (Inertia)

| Type | Location | Description |
|------|----------|-------------|
| **Pages** | `resources/js/pages/workspaces/` | Full-page Inertia components |
| **Components** | `resources/js/components/workspaces/` | Reusable UI components |
| **Controllers** | `app/Http/Controllers/` | Inertia-compatible controllers |
| **Routes** | `routes/workspaces.php` | Web routes for all workspace operations |
| **Middleware** | `app/Http/Middleware/HandleInertiaRequests.php` | Shares workspace data to all pages |

### For Livewire

| Type | Location | Description |
|------|----------|-------------|
| **Components** | `app/Livewire/Workspaces/` | Livewire component classes |
| **Views** | `resources/views/livewire/workspaces/` | Blade component views |
| **Routes** | `routes/workspaces.php` | Web routes for workspace pages |

## Generated Components

### Pages

| Page | Route | Description |
|------|-------|-------------|
| **Index** | `/workspaces` | List all user's workspaces |
| **Create** | `/workspaces/create` | Create a new workspace |
| **Settings** | `/settings/workspace` | Edit workspace name/description |
| **Members** | `/settings/members` | Manage workspace members |
| **Invitations** | `/settings/invitations` | Manage pending invitations |

### Components

| Component | Description |
|-----------|-------------|
| **WorkspaceSwitcher** | Dropdown menu to switch between workspaces |
| **CreateWorkspaceModal** | Modal dialog to create a new workspace |
| **MembersList** | Table of members with role management |
| **InviteMemberModal** | Modal to invite new members via email |
| **InvitationsList** | Table of pending invitations with actions |

## Post-Scaffolding Setup

### 1. Install Shadcn Components (React/Vue only)

**For React:**
```bash
npx shadcn@latest add button dialog dropdown-menu select avatar badge table card input label alert-dialog
```

**For Vue:**
```bash
npx shadcn-vue@latest add button dialog dropdown-menu select avatar badge table card input label alert-dialog
```

**For Livewire:** No additional installation needed - Flux UI components are included.

### 2. Include Routes

Add the workspace routes to your `routes/web.php`:

```php
// At the end of routes/web.php
require __DIR__.'/workspaces.php';
```

### 3. Update HandleInertiaRequests (Inertia only)

If you already have a `HandleInertiaRequests` middleware, merge the workspace sharing logic:

```php
public function share(Request $request): array
{
    return array_merge(parent::share($request), [
        // Your existing shares...

        // Add workspace data
        'workspaces' => fn () => $request->user()
            ? $request->user()->workspaces()->get(['id', 'name', 'slug'])
            : [],
        'currentWorkspace' => fn () => $request->user()?->currentWorkspace,
        'roles' => fn () => config('workspaces.roles'),
    ]);
}
```

### 4. Register Policies (Optional)

If you're using authorization policies:

```php
// In AppServiceProvider or AuthServiceProvider
use App\Models\Workspace;
use App\Policies\WorkspacePolicy;

Gate::policy(Workspace::class, WorkspacePolicy::class);
```

## Route Structure

### Public Routes

```
GET  /workspaces              # List workspaces
GET  /workspaces/create       # Create workspace form
POST /workspaces              # Store new workspace
POST /workspaces/{id}/switch  # Switch to workspace
```

### Workspace Settings (requires workspace context)

```
GET    /settings/workspace       # Edit workspace
PATCH  /settings/workspace       # Update workspace
DELETE /settings/workspace       # Delete workspace
GET    /settings/members         # List members
PATCH  /settings/members/{id}    # Update member role
DELETE /settings/members/{id}    # Remove member
POST   /settings/members/leave   # Leave workspace
GET    /settings/invitations     # List invitations
POST   /settings/invitations     # Create invitation
DELETE /settings/invitations/{id} # Cancel invitation
POST   /settings/invitations/{id}/resend # Resend invitation
```

### Invitation Acceptance (public with token)

```
GET  /invitations/{token}         # View invitation
POST /invitations/{token}/accept  # Accept invitation
POST /invitations/{token}/decline # Decline invitation
```

## Using the WorkspaceSwitcher Component

### React

```tsx
import { WorkspaceSwitcher } from '@/components/workspaces/WorkspaceSwitcher';

export default function Layout({ children }) {
    return (
        <div>
            <nav>
                <WorkspaceSwitcher />
            </nav>
            <main>{children}</main>
        </div>
    );
}
```

### Vue

```vue
<template>
    <div>
        <nav>
            <WorkspaceSwitcher />
        </nav>
        <main>
            <slot />
        </main>
    </div>
</template>

<script setup>
import WorkspaceSwitcher from '@/components/workspaces/WorkspaceSwitcher.vue';
</script>
```

### Livewire

```blade
<nav>
    <livewire:workspaces.workspace-switcher />
</nav>
```

## Manual Publishing

You can also publish components manually without the scaffold command:

```bash
# React UI
php artisan vendor:publish --tag=workspaces-ui-react

# Vue UI
php artisan vendor:publish --tag=workspaces-ui-vue

# Livewire UI
php artisan vendor:publish --tag=workspaces-ui-livewire

# Inertia Controllers
php artisan vendor:publish --tag=workspaces-controllers-inertia

# Inertia Routes
php artisan vendor:publish --tag=workspaces-routes-inertia

# Livewire Routes
php artisan vendor:publish --tag=workspaces-routes-livewire

# HandleInertiaRequests Middleware
php artisan vendor:publish --tag=workspaces-middleware-inertia
```

## Configuration

The UI scaffolding respects the following configuration options in `config/workspaces.php`:

```php
'ui' => [
    // Default UI stack (can be set via env)
    'stack' => env('WORKSPACES_UI_STACK'),

    // Route prefix for settings pages
    'settings_prefix' => 'settings',

    // Share workspace data via Inertia
    'share_workspaces' => true,
],
```

## Customization

All generated files are placed in your application directory and can be freely modified. The components are designed to be starting points that you can customize to match your application's design.

### Styling

- **React/Vue:** Components use Tailwind CSS classes and Shadcn UI conventions
- **Livewire:** Components use Flux UI's styling system

### Extending Functionality

The generated controllers use the package's action classes, which you can customize:

```php
'actions' => [
    'create_workspace' => \App\Actions\CustomCreateWorkspace::class,
    'add_member' => \App\Actions\CustomAddMember::class,
    // ...
],
```

## Troubleshooting

### Components not styling correctly

Ensure you've installed the required Shadcn components. The scaffolder will display the exact command after generation.

### Routes not working

1. Check that you've included the routes file in `routes/web.php`
2. Verify the `workspace` middleware is registered (done automatically by the package)
3. Ensure you have the `HasWorkspaces` trait on your User model

### Workspace data not available in components

For Inertia apps, ensure your `HandleInertiaRequests` middleware is sharing the workspace data. The scaffolder generates this middleware, but if you have an existing one, you'll need to merge the changes.
