<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'division_id', 'system_role', 'is_active', 'nik', 'phone_number', 'branch_id', 'position'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * All divisions the user belongs to (primary + additional via pivot).
     */
    public function divisions(): BelongsToMany
    {
        return $this->belongsToMany(Division::class)->withTimestamps();
    }

    /**
     * IDs of every division the user is a member of.
     */
    public function allDivisionIds(): array
    {
        $ids = $this->divisions()->pluck('divisions.id')->all();

        if ($this->division_id) {
            $ids[] = $this->division_id;
        }

        return array_values(array_unique($ids));
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'owner_id');
    }

    public function authoredVersions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class, 'author_id');
    }

    public function reviewedVersions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class, 'reviewer_id');
    }

    public function createdLinks(): HasMany
    {
        return $this->hasMany(DocumentAccessLink::class, 'created_by');
    }

    public function documentShares(): HasMany
    {
        return $this->hasMany(DocumentShare::class);
    }

    public function signature(): HasOne
    {
        return $this->hasOne(Signature::class);
    }

    public function requestedSignatures(): HasMany
    {
        return $this->hasMany(SignatureRequest::class, 'requester_id');
    }

    public function receivedSignatureRequests(): HasMany
    {
        return $this->hasMany(SignatureRequest::class, 'target_user_id');
    }

    public function hasSignature(): bool
    {
        return $this->signature !== null && file_exists($this->signature->absolute_path);
    }

    public function isAdmin(): bool
    {
        return $this->system_role === 'admin';
    }

    public function isHead(): bool
    {
        return $this->system_role === 'head';
    }

    public function isDirector(): bool
    {
        return $this->system_role === 'director';
    }

    protected $with = ['signature'];
}
