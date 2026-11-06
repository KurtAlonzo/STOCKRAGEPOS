<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** @property int $id
 * @property int $team_id
 * @property string $name
 * @property bool $active
 * @property int $revision
 */
class Category extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'revision' => 'integer'];
    }
}
