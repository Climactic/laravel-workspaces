<div align="center">

<img src=".github/assets/og.webp" alt="Laravel Workspaces" width="600">

# 🏢 Laravel Workspaces

A flexible multi-tenancy package for Laravel that adds workspace (team) functionality to your application. Supports workspace switching, member management, invitations, and role-based permissions.

[![Discord](https://img.shields.io/badge/Discord-Join%20Us-5865F2?style=for-the-badge&logo=discord&logoColor=white)](http://go.climactic.co/discord)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/climactic/laravel-workspaces.svg?style=for-the-badge)](https://packagist.org/packages/climactic/laravel-workspaces)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/climactic/laravel-workspaces/run-tests.yml?branch=main&label=tests&style=for-the-badge)](https://github.com/climactic/laravel-workspaces/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/climactic/laravel-workspaces/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=for-the-badge)](https://github.com/climactic/laravel-workspaces/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/climactic/laravel-workspaces.svg?style=for-the-badge)](https://packagist.org/packages/climactic/laravel-workspaces)
[![Sponsor on GitHub](https://img.shields.io/badge/Sponsor-GitHub-ea4aaa?style=for-the-badge&logo=github)](https://github.com/sponsors/climactic)
[![Support on Ko-fi](https://img.shields.io/badge/Support-Ko--fi-FF5E5B?style=for-the-badge&logo=ko-fi&logoColor=white)](https://ko-fi.com/ClimacticCo)

</div>

## Table of Contents

- ✨ [Features](#-features)
- 📦 [Installation](#-installation)
- ⚙️ [Configuration](#️-configuration)
- 🚀 [Usage](#-usage)
  - [Setup Your Model](#setup-your-model)
  - [Basic Usage](#basic-usage)
  - [Creating Workspaces](#creating-workspaces)
  - [Switching Workspaces](#switching-workspaces)
  - [Managing Members](#managing-members)
  - [Invitations](#invitations)
  - [Permissions](#permissions)
  - [Middleware](#middleware)
  - [Context Resolvers](#context-resolvers)
  - [Events](#events)
- 🎨 [UI Scaffolding](#-ui-scaffolding)
- 📖 [Documentation](#-documentation)
- 🧪 [Testing](#-testing)
- 📝 [Changelog](#-changelog)
- 🤝 [Contributing](#-contributing)
- 🔒 [Security Vulnerabilities](#-security-vulnerabilities)
- 💖 [Support This Project](#-support-this-project)
- 📦 [Other Packages](#-other-packages)
- ⭐ [Star History](#-star-history)
- 📄 [License](#-license)
- ⚠️ [Disclaimer](#️-disclaimer)

## ✨ Features

- 🏢 **Workspace Management** - Create, update, and delete workspaces with soft delete support
- 👥 **Member Management** - Add, remove, and update member roles within workspaces
- 📧 **Invitation System** - Email-based invitations with expiration and token-based acceptance
- 🔐 **Role-Based Permissions** - Flexible permission system with wildcard support
- 🔍 **Context Resolution** - Multiple strategies for resolving current workspace (auth, subdomain, header, session, route)
- 🛡️ **Middleware** - Built-in middleware for workspace access control
- 📢 **Events** - Comprehensive events for all workspace operations
- 🏗️ **Multi-Tenancy Ready** - Scope models to workspaces with automatic filtering

## 📦 Installation

You can install the package via composer:

```bash
composer require climactic/laravel-workspaces
```

Run the install command to publish config and migrations:

```bash
php artisan workspaces:install
php artisan migrate
```

## ⚙️ Configuration

The configuration file will be published to `config/workspaces.php`. Key options include:

```php
return [
    // Models
    'models' => [
        'workspace' => \Climactic\Workspaces\Models\Workspace::class,
        'membership' => \Climactic\Workspaces\Models\WorkspaceMembership::class,
        'invitation' => \Climactic\Workspaces\Models\WorkspaceInvitation::class,
    ],

    // User model
    'user_model' => \App\Models\User::class,

    // Roles with permissions
    'roles' => [
        'owner' => ['permissions' => ['*']],
        'admin' => ['permissions' => ['workspace.view', 'workspace.update', 'members.*']],
        'member' => ['permissions' => ['workspace.view', 'members.view']],
    ],

    // Auto-create workspace on user registration
    'auto_create_on_registration' => [
        'enabled' => true,
        'name_from' => 'name',
        'name_suffix' => "'s Workspace",
    ],
];
```

## 🚀 Usage

### Setup Your Model

Add the `HasWorkspaces` trait to your User model:

```php
use Climactic\Workspaces\Concerns\HasWorkspaces;

class User extends Authenticatable
{
    use HasWorkspaces;
}
```

### Basic Usage

```php
// Create a workspace
$workspace = $user->createWorkspace('My Team');

// Switch to a workspace
$user->switchWorkspace($workspace);

// Get current workspace
$current = $user->currentWorkspace;

// Check membership
$user->belongsToWorkspace($workspace); // true/false

// Check permissions
$user->hasWorkspacePermission($workspace, 'members.invite'); // true/false
```

### Creating Workspaces

```php
// Simple creation
$workspace = $user->createWorkspace('Engineering Team');

// With description
$workspace = $user->createWorkspace('Engineering Team', [
    'description' => 'Our engineering department',
]);

// Using the action directly
use Climactic\Workspaces\Actions\CreateWorkspace;

$workspace = app(CreateWorkspace::class)->execute(
    data: ['name' => 'My Workspace', 'description' => 'A great workspace'],
    owner: $user,
    setAsCurrent: true
);
```

### Switching Workspaces

```php
// Switch workspace
$user->switchWorkspace($workspace);

// Check if workspace is current
$user->isCurrentWorkspace($workspace); // true/false

// Get current workspace
$current = $user->currentWorkspace;

// Get current workspace ID
$id = $user->current_workspace_id;
```

### Managing Members

```php
// Add a member
$workspace->addMember($user, 'member');

// Update member role
$workspace->updateMemberRole($user, 'admin');

// Remove a member
$workspace->removeMember($user);

// Get member's role
$role = $user->workspaceRole($workspace); // 'owner', 'admin', etc.

// Check role
$user->hasWorkspaceRole($workspace, 'admin'); // true/false
$user->hasWorkspaceRole($workspace, ['admin', 'owner']); // true if any match
```

### Invitations

```php
use Climactic\Workspaces\Actions\CreateInvitation;

// Create an invitation
$invitation = app(CreateInvitation::class)->execute(
    workspace: $workspace,
    email: 'newuser@example.com',
    role: 'member',
    invitedBy: $currentUser
);

// Accept an invitation
use Climactic\Workspaces\Actions\AcceptInvitation;

app(AcceptInvitation::class)->execute($invitation, $user);

// Decline an invitation
use Climactic\Workspaces\Actions\DeclineInvitation;

app(DeclineInvitation::class)->execute($invitation);
```

### Permissions

```php
// Check permission
$user->hasWorkspacePermission($workspace, 'members.invite');

// Get all permissions
$permissions = $user->workspacePermissions($workspace);

// Wildcard permissions work automatically
// If role has 'members.*', it grants 'members.invite', 'members.remove', etc.
```

### Middleware

```php
// In routes/web.php
Route::middleware(['auth', 'workspace'])->group(function () {
    // Routes that need workspace context
});

Route::middleware(['auth', 'workspace.access'])->group(function () {
    // Routes that require workspace membership
});

Route::middleware(['auth', 'workspace.role:admin,owner'])->group(function () {
    // Routes that require specific roles
});
```

### Context Resolvers

The package supports multiple strategies for resolving the current workspace:

```php
// In config/workspaces.php
'context' => [
    'resolvers' => [
        \Climactic\Workspaces\ContextResolvers\AuthUserResolver::class,
        \Climactic\Workspaces\ContextResolvers\SubdomainResolver::class,
        \Climactic\Workspaces\ContextResolvers\RouteParameterResolver::class,
        \Climactic\Workspaces\ContextResolvers\HeaderResolver::class,
        \Climactic\Workspaces\ContextResolvers\SessionResolver::class,
    ],
],
```

### Events

The package fires events for all major operations:

| Event | Description |
|-------|-------------|
| `WorkspaceCreated` | Fired when a workspace is created |
| `WorkspaceDeleted` | Fired when a workspace is deleted |
| `WorkspaceSwitched` | Fired when a user switches workspaces |
| `MemberAdded` | Fired when a member is added |
| `MemberRemoved` | Fired when a member is removed |
| `MemberRoleUpdated` | Fired when a member's role is updated |
| `InvitationCreated` | Fired when an invitation is created |
| `InvitationAccepted` | Fired when an invitation is accepted |
| `InvitationDeclined` | Fired when an invitation is declined |
| `InvitationCancelled` | Fired when an invitation is cancelled |

## 🎨 UI Scaffolding

The package includes pre-built UI components for workspace management, supporting three UI stacks:

| Stack | Description |
|-------|-------------|
| **React + Shadcn** | Inertia.js pages and components using Shadcn UI |
| **Vue + Shadcn** | Inertia.js pages and components using Shadcn Vue |
| **Livewire + Flux UI** | Livewire components with Flux UI |

### Quick Start

```bash
# Interactive scaffolding (recommended)
php artisan workspaces:scaffold

# Direct UI generation
php artisan workspaces:scaffold --ui=react
php artisan workspaces:scaffold --ui=vue
php artisan workspaces:scaffold --ui=livewire
```

### What's Included

- **Workspace Switcher** - Dropdown to switch between workspaces
- **Create Workspace** - Modal/page to create new workspaces
- **Members Management** - List, update roles, and remove members
- **Invitations Management** - Send, cancel, and resend invitations
- **Settings Pages** - Workspace settings and danger zone

### After Scaffolding

**For React:**
```bash
bunx shadcn@latest add button dialog dropdown-menu select avatar badge table card input label alert-dialog
```

**For Vue:**
```bash
npx shadcn-vue@latest add button dialog dropdown-menu select avatar badge table card input label alert-dialog
```

**For Livewire:** Flux UI components are included by default.

For detailed documentation, see [UI Scaffolding](docs/ui-scaffolding.md).

## 📖 Documentation

For detailed documentation, see the [docs](docs/) folder:

- [Installation](docs/installation.md)
- [Workspaces](docs/workspaces.md)
- [Members](docs/members.md)
- [Invitations](docs/invitations.md)
- [Permissions](docs/permissions.md)
- [Middleware](docs/middleware.md)
- [Context Resolvers](docs/context-resolvers.md)
- [Events](docs/events.md)
- [Actions](docs/actions.md)
- [UI Scaffolding](docs/ui-scaffolding.md)

## 🧪 Testing

```bash
composer test
```

## 📝 Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## 🤝 Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.
You can also join our Discord server to discuss ideas and get help: [Discord Invite](http://go.climactic.co/discord).

## 🔒 Security Vulnerabilities

Please report security vulnerabilities to [security@climactic.co](mailto:security@climactic.co).

## 💖 Support This Project

Laravel Workspaces is free and open source, built and maintained with care. If this package has saved you development time or helped power your application, please consider supporting its continued development.

<a href="https://github.com/sponsors/climactic">
    <img src="https://img.shields.io/badge/Sponsor%20on-GitHub-ea4aaa?style=for-the-badge&logo=github" alt="Sponsor on GitHub" />
</a>
<a href="https://ko-fi.com/ClimacticCo">
    <img src="https://img.shields.io/badge/Support%20on-Ko--fi-FF5E5B?style=for-the-badge&logo=ko-fi&logoColor=white" alt="Support on Ko-fi" />
</a>

### 🏆 Sponsors

<!-- sponsors -->
*Your logo here* — Become a sponsor and get your logo featured in this README and on our website.
<!-- sponsors -->

**Interested in title sponsorship?** Contact us at [sponsors@climactic.co](mailto:sponsors@climactic.co) for premium placement and recognition.

## 📦 Other Packages

Check out our other Laravel packages:

| Package | Description |
|---------|-------------|
| [Laravel Credits](https://github.com/Climactic/laravel-credits) | A ledger-based Laravel package for managing credit-based systems in your application. Perfect for virtual currencies, reward points, or any credit-based feature. |

## ⭐ Star History

<a href="https://star-history.com/#climactic/laravel-workspaces&Date">
 <picture>
   <source media="(prefers-color-scheme: dark)" srcset="https://api.star-history.com/svg?repos=climactic/laravel-workspaces&type=Date&theme=dark" />
   <source media="(prefers-color-scheme: light)" srcset="https://api.star-history.com/svg?repos=climactic/laravel-workspaces&type=Date" />
   <img alt="Star History Chart" src="https://api.star-history.com/svg?repos=climactic/laravel-workspaces&type=Date" />
 </picture>
</a>

## 📄 License

The AGPL-3.0 License. Please see [License File](LICENSE) for more information.

## ⚠️ Disclaimer

This package is not affiliated with Laravel. It's for Laravel but is not by Laravel. Laravel is a trademark of Taylor Otwell.
