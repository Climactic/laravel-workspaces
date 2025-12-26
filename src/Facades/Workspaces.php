<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Climactic\Workspaces\Workspaces
 */
class Workspaces extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Climactic\Workspaces\Workspaces::class;
    }
}
