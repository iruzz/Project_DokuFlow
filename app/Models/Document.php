<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    protected $fillable = [
        'document_number', 'title', 'summary', 'summary_status', 'summary_error',
        'summary_started_at', 'summary_completed_at', 'visibility', 'division_id', 'owner_id',
        'document_type_id', 'is_public', 'current_version_id',
        'pending_rollback_version_id', 'rollback_requested_by_id', 'rollback_requested_at',
        'paper_size', 'paper_margin',
        'general_access', 'link_role', 'share_token',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'rollback_requested_at' => 'datetime',
            'summary_started_at' => 'datetime',
            'summary_completed_at' => 'datetime',
            'paper_margin' => 'array',
        ];
    }

    public const SUMMARY_PENDING = 'pending';
    public const SUMMARY_PROCESSING = 'processing';
    public const SUMMARY_COMPLETED = 'completed';
    public const SUMMARY_FAILED = 'failed';

    public function isSummaryCompleted(): bool
    {
        return $this->summary_status === self::SUMMARY_COMPLETED;
    }

    public const VISIBILITY_GENERAL = 'general';
    public const VISIBILITY_DIVISION = 'division';
    public const VISIBILITY_PERSONAL = 'personal';

    public function isGeneral(): bool
    {
        return $this->visibility === self::VISIBILITY_GENERAL;
    }

    public function isDivision(): bool
    {
        return $this->visibility === self::VISIBILITY_DIVISION;
    }

    public function isPersonal(): bool
    {
        return $this->visibility === self::VISIBILITY_PERSONAL;
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'current_version_id');
    }

    public function pendingRollbackVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'pending_rollback_version_id');
    }

    public function rollbackRequestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rollback_requested_by_id');
    }

    public function hasPendingRollback(): bool
    {
        return !is_null($this->pending_rollback_version_id);
    }

    /**
     * Version to display: newest non-discarded pending (edit terbaru yang
     * belum di-approve), else approved current version, else latest draft.
     */
    public function displayVersion(): ?DocumentVersion
    {
        $versions = $this->versions
            ->filter(fn($v) => !$v->discarded_at)
            ->sortByDesc('version_number');

        $pending = $versions->first(fn($v) => $v->status === 'pending');
        if ($pending) {
            return $pending;
        }

        if ($this->currentVersion) {
            return $this->currentVersion;
        }

        return $versions->first(fn($v) => $v->status === 'draft');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    public function signatureRequests(): HasMany
    {
        return $this->hasMany(SignatureRequest::class);
    }

    public function accessLinks(): HasMany
    {
        return $this->hasMany(DocumentAccessLink::class);
    }

    public function shares(): HasMany
    {
        return $this->hasMany(DocumentShare::class);
    }

    public function divisionShares(): HasMany
    {
        return $this->hasMany(DocumentDivisionShare::class);
    }

    public function scopeActive($query)
    {
        return $query->whereHas('versions', fn($q) => $q->where('status', 'active'));
    }

    public function scopePending($query)
    {
        return $query->whereHas('versions', fn($q) => $q->where('status', 'pending'));
    }

    /**
     * General (public) documents — visible to every authenticated user.
     * Hanya dokumen berstatus aktif (punya versi approved) yang muncul,
     * konsisten dengan scopeDivision. Dokumen pending/draft tidak tampil.
     */
    public function scopeGeneral(Builder $query): Builder
    {
        return $query->where('visibility', self::VISIBILITY_GENERAL)
            ->whereHas('versions', fn($q) => $q->where('status', 'active'));
    }

    /**
     * Division-scoped documents the given user may see (Dokumen Divisi tab).
     */
    public function scopeDivision(Builder $query, User $user): Builder
    {
        $divisionIds = $user->allDivisionIds();

        if (empty($divisionIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('visibility', self::VISIBILITY_DIVISION)
            ->whereIn('division_id', $divisionIds)
            // Only approved/published documents appear in Dokumen Divisi.
            // Pending (not yet approved) documents stay hidden until approved.
            ->whereHas('versions', fn($q) => $q->where('status', 'active'));
    }

    /**
     * Documents the given user is allowed to see (row-level visibility).
     * Admin sees everything. Regular users see: general docs, own docs
     * (any scope), division docs of any division they belong to, and
     * docs where they have a personal share or a division share.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        $divisionIds = $user->allDivisionIds();

        return $query->where(function (Builder $q) use ($user, $divisionIds) {
            $q->where('visibility', self::VISIBILITY_GENERAL)
                ->orWhere('owner_id', $user->id)
                ->orWhere(function (Builder $sub) use ($divisionIds) {
                    $sub->where('visibility', self::VISIBILITY_DIVISION)
                        ->whereIn('division_id', $divisionIds);
                })
                ->orWhereHas('shares', fn(Builder $s) => $s->where('user_id', $user->id))
                ->orWhereHas('divisionShares', fn(Builder $ds) => $ds->whereIn('division_id', $divisionIds));
        });
    }

    /**
     * Documents owned by the given user (My Documents tab).
     */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('owner_id', $user->id);
    }
}
