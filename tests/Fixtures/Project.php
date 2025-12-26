<?php

namespace Climactic\Workspaces\Tests\Fixtures;

use Climactic\Workspaces\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

/**
 * Test model that belongs to a workspace.
 */
class Project extends Model
{
    use BelongsToWorkspace;

    protected $table = 'projects';

    protected $fillable = [
        'name',
        'workspace_id',
    ];
}
