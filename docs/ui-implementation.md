# UI Implementation Guide

This guide covers how to build frontend interfaces for Laravel Workspaces using React, Vue, or Livewire.

## API Endpoints

The scaffolded controllers provide these API endpoints:

### Workspace Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/workspaces` | List user's workspaces |
| `POST` | `/api/workspaces` | Create a new workspace |
| `GET` | `/api/workspaces/{workspace}` | Get workspace details |
| `PATCH` | `/api/workspaces/{workspace}` | Update workspace |
| `DELETE` | `/api/workspaces/{workspace}` | Delete workspace |
| `POST` | `/api/workspaces/{workspace}/switch` | Switch to workspace |

### Member Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/workspaces/{workspace}/members` | List workspace members |
| `PATCH` | `/api/workspaces/{workspace}/members/{member}` | Update member role |
| `DELETE` | `/api/workspaces/{workspace}/members/{member}` | Remove member |
| `POST` | `/api/workspaces/{workspace}/leave` | Leave workspace |

### Invitation Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/invitations` | List user's pending invitations |
| `GET` | `/api/workspaces/{workspace}/invitations` | List workspace invitations |
| `POST` | `/api/workspaces/{workspace}/invitations` | Send invitation |
| `DELETE` | `/api/workspaces/{workspace}/invitations/{invitation}` | Cancel invitation |
| `POST` | `/api/workspaces/{workspace}/invitations/{invitation}/resend` | Resend invitation |
| `GET` | `/api/invitations/{token}` | View invitation by token |
| `POST` | `/api/invitations/{token}/accept` | Accept invitation |
| `POST` | `/api/invitations/{token}/decline` | Decline invitation |

## Data Structures

### Workspace

```typescript
interface Workspace {
    id: number | string;
    name: string;
    slug: string;
    description?: string;
    personal: boolean;
    owner_id: number | string;
    owner?: User;
    created_at: string;
    updated_at: string;
    pivot?: {
        role: string;
        permissions?: string[];
    };
}
```

### Member

```typescript
interface Member {
    id: number | string;
    name: string;
    email: string;
    pivot: {
        role: string;
        permissions?: string[];
        joined_at: string;
    };
}
```

### Invitation

```typescript
interface Invitation {
    id: number | string;
    email: string;
    role: string;
    token: string;
    expires_at: string;
    created_at: string;
    workspace?: Workspace;
    inviter?: User;
}
```

### Roles Configuration

```typescript
interface Role {
    name: string;
    permissions: string[];
}

type RolesConfig = Record<string, Role>;
// Example: { owner: { name: 'Owner', permissions: ['*'] }, admin: { name: 'Admin', permissions: [...] } }
```

## React Implementation (Inertia)

### Sharing Data via HandleInertiaRequests

```php
// app/Http/Middleware/HandleInertiaRequests.php
public function share(Request $request): array
{
    return [
        ...parent::share($request),
        'auth' => [
            'user' => $request->user(),
        ],
        'workspaces' => fn () => $request->user()
            ?->workspaces()
            ->get(['workspaces.id', 'workspaces.name', 'workspaces.slug']),
        'currentWorkspace' => fn () => $request->user()?->currentWorkspace,
        'roles' => fn () => config('workspaces.roles'),
        'flash' => [
            'success' => fn () => $request->session()->get('success'),
            'error' => fn () => $request->session()->get('error'),
        ],
    ];
}
```

### Workspace Switcher Component

```tsx
// resources/js/components/workspace-switcher.tsx
import { router, usePage } from '@inertiajs/react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';

interface Workspace {
    id: number;
    name: string;
    slug: string;
}

export function WorkspaceSwitcher() {
    const { workspaces, currentWorkspace } = usePage<{
        workspaces: Workspace[];
        currentWorkspace: Workspace | null;
    }>().props;

    const switchWorkspace = (workspace: Workspace) => {
        router.post(`/api/workspaces/${workspace.id}/switch`);
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline">
                    {currentWorkspace?.name ?? 'Select Workspace'}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent>
                {workspaces.map((workspace) => (
                    <DropdownMenuItem
                        key={workspace.id}
                        onClick={() => switchWorkspace(workspace)}
                    >
                        {workspace.name}
                        {currentWorkspace?.id === workspace.id && ' ✓'}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
```

### Create Workspace Form

```tsx
// resources/js/components/create-workspace-form.tsx
import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export function CreateWorkspaceForm({ onSuccess }: { onSuccess?: () => void }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/api/workspaces', {
            onSuccess: () => {
                reset();
                onSuccess?.();
            },
        });
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div>
                <Label htmlFor="name">Workspace Name</Label>
                <Input
                    id="name"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    placeholder="My Workspace"
                />
                {errors.name && (
                    <p className="text-sm text-red-500">{errors.name}</p>
                )}
            </div>
            <Button type="submit" disabled={processing}>
                {processing ? 'Creating...' : 'Create Workspace'}
            </Button>
        </form>
    );
}
```

### Members List Component

```tsx
// resources/js/components/members-list.tsx
import { router } from '@inertiajs/react';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Button } from '@/components/ui/button';

interface Member {
    id: number;
    name: string;
    email: string;
    pivot: { role: string };
}

interface Props {
    workspaceId: number;
    members: Member[];
    roles: Record<string, { name: string }>;
    canManage: boolean;
}

export function MembersList({ workspaceId, members, roles, canManage }: Props) {
    const updateRole = (memberId: number, role: string) => {
        router.patch(`/api/workspaces/${workspaceId}/members/${memberId}`, { role });
    };

    const removeMember = (memberId: number) => {
        if (confirm('Remove this member?')) {
            router.delete(`/api/workspaces/${workspaceId}/members/${memberId}`);
        }
    };

    // Filter out owner role for assignable roles
    const assignableRoles = Object.entries(roles).filter(([key]) => key !== 'owner');

    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Name</TableHead>
                    <TableHead>Email</TableHead>
                    <TableHead>Role</TableHead>
                    {canManage && <TableHead>Actions</TableHead>}
                </TableRow>
            </TableHeader>
            <TableBody>
                {members.map((member) => (
                    <TableRow key={member.id}>
                        <TableCell>{member.name}</TableCell>
                        <TableCell>{member.email}</TableCell>
                        <TableCell>
                            {canManage && member.pivot.role !== 'owner' ? (
                                <Select
                                    value={member.pivot.role}
                                    onValueChange={(role) => updateRole(member.id, role)}
                                >
                                    <SelectTrigger className="w-32">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {assignableRoles.map(([key, role]) => (
                                            <SelectItem key={key} value={key}>
                                                {role.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            ) : (
                                roles[member.pivot.role]?.name ?? member.pivot.role
                            )}
                        </TableCell>
                        {canManage && (
                            <TableCell>
                                {member.pivot.role !== 'owner' && (
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        onClick={() => removeMember(member.id)}
                                    >
                                        Remove
                                    </Button>
                                )}
                            </TableCell>
                        )}
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
```

## Vue Implementation (Inertia)

### Workspace Switcher Component

```vue
<!-- resources/js/components/WorkspaceSwitcher.vue -->
<script setup lang="ts">
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';

interface Workspace {
    id: number;
    name: string;
    slug: string;
}

const page = usePage<{
    workspaces: Workspace[];
    currentWorkspace: Workspace | null;
}>();

const workspaces = computed(() => page.props.workspaces ?? []);
const currentWorkspace = computed(() => page.props.currentWorkspace);

const switchWorkspace = (workspace: Workspace) => {
    router.post(`/api/workspaces/${workspace.id}/switch`);
};
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="outline">
                {{ currentWorkspace?.name ?? 'Select Workspace' }}
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent>
            <DropdownMenuItem
                v-for="workspace in workspaces"
                :key="workspace.id"
                @click="switchWorkspace(workspace)"
            >
                {{ workspace.name }}
                <span v-if="currentWorkspace?.id === workspace.id"> ✓</span>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
```

### Create Workspace Form

```vue
<!-- resources/js/components/CreateWorkspaceForm.vue -->
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const emit = defineEmits<{
    success: [];
}>();

const form = useForm({
    name: '',
});

const submit = () => {
    form.post('/api/workspaces', {
        onSuccess: () => {
            form.reset();
            emit('success');
        },
    });
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-4">
        <div>
            <Label for="name">Workspace Name</Label>
            <Input
                id="name"
                v-model="form.name"
                placeholder="My Workspace"
            />
            <p v-if="form.errors.name" class="text-sm text-red-500">
                {{ form.errors.name }}
            </p>
        </div>
        <Button type="submit" :disabled="form.processing">
            {{ form.processing ? 'Creating...' : 'Create Workspace' }}
        </Button>
    </form>
</template>
```

## Livewire Implementation

### Workspace Switcher Component

```php
// app/Livewire/WorkspaceSwitcher.php
<?php

namespace App\Livewire;

use Livewire\Component;

class WorkspaceSwitcher extends Component
{
    public function switchWorkspace(int $workspaceId): void
    {
        $workspace = auth()->user()->workspaces()->findOrFail($workspaceId);
        auth()->user()->switchWorkspace($workspace);

        $this->redirect(request()->header('Referer', '/'), navigate: true);
    }

    public function render()
    {
        return view('livewire.workspace-switcher', [
            'workspaces' => auth()->user()->workspaces,
            'currentWorkspace' => auth()->user()->currentWorkspace,
        ]);
    }
}
```

```blade
<!-- resources/views/livewire/workspace-switcher.blade.php -->
<div>
    <flux:dropdown>
        <flux:button variant="outline">
            {{ $currentWorkspace?->name ?? 'Select Workspace' }}
        </flux:button>

        <flux:menu>
            @foreach ($workspaces as $workspace)
                <flux:menu.item wire:click="switchWorkspace({{ $workspace->id }})">
                    {{ $workspace->name }}
                    @if ($currentWorkspace?->id === $workspace->id)
                        <flux:icon name="check" class="ml-2 h-4 w-4" />
                    @endif
                </flux:menu.item>
            @endforeach
        </flux:menu>
    </flux:dropdown>
</div>
```

### Invite Member Component

```php
// app/Livewire/InviteMember.php
<?php

namespace App\Livewire;

use Climactic\Workspaces\Actions\CreateInvitation;
use Livewire\Component;

class InviteMember extends Component
{
    public string $email = '';
    public string $role = 'member';

    protected function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'role' => ['required', 'in:' . implode(',', array_keys(config('workspaces.roles')))],
        ];
    }

    public function invite(CreateInvitation $createInvitation): void
    {
        $this->validate();

        $workspace = auth()->user()->currentWorkspace;

        $createInvitation->execute(
            workspace: $workspace,
            email: $this->email,
            role: $this->role,
            invitedBy: auth()->user(),
        );

        $this->reset(['email', 'role']);
        session()->flash('success', "Invitation sent to {$this->email}");
        $this->dispatch('invitation-sent');
    }

    public function render()
    {
        return view('livewire.invite-member', [
            'roles' => collect(config('workspaces.roles'))
                ->except('owner')
                ->all(),
        ]);
    }
}
```

```blade
<!-- resources/views/livewire/invite-member.blade.php -->
<form wire:submit="invite" class="space-y-4">
    <div>
        <flux:input
            wire:model="email"
            type="email"
            label="Email Address"
            placeholder="colleague@example.com"
        />
        @error('email')
            <p class="text-sm text-red-500">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <flux:select wire:model="role" label="Role">
            @foreach ($roles as $key => $role)
                <flux:option value="{{ $key }}">{{ $role['name'] }}</flux:option>
            @endforeach
        </flux:select>
    </div>

    <flux:button type="submit">Send Invitation</flux:button>
</form>
```

## Authorization

Use the `HasWorkspaces` trait methods to check permissions:

```php
// In controllers or Livewire components
$user = auth()->user();
$workspace = $user->currentWorkspace;

// Check if user has a specific permission
if ($user->hasWorkspacePermission($workspace, 'members.update-role')) {
    // Can update member roles
}

// Check if user is owner
if ($user->isWorkspaceOwner($workspace)) {
    // Is the workspace owner
}

// Get user's role in workspace
$role = $user->workspaceRole($workspace); // 'owner', 'admin', 'member', etc.
```

## Best Practices

1. **Always validate roles server-side** - Never trust client-submitted role values
2. **Use Form Requests** - The scaffolded Form Requests handle validation and authorization
3. **Check permissions before rendering UI** - Hide actions users can't perform
4. **Handle loading states** - Show loading indicators during async operations
5. **Provide feedback** - Use flash messages for success/error states

## Recommended Packages

### React/Vue (Inertia)
- [Shadcn UI](https://ui.shadcn.com/) (React) or [Shadcn Vue](https://www.shadcn-vue.com/) (Vue)
- [Laravel Wayfinder](https://github.com/laravel/wayfinder) for type-safe routing

### Livewire
- [Flux UI](https://fluxui.dev/) for components
- [Wire Elements Modal](https://github.com/wire-elements/modal) for modals
