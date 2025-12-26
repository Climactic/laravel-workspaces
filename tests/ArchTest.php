<?php

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r'])
    ->each->not->toBeUsed();

arch('actions are invokable or have execute method')
    ->expect('Climactic\Workspaces\Actions')
    ->toHaveMethod('execute');

arch('models extend eloquent model')
    ->expect('Climactic\Workspaces\Models')
    ->toExtend('Illuminate\Database\Eloquent\Model');

arch('contracts are interfaces')
    ->expect('Climactic\Workspaces\Contracts')
    ->toBeInterfaces();

arch('exceptions extend exception')
    ->expect('Climactic\Workspaces\Exceptions')
    ->toExtend('Exception');

arch('middleware has handle method')
    ->expect('Climactic\Workspaces\Middleware')
    ->toHaveMethod('handle');

arch('events are classes')
    ->expect('Climactic\Workspaces\Events')
    ->toBeClasses();

arch('concerns are traits')
    ->expect('Climactic\Workspaces\Concerns')
    ->toBeTraits();

arch('permission providers implement contract')
    ->expect('Climactic\Workspaces\Permissions\ConfigPermissionProvider')
    ->toImplement('Climactic\Workspaces\Contracts\PermissionProviderContract');

arch('strict types are used')
    ->expect('Climactic\Workspaces')
    ->toUseStrictTypes();
