<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property string $kind
 * @property string $unit
 * @property bool $active
 * @property int|null $threshold_thousandths
 * @property int $balance_thousandths
 * @property int $revision
 */
class InventoryItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'threshold_thousandths' => 'integer', 'balance_thousandths' => 'integer', 'revision' => 'integer'];
    }
}
