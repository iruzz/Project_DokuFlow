<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['code', 'name'])]
class Branch extends Model
{
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
