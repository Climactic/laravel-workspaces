# UI Implementation Guide

This guide covers implementing Laravel Workspaces in your application's UI for both **Laravel Inertia** (Vue/React) and **Livewire** applications.

## Prerequisites

Before implementing the UI, ensure you have:

1. Installed the package: `composer require climactic/laravel-workspaces`
2. Run migrations: `php artisan migrate`
3. Added the `HasWorkspaces` trait to your User model:

```php
use Climactic\Workspaces\Concerns\HasWorkspaces;

class User extends Authenticatable
{
    use HasWorkspaces;
}
```

4. Published the config (optional): `php artisan vendor:publish --tag=workspaces-config`

---

## Backend Setup

### Routes

```php
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\WorkspaceMemberController;
use App\Http\Controllers\WorkspaceInvitationController;

Route::middleware(['auth', 'workspace'])->group(function () {
    // Workspace CRUD
    Route::resource('workspaces', WorkspaceController::class);
    Route::post('workspaces/{workspace}/switch', [WorkspaceController::class, 'switch'])
        ->name('workspaces.switch');

    // Members (require workspace access)
    Route::middleware(['workspace.access'])->group(function () {
        Route::get('workspaces/{workspace}/members', [WorkspaceMemberController::class, 'index']);

        // Admin/Owner only routes
        Route::middleware(['workspace.role:admin,owner'])->group(function () {
            Route::put('workspaces/{workspace}/members/{user}', [WorkspaceMemberController::class, 'update']);
            Route::delete('workspaces/{workspace}/members/{user}', [WorkspaceMemberController::class, 'destroy']);
            Route::post('workspaces/{workspace}/transfer', [WorkspaceMemberController::class, 'transfer']);
        });
    });

    // Invitations
    Route::middleware(['workspace.access', 'workspace.role:admin,owner'])->group(function () {
        Route::post('workspaces/{workspace}/invitations', [WorkspaceInvitationController::class, 'store']);
        Route::delete('invitations/{invitation}', [WorkspaceInvitationController::class, 'destroy']);
    });
});

// Public invitation routes (for invitees)
Route::get('invitations/{token}', [WorkspaceInvitationController::class, 'show'])
    ->name('invitations.show');
Route::post('invitations/{token}/accept', [WorkspaceInvitationController::class, 'accept'])
    ->middleware('auth')
    ->name('invitations.accept');
Route::post('invitations/{token}/decline', [WorkspaceInvitationController::class, 'decline'])
    ->name('invitations.decline');
```

### Controllers

#### WorkspaceController

```php
use Climactic\Workspaces\Facades\Workspaces;

class WorkspaceController extends Controller
{
    public function index()
    {
        return inertia('Workspaces/Index', [
            'workspaces' => auth()->user()->workspaces,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $workspace = Workspaces::createWorkspace($validated, auth()->user());

        return redirect()->route('workspaces.show', $workspace);
    }

    public function update(Request $request, Workspace $workspace)
    {
        $this->authorize('update', $workspace);

        $workspace->update($request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]));

        return back();
    }

    public function destroy(Workspace $workspace)
    {
        $this->authorize('delete', $workspace);

        Workspaces::deleteWorkspace($workspace);

        return redirect()->route('workspaces.index');
    }

    public function switch(Workspace $workspace)
    {
        auth()->user()->switchWorkspace($workspace);

        return back();
    }
}
```

#### WorkspaceMemberController

```php
use Climactic\Workspaces\Facades\Workspaces;

class WorkspaceMemberController extends Controller
{
    public function index(Workspace $workspace)
    {
        return inertia('Workspaces/Members', [
            'workspace' => $workspace,
            'members' => $workspace->members()->with('pivot')->get(),
            'pendingInvitations' => $workspace->pendingInvitations,
            'roles' => Workspaces::roleNames(),
        ]);
    }

    public function update(Request $request, Workspace $workspace, User $user)
    {
        $validated = $request->validate([
            'role' => 'required|string|in:admin,member',
        ]);

        Workspaces::updateMemberRole($workspace, $user, $validated['role']);

        return back();
    }

    public function destroy(Workspace $workspace, User $user)
    {
        Workspaces::removeMember($workspace, $user);

        return back();
    }

    public function transfer(Request $request, Workspace $workspace)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $newOwner = User::findOrFail($validated['user_id']);

        Workspaces::transferOwnership($workspace, $newOwner, auth()->user());

        return back();
    }
}
```

#### WorkspaceInvitationController

```php
use Climactic\Workspaces\Facades\Workspaces;
use Climactic\Workspaces\Models\WorkspaceInvitation;

class WorkspaceInvitationController extends Controller
{
    public function store(Request $request, Workspace $workspace)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'role' => 'required|string|in:admin,member',
        ]);

        Workspaces::invite(
            $workspace,
            $validated['email'],
            $validated['role'],
            auth()->user()
        );

        return back();
    }

    public function destroy(WorkspaceInvitation $invitation)
    {
        Workspaces::cancelInvitation($invitation);

        return back();
    }

    public function show(string $token)
    {
        $invitation = Workspaces::findInvitationByToken($token);

        if (!$invitation || !$invitation->isValid()) {
            abort(404, 'Invitation not found or expired');
        }

        return inertia('Invitations/Show', [
            'invitation' => $invitation->load('workspace', 'inviter'),
        ]);
    }

    public function accept(string $token)
    {
        $invitation = Workspaces::findInvitationByToken($token);

        $workspace = Workspaces::acceptInvitation($invitation, auth()->user());

        return redirect()->route('workspaces.show', $workspace);
    }

    public function decline(string $token)
    {
        $invitation = Workspaces::findInvitationByToken($token);

        Workspaces::declineInvitation($invitation);

        return redirect()->route('home');
    }
}
```

---

## Inertia Implementation

### Sharing Workspace Data

In `app/Http/Middleware/HandleInertiaRequests.php`:

```php
public function share(Request $request): array
{
    return array_merge(parent::share($request), [
        'auth' => [
            'user' => $request->user(),
        ],
        'workspace' => fn () => $this->getWorkspaceData($request),
    ]);
}

protected function getWorkspaceData(Request $request): ?array
{
    $user = $request->user();

    if (!$user) {
        return null;
    }

    $current = $user->currentWorkspace();

    return [
        'current' => $current,
        'all' => $user->workspaces,
        'permissions' => $current ? $user->workspacePermissions($current) : [],
        'role' => $current ? $user->workspaceRole($current) : null,
    ];
}
```

### Workspace Switcher

**Vue (Composition API)**

```vue
<script setup>
import { usePage, router } from '@inertiajs/vue3'
import { computed } from 'vue'

const page = usePage()
const workspaces = computed(() => page.props.workspace?.all ?? [])
const current = computed(() => page.props.workspace?.current)

function switchWorkspace(workspace) {
    router.post(`/workspaces/${workspace.slug}/switch`)
}
</script>

<template>
    <select @change="switchWorkspace(workspaces.find(w => w.id === $event.target.value))">
        <option
            v-for="workspace in workspaces"
            :key="workspace.id"
            :value="workspace.id"
            :selected="workspace.id === current?.id"
        >
            {{ workspace.name }}
        </option>
    </select>
</template>
```

**React**

```jsx
import { usePage, router } from '@inertiajs/react'

export default function WorkspaceSwitcher() {
    const { workspace } = usePage().props
    const workspaces = workspace?.all ?? []
    const current = workspace?.current

    const switchWorkspace = (slug) => {
        router.post(`/workspaces/${slug}/switch`)
    }

    return (
        <select
            value={current?.id}
            onChange={(e) => {
                const ws = workspaces.find(w => w.id === parseInt(e.target.value))
                switchWorkspace(ws.slug)
            }}
        >
            {workspaces.map(ws => (
                <option key={ws.id} value={ws.id}>{ws.name}</option>
            ))}
        </select>
    )
}
```

### Workspace Settings Form

**Vue**

```vue
<script setup>
import { useForm } from '@inertiajs/vue3'

const props = defineProps({ workspace: Object })

const form = useForm({
    name: props.workspace.name,
    description: props.workspace.description ?? '',
})

function submit() {
    form.put(`/workspaces/${props.workspace.slug}`)
}
</script>

<template>
    <form @submit.prevent="submit">
        <input v-model="form.name" type="text" placeholder="Workspace name" />
        <textarea v-model="form.description" placeholder="Description"></textarea>
        <button type="submit" :disabled="form.processing">Save</button>
    </form>
</template>
```

**React**

```jsx
import { useForm } from '@inertiajs/react'

export default function WorkspaceSettings({ workspace }) {
    const { data, setData, put, processing } = useForm({
        name: workspace.name,
        description: workspace.description ?? '',
    })

    const submit = (e) => {
        e.preventDefault()
        put(`/workspaces/${workspace.slug}`)
    }

    return (
        <form onSubmit={submit}>
            <input
                value={data.name}
                onChange={e => setData('name', e.target.value)}
            />
            <textarea
                value={data.description}
                onChange={e => setData('description', e.target.value)}
            />
            <button type="submit" disabled={processing}>Save</button>
        </form>
    )
}
```

### Members Management

**Vue**

```vue
<script setup>
import { router } from '@inertiajs/vue3'

const props = defineProps({
    workspace: Object,
    members: Array,
    roles: Object
})

function updateRole(member, role) {
    router.put(`/workspaces/${props.workspace.slug}/members/${member.id}`, { role })
}

function removeMember(member) {
    if (confirm(`Remove ${member.name}?`)) {
        router.delete(`/workspaces/${props.workspace.slug}/members/${member.id}`)
    }
}
</script>

<template>
    <div v-for="member in members" :key="member.id">
        <span>{{ member.name }}</span>
        <span>{{ member.email }}</span>

        <select
            :value="member.pivot.role"
            @change="updateRole(member, $event.target.value)"
            :disabled="member.pivot.role === 'owner'"
        >
            <option v-for="(name, key) in roles" :key="key" :value="key">
                {{ name }}
            </option>
        </select>

        <button
            v-if="member.pivot.role !== 'owner'"
            @click="removeMember(member)"
        >
            Remove
        </button>
    </div>
</template>
```

**React**

```jsx
import { router } from '@inertiajs/react'

export default function MembersList({ workspace, members, roles }) {
    const updateRole = (member, role) => {
        router.put(`/workspaces/${workspace.slug}/members/${member.id}`, { role })
    }

    const removeMember = (member) => {
        if (confirm(`Remove ${member.name}?`)) {
            router.delete(`/workspaces/${workspace.slug}/members/${member.id}`)
        }
    }

    return (
        <div>
            {members.map(member => (
                <div key={member.id}>
                    <span>{member.name}</span>
                    <select
                        value={member.pivot.role}
                        onChange={e => updateRole(member, e.target.value)}
                        disabled={member.pivot.role === 'owner'}
                    >
                        {Object.entries(roles).map(([key, name]) => (
                            <option key={key} value={key}>{name}</option>
                        ))}
                    </select>
                    {member.pivot.role !== 'owner' && (
                        <button onClick={() => removeMember(member)}>Remove</button>
                    )}
                </div>
            ))}
        </div>
    )
}
```

### Invitations

**Vue - Create Invitation**

```vue
<script setup>
import { useForm } from '@inertiajs/vue3'

const props = defineProps({ workspace: Object, roles: Object })

const form = useForm({
    email: '',
    role: 'member',
})

function submit() {
    form.post(`/workspaces/${props.workspace.slug}/invitations`, {
        onSuccess: () => form.reset(),
    })
}
</script>

<template>
    <form @submit.prevent="submit">
        <input v-model="form.email" type="email" placeholder="Email address" />
        <select v-model="form.role">
            <option v-for="(name, key) in roles" :key="key" :value="key">
                {{ name }}
            </option>
        </select>
        <button type="submit">Send Invitation</button>
    </form>
</template>
```

**Vue - Pending Invitations List**

```vue
<script setup>
import { router } from '@inertiajs/vue3'

const props = defineProps({ invitations: Array })

function cancel(invitation) {
    router.delete(`/invitations/${invitation.id}`)
}
</script>

<template>
    <div v-for="invitation in invitations" :key="invitation.id">
        <span>{{ invitation.email }}</span>
        <span>{{ invitation.role }}</span>
        <span>Expires: {{ invitation.expires_at }}</span>
        <button @click="cancel(invitation)">Cancel</button>
    </div>
</template>
```

**React - Accept Invitation Page**

```jsx
import { router, usePage } from '@inertiajs/react'

export default function AcceptInvitation() {
    const { invitation } = usePage().props

    return (
        <div>
            <h1>You've been invited to {invitation.workspace.name}</h1>
            <p>Invited by: {invitation.inviter.name}</p>
            <p>Role: {invitation.role}</p>

            <button onClick={() => router.post(`/invitations/${invitation.token}/accept`)}>
                Accept
            </button>
            <button onClick={() => router.post(`/invitations/${invitation.token}/decline`)}>
                Decline
            </button>
        </div>
    )
}
```

### Permission Helpers

**Vue Composable**

```js
// composables/useWorkspacePermissions.js
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

export function useWorkspacePermissions() {
    const page = usePage()

    const permissions = computed(() => page.props.workspace?.permissions ?? [])
    const role = computed(() => page.props.workspace?.role)

    const can = (permission) => {
        return permissions.value.includes('*') ||
               permissions.value.includes(permission) ||
               permissions.value.some(p => {
                   if (p.endsWith('.*')) {
                       const prefix = p.slice(0, -1)
                       return permission.startsWith(prefix)
                   }
                   return false
               })
    }

    const isOwner = computed(() => role.value === 'owner')
    const isAdmin = computed(() => ['owner', 'admin'].includes(role.value))

    return { can, isOwner, isAdmin, role, permissions }
}
```

Usage in Vue:

```vue
<script setup>
import { useWorkspacePermissions } from '@/composables/useWorkspacePermissions'

const { can, isAdmin } = useWorkspacePermissions()
</script>

<template>
    <button v-if="can('members.invite')">Invite Member</button>
    <button v-if="isAdmin">Admin Settings</button>
</template>
```

**React Hook**

```js
// hooks/useWorkspacePermissions.js
import { usePage } from '@inertiajs/react'

export function useWorkspacePermissions() {
    const { workspace } = usePage().props
    const permissions = workspace?.permissions ?? []
    const role = workspace?.role

    const can = (permission) => {
        return permissions.includes('*') ||
               permissions.includes(permission) ||
               permissions.some(p => {
                   if (p.endsWith('.*')) {
                       const prefix = p.slice(0, -1)
                       return permission.startsWith(prefix)
                   }
                   return false
               })
    }

    const isOwner = role === 'owner'
    const isAdmin = ['owner', 'admin'].includes(role)

    return { can, isOwner, isAdmin, role, permissions }
}
```

Usage in React:

```jsx
import { useWorkspacePermissions } from '@/hooks/useWorkspacePermissions'

export default function SettingsPage() {
    const { can, isAdmin } = useWorkspacePermissions()

    return (
        <div>
            {can('members.invite') && <button>Invite Member</button>}
            {isAdmin && <button>Admin Settings</button>}
        </div>
    )
}
```

### Create Workspace

**Vue**

```vue
<script setup>
import { useForm } from '@inertiajs/vue3'

const form = useForm({
    name: '',
    description: '',
})

function submit() {
    form.post('/workspaces')
}
</script>

<template>
    <form @submit.prevent="submit">
        <input v-model="form.name" placeholder="Workspace name" required />
        <textarea v-model="form.description" placeholder="Description (optional)" />
        <button type="submit" :disabled="form.processing">Create Workspace</button>
    </form>
</template>
```

---

## Using Laravel Wayfinder

[Laravel Wayfinder](https://github.com/laravel/wayfinder) provides type-safe routing for Inertia applications by generating TypeScript definitions from your Laravel controllers. This eliminates hardcoded URLs and provides IDE autocompletion.

### Setup

Install the Vite plugin:

```bash
npm install @laravel/vite-plugin-wayfinder
```

Configure in `vite.config.js`:

```js
import { wayfinder } from "@laravel/vite-plugin-wayfinder";

export default defineConfig({
    plugins: [
        wayfinder(),
        // ...
    ],
});
```

Generate TypeScript definitions:

```bash
php artisan wayfinder:generate
```

### Using with Workspace Controllers

After running `wayfinder:generate`, you can import controller actions directly:

**Vue with Wayfinder**

```vue
<script setup>
import { useForm } from '@inertiajs/vue3'
import { store, update } from '@/actions/App/Http/Controllers/WorkspaceController'
import { store as invite } from '@/actions/App/Http/Controllers/WorkspaceInvitationController'
import { update as updateMember, destroy as removeMember } from '@/actions/App/Http/Controllers/WorkspaceMemberController'

const props = defineProps({ workspace: Object })

const form = useForm({
    name: props.workspace.name,
    description: props.workspace.description ?? '',
})

function submit() {
    form.submit(update(props.workspace.slug))
}
</script>

<template>
    <form @submit.prevent="submit">
        <input v-model="form.name" type="text" />
        <textarea v-model="form.description"></textarea>
        <button type="submit">Save</button>
    </form>
</template>
```

**React with Wayfinder**

```jsx
import { useForm } from '@inertiajs/react'
import { store, update, switch_ } from '@/actions/App/Http/Controllers/WorkspaceController'
import { store as invite } from '@/actions/App/Http/Controllers/WorkspaceInvitationController'

export default function WorkspaceSettings({ workspace }) {
    const form = useForm({
        name: workspace.name,
        description: workspace.description ?? '',
    })

    const submit = (e) => {
        e.preventDefault()
        form.submit(update(workspace.slug))
    }

    return (
        <form onSubmit={submit}>
            <input
                value={form.data.name}
                onChange={e => form.setData('name', e.target.value)}
            />
            <button type="submit">Save</button>
        </form>
    )
}
```

### Workspace Switcher with Wayfinder

**Vue**

```vue
<script setup>
import { router } from '@inertiajs/vue3'
import { switch_ } from '@/actions/App/Http/Controllers/WorkspaceController'

const props = defineProps({ workspaces: Array, current: Object })

function switchWorkspace(workspace) {
    router.post(switch_.url(workspace.slug))
}
</script>

<template>
    <select @change="switchWorkspace(workspaces.find(w => w.id == $event.target.value))">
        <option v-for="ws in workspaces" :key="ws.id" :value="ws.id" :selected="ws.id === current?.id">
            {{ ws.name }}
        </option>
    </select>
</template>
```

**React**

```jsx
import { router } from '@inertiajs/react'
import { switch_ } from '@/actions/App/Http/Controllers/WorkspaceController'

export default function WorkspaceSwitcher({ workspaces, current }) {
    const switchWorkspace = (slug) => {
        router.post(switch_.url(slug))
    }

    return (
        <select
            value={current?.id}
            onChange={(e) => {
                const ws = workspaces.find(w => w.id === parseInt(e.target.value))
                switchWorkspace(ws.slug)
            }}
        >
            {workspaces.map(ws => (
                <option key={ws.id} value={ws.id}>{ws.name}</option>
            ))}
        </select>
    )
}
```

### Members & Invitations with Wayfinder

**Vue**

```vue
<script setup>
import { router, useForm } from '@inertiajs/vue3'
import { update, destroy } from '@/actions/App/Http/Controllers/WorkspaceMemberController'
import { store as createInvitation, destroy as cancelInvitation } from '@/actions/App/Http/Controllers/WorkspaceInvitationController'

const props = defineProps({ workspace: Object, members: Array })

// Update member role
function updateRole(member, role) {
    router.put(update.url(props.workspace.slug, member.id), { role })
}

// Remove member
function removeMember(member) {
    if (confirm(`Remove ${member.name}?`)) {
        router.delete(destroy.url(props.workspace.slug, member.id))
    }
}

// Invite form
const inviteForm = useForm({ email: '', role: 'member' })

function invite() {
    inviteForm.submit(createInvitation(props.workspace.slug), {
        onSuccess: () => inviteForm.reset(),
    })
}
</script>
```

**React**

```jsx
import { router, useForm } from '@inertiajs/react'
import { update, destroy } from '@/actions/App/Http/Controllers/WorkspaceMemberController'
import { store as createInvitation } from '@/actions/App/Http/Controllers/WorkspaceInvitationController'

export default function MembersPage({ workspace, members }) {
    const updateRole = (member, role) => {
        router.put(update.url(workspace.slug, member.id), { role })
    }

    const removeMember = (member) => {
        if (confirm(`Remove ${member.name}?`)) {
            router.delete(destroy.url(workspace.slug, member.id))
        }
    }

    const inviteForm = useForm({ email: '', role: 'member' })

    const invite = (e) => {
        e.preventDefault()
        inviteForm.submit(createInvitation(workspace.slug), {
            onSuccess: () => inviteForm.reset(),
        })
    }

    // ... render
}
```

### Links with Wayfinder

Use with Inertia's `Link` component for navigation:

**Vue**

```vue
<script setup>
import { Link } from '@inertiajs/vue3'
import { show, index } from '@/actions/App/Http/Controllers/WorkspaceController'
import { index as membersIndex } from '@/actions/App/Http/Controllers/WorkspaceMemberController'
</script>

<template>
    <nav>
        <Link :href="index.url()">All Workspaces</Link>
        <Link :href="show.url(workspace.slug)">{{ workspace.name }}</Link>
        <Link :href="membersIndex.url(workspace.slug)">Members</Link>
    </nav>
</template>
```

**React (TSX)**

```tsx
import { Link } from '@inertiajs/react'
import { show, index } from '@/actions/App/Http/Controllers/WorkspaceController'
import { index as membersIndex } from '@/actions/App/Http/Controllers/WorkspaceMemberController'

export default function Navigation({ workspace }) {
    return (
        <nav>
            <Link href={index.url()}>All Workspaces</Link>
            <Link href={show.url(workspace.slug)}>{workspace.name}</Link>
            <Link href={membersIndex.url(workspace.slug)}>Members</Link>
        </nav>
    )
}
```

### Benefits of Wayfinder

1. **Type Safety**: TypeScript knows your route parameters and catches errors at build time
2. **IDE Autocompletion**: Get suggestions for controller actions and parameters
3. **No Hardcoded URLs**: Routes are generated from your controllers
4. **Refactoring Support**: Renaming a controller method updates everywhere
5. **HTTP Method Awareness**: Wayfinder knows which HTTP method each action uses

```typescript
import { store } from '@/actions/App/Http/Controllers/WorkspaceController'

store()           // { url: "/workspaces", method: "post" }
store.url()       // "/workspaces"

import { update } from '@/actions/App/Http/Controllers/WorkspaceController'

update('my-workspace')        // { url: "/workspaces/my-workspace", method: "put" }
update.url('my-workspace')    // "/workspaces/my-workspace"
```

---

## Livewire Implementation

### View Composer for Shared Data

In `app/Providers/AppServiceProvider.php`:

```php
use Illuminate\Support\Facades\View;
use Climactic\Workspaces\Facades\Workspaces;

public function boot(): void
{
    View::composer('*', function ($view) {
        $user = auth()->user();

        if ($user) {
            $current = $user->currentWorkspace();

            $view->with('currentWorkspace', $current);
            $view->with('workspaces', $user->workspaces);
            $view->with('workspacePermissions', $current ? $user->workspacePermissions($current) : []);
            $view->with('workspaceRole', $current ? $user->workspaceRole($current) : null);
        }
    });
}
```

### Workspace Switcher Component

```php
// app/Livewire/WorkspaceSwitcher.php
namespace App\Livewire;

use Livewire\Component;

class WorkspaceSwitcher extends Component
{
    public $currentWorkspaceId;

    public function mount()
    {
        $this->currentWorkspaceId = auth()->user()->current_workspace_id;
    }

    public function switch($workspaceId)
    {
        $workspace = auth()->user()->workspaces()->findOrFail($workspaceId);
        auth()->user()->switchWorkspace($workspace);

        $this->currentWorkspaceId = $workspaceId;

        return redirect(request()->header('Referer'));
    }

    public function render()
    {
        return view('livewire.workspace-switcher', [
            'workspaces' => auth()->user()->workspaces,
        ]);
    }
}
```

```blade
{{-- resources/views/livewire/workspace-switcher.blade.php --}}
<div>
    <select wire:change="switch($event.target.value)">
        @foreach($workspaces as $workspace)
            <option
                value="{{ $workspace->id }}"
                @selected($workspace->id === $currentWorkspaceId)
            >
                {{ $workspace->name }}
            </option>
        @endforeach
    </select>
</div>
```

### Workspace Settings Component

```php
// app/Livewire/WorkspaceSettings.php
namespace App\Livewire;

use Livewire\Component;
use Climactic\Workspaces\Models\Workspace;

class WorkspaceSettings extends Component
{
    public Workspace $workspace;
    public string $name;
    public ?string $description;

    protected $rules = [
        'name' => 'required|string|max:255',
        'description' => 'nullable|string',
    ];

    public function mount(Workspace $workspace)
    {
        $this->workspace = $workspace;
        $this->name = $workspace->name;
        $this->description = $workspace->description;
    }

    public function save()
    {
        $this->validate();

        $this->workspace->update([
            'name' => $this->name,
            'description' => $this->description,
        ]);

        session()->flash('message', 'Settings saved.');
    }

    public function render()
    {
        return view('livewire.workspace-settings');
    }
}
```

```blade
{{-- resources/views/livewire/workspace-settings.blade.php --}}
<form wire:submit="save">
    <div>
        <label>Name</label>
        <input type="text" wire:model="name" />
        @error('name') <span>{{ $message }}</span> @enderror
    </div>

    <div>
        <label>Description</label>
        <textarea wire:model="description"></textarea>
    </div>

    <button type="submit">Save</button>

    @if (session()->has('message'))
        <span>{{ session('message') }}</span>
    @endif
</form>
```

### Members Management Component

```php
// app/Livewire/WorkspaceMembers.php
namespace App\Livewire;

use Livewire\Component;
use Climactic\Workspaces\Facades\Workspaces;
use Climactic\Workspaces\Models\Workspace;
use App\Models\User;

class WorkspaceMembers extends Component
{
    public Workspace $workspace;
    public ?int $confirmingRemoval = null;

    public function updateRole(int $userId, string $role)
    {
        $user = User::findOrFail($userId);
        Workspaces::updateMemberRole($this->workspace, $user, $role);
    }

    public function confirmRemoval(int $userId)
    {
        $this->confirmingRemoval = $userId;
    }

    public function removeMember()
    {
        if ($this->confirmingRemoval) {
            $user = User::findOrFail($this->confirmingRemoval);
            Workspaces::removeMember($this->workspace, $user);
            $this->confirmingRemoval = null;
        }
    }

    public function render()
    {
        return view('livewire.workspace-members', [
            'members' => $this->workspace->members()->with('pivot')->get(),
            'roles' => Workspaces::roleNames(),
        ]);
    }
}
```

```blade
{{-- resources/views/livewire/workspace-members.blade.php --}}
<div>
    @foreach($members as $member)
        <div>
            <span>{{ $member->name }}</span>
            <span>{{ $member->email }}</span>

            <select
                wire:change="updateRole({{ $member->id }}, $event.target.value)"
                @disabled($member->pivot->role === 'owner')
            >
                @foreach($roles as $key => $name)
                    <option value="{{ $key }}" @selected($member->pivot->role === $key)>
                        {{ $name }}
                    </option>
                @endforeach
            </select>

            @if($member->pivot->role !== 'owner')
                <button wire:click="confirmRemoval({{ $member->id }})">Remove</button>
            @endif
        </div>
    @endforeach

    {{-- Confirmation Modal --}}
    @if($confirmingRemoval)
        <div>
            <p>Are you sure you want to remove this member?</p>
            <button wire:click="removeMember">Confirm</button>
            <button wire:click="$set('confirmingRemoval', null)">Cancel</button>
        </div>
    @endif
</div>
```

### Invitations Component

```php
// app/Livewire/WorkspaceInvitations.php
namespace App\Livewire;

use Livewire\Component;
use Climactic\Workspaces\Facades\Workspaces;
use Climactic\Workspaces\Models\Workspace;

class WorkspaceInvitations extends Component
{
    public Workspace $workspace;
    public string $email = '';
    public string $role = 'member';

    protected $rules = [
        'email' => 'required|email',
        'role' => 'required|in:admin,member',
    ];

    public function invite()
    {
        $this->validate();

        Workspaces::invite(
            $this->workspace,
            $this->email,
            $this->role,
            auth()->user()
        );

        $this->reset(['email', 'role']);
        session()->flash('message', 'Invitation sent.');
    }

    public function cancel(int $invitationId)
    {
        $invitation = $this->workspace->invitations()->findOrFail($invitationId);
        Workspaces::cancelInvitation($invitation);
    }

    public function render()
    {
        return view('livewire.workspace-invitations', [
            'invitations' => $this->workspace->pendingInvitations,
            'roles' => Workspaces::roleNames(),
        ]);
    }
}
```

```blade
{{-- resources/views/livewire/workspace-invitations.blade.php --}}
<div>
    {{-- Invite Form --}}
    <form wire:submit="invite">
        <input type="email" wire:model="email" placeholder="Email address" />
        <select wire:model="role">
            @foreach($roles as $key => $name)
                @if($key !== 'owner')
                    <option value="{{ $key }}">{{ $name }}</option>
                @endif
            @endforeach
        </select>
        <button type="submit">Send Invitation</button>
        @error('email') <span>{{ $message }}</span> @enderror
    </form>

    {{-- Pending Invitations --}}
    <h3>Pending Invitations</h3>
    @forelse($invitations as $invitation)
        <div>
            <span>{{ $invitation->email }}</span>
            <span>{{ $invitation->getRoleName() }}</span>
            <span>Expires: {{ $invitation->expires_at->diffForHumans() }}</span>
            <button wire:click="cancel({{ $invitation->id }})">Cancel</button>
        </div>
    @empty
        <p>No pending invitations.</p>
    @endforelse
</div>
```

### Blade Permission Directive

Register in `app/Providers/AppServiceProvider.php`:

```php
use Illuminate\Support\Facades\Blade;

public function boot(): void
{
    Blade::if('workspacePermission', function (string $permission) {
        $user = auth()->user();
        $workspace = $user?->currentWorkspace();

        if (!$user || !$workspace) {
            return false;
        }

        return $user->hasWorkspacePermission($workspace, $permission);
    });

    Blade::if('workspaceRole', function (string|array $roles) {
        $user = auth()->user();
        $workspace = $user?->currentWorkspace();

        if (!$user || !$workspace) {
            return false;
        }

        return $user->hasWorkspaceRole($workspace, $roles);
    });
}
```

Usage:

```blade
@workspacePermission('members.invite')
    <button>Invite Member</button>
@endworkspacePermission

@workspaceRole('owner')
    <button>Delete Workspace</button>
@endworkspaceRole

@workspaceRole(['admin', 'owner'])
    <a href="{{ route('workspace.settings') }}">Settings</a>
@endworkspaceRole
```

### Create Workspace Component

```php
// app/Livewire/CreateWorkspace.php
namespace App\Livewire;

use Livewire\Component;
use Climactic\Workspaces\Facades\Workspaces;

class CreateWorkspace extends Component
{
    public bool $showModal = false;
    public string $name = '';
    public ?string $description = null;

    protected $rules = [
        'name' => 'required|string|max:255',
        'description' => 'nullable|string',
    ];

    public function create()
    {
        $this->validate();

        $workspace = Workspaces::createWorkspace([
            'name' => $this->name,
            'description' => $this->description,
        ], auth()->user());

        return redirect()->route('workspaces.show', $workspace);
    }

    public function render()
    {
        return view('livewire.create-workspace');
    }
}
```

```blade
{{-- resources/views/livewire/create-workspace.blade.php --}}
<div>
    <button wire:click="$set('showModal', true)">Create Workspace</button>

    @if($showModal)
        <div class="modal">
            <form wire:submit="create">
                <h2>Create New Workspace</h2>

                <div>
                    <label>Name</label>
                    <input type="text" wire:model="name" required />
                    @error('name') <span>{{ $message }}</span> @enderror
                </div>

                <div>
                    <label>Description</label>
                    <textarea wire:model="description"></textarea>
                </div>

                <button type="submit">Create</button>
                <button type="button" wire:click="$set('showModal', false)">Cancel</button>
            </form>
        </div>
    @endif
</div>
```

---

## Events & Notifications

### Listening to Package Events

Register listeners in `app/Providers/EventServiceProvider.php`:

```php
use Climactic\Workspaces\Events\MemberAdded;
use Climactic\Workspaces\Events\MemberRemoved;
use Climactic\Workspaces\Events\InvitationAccepted;
use Climactic\Workspaces\Events\WorkspaceCreated;

protected $listen = [
    MemberAdded::class => [
        \App\Listeners\LogMemberAdded::class,
    ],
    MemberRemoved::class => [
        \App\Listeners\NotifyMemberRemoved::class,
    ],
    InvitationAccepted::class => [
        \App\Listeners\WelcomeNewMember::class,
    ],
    WorkspaceCreated::class => [
        \App\Listeners\SetupDefaultWorkspaceResources::class,
    ],
];
```

Example listener:

```php
// app/Listeners/LogMemberAdded.php
namespace App\Listeners;

use Climactic\Workspaces\Events\MemberAdded;

class LogMemberAdded
{
    public function handle(MemberAdded $event): void
    {
        activity()
            ->performedOn($event->workspace)
            ->causedBy(auth()->user())
            ->withProperties(['role' => $event->role])
            ->log("Added {$event->user->name} as {$event->role}");
    }
}
```

---

## Tips & Best Practices

### Route Model Binding with Slugs

The Workspace model uses `slug` as the route key by default:

```php
// Routes automatically resolve by slug
Route::get('workspaces/{workspace}', [WorkspaceController::class, 'show']);

// URL: /workspaces/my-workspace-name
```

### Caching Current Workspace

For performance in high-traffic applications, cache the current workspace resolution:

```php
// In a custom middleware or service
public function getCurrentWorkspace(): ?Workspace
{
    return Cache::remember(
        "user.{$this->user->id}.current_workspace",
        now()->addMinutes(5),
        fn () => $this->user->currentWorkspace()
    );
}
```

Remember to clear cache when switching workspaces:

```php
// After switching
Cache::forget("user.{$user->id}.current_workspace");
```

### Authorization with Policies

Create a workspace policy for cleaner authorization:

```php
// app/Policies/WorkspacePolicy.php
namespace App\Policies;

use App\Models\User;
use Climactic\Workspaces\Models\Workspace;

class WorkspacePolicy
{
    public function update(User $user, Workspace $workspace): bool
    {
        return $user->hasWorkspacePermission($workspace, 'workspace.update');
    }

    public function delete(User $user, Workspace $workspace): bool
    {
        return $user->ownsWorkspace($workspace);
    }

    public function manageMembers(User $user, Workspace $workspace): bool
    {
        return $user->hasWorkspaceRole($workspace, ['owner', 'admin']);
    }
}
```

### Testing

```php
use Climactic\Workspaces\Models\Workspace;

test('user can switch workspace', function () {
    $user = User::factory()->create();
    $workspace1 = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace2 = Workspace::factory()->create(['owner_id' => $user->id]);

    $user->switchWorkspace($workspace1);
    expect($user->currentWorkspace()->id)->toBe($workspace1->id);

    $user->switchWorkspace($workspace2);
    expect($user->currentWorkspace()->id)->toBe($workspace2->id);
});

test('member cannot access admin routes', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

    $workspace->addMember($member, 'member');

    $this->actingAs($member)
        ->post("/workspaces/{$workspace->slug}/invitations", ['email' => 'test@example.com', 'role' => 'member'])
        ->assertForbidden();
});
```

---

## Quick Reference

### Key Methods

| Task | Method |
|------|--------|
| Get current workspace | `$user->currentWorkspace()` or `Workspaces::current()` |
| Switch workspace | `$user->switchWorkspace($workspace)` |
| Check membership | `$user->belongsToWorkspace($workspace)` |
| Check permission | `$user->hasWorkspacePermission($workspace, 'permission')` |
| Check role | `$user->hasWorkspaceRole($workspace, 'admin')` |
| Get user's role | `$user->workspaceRole($workspace)` |
| Add member | `$workspace->addMember($user, 'member')` |
| Remove member | `$workspace->removeMember($user)` |
| Update role | `$workspace->updateMemberRole($user, 'admin')` |
| Create invitation | `Workspaces::invite($workspace, $email, $role, $inviter)` |
| Accept invitation | `Workspaces::acceptInvitation($invitation, $user)` |

### Middleware

| Middleware | Purpose |
|------------|---------|
| `workspace` | Sets workspace context from request |
| `workspace.access` | Requires user to be workspace member |
| `workspace.role:admin,owner` | Requires specific role(s) |

### Events

| Event | Fired When |
|-------|------------|
| `WorkspaceCreated` | New workspace created |
| `WorkspaceSwitched` | User switches workspace |
| `MemberAdded` | User added to workspace |
| `MemberRemoved` | User removed from workspace |
| `MemberRoleUpdated` | Member's role changed |
| `InvitationCreated` | Invitation sent |
| `InvitationAccepted` | Invitation accepted |
| `InvitationDeclined` | Invitation declined |
| `InvitationCancelled` | Invitation cancelled |
| `OwnershipTransferred` | Ownership transferred |
