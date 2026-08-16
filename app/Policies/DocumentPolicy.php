<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use App\Services\DocumentShareService;

class DocumentPolicy
{
    public function view(User $user, Document $document): bool
    {
        if ($user->isAdmin()) return true;
        if ($document->isGeneral()) return true;
        if ($user->id === $document->owner_id) return true;

        // Division-scoped docs: visible to members of that division.
        if ($document->isDivision()
            && $document->division_id
            && in_array($document->division_id, $user->allDivisionIds(), true)) {
            return true;
        }

        // Any share (personal, division, or link) grants view.
        if (app(DocumentShareService::class)->resolveEffectiveRole($document, $user) !== null) {
            return true;
        }

        // A requested signer is allowed to view the document
        return $document->signatureRequests()->where('target_user_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        // Any active user may create documents; personal/general docs
        // do not require a division.
        return $user->is_active;
    }

    public function update(User $user, Document $document): bool
    {
        if ($user->id === $document->owner_id || $user->isAdmin()) return true;

        $role = app(DocumentShareService::class)->resolveEffectiveRole($document, $user);

        return in_array($role, ['owner', 'editor'], true);
    }

    public function manageAccess(User $user, Document $document): bool
    {
        return $user->id === $document->owner_id || $user->isAdmin();
    }

    public function approve(User $user, Document $document): bool
    {
        if ($user->isAdmin()) return true;
        return $user->isHead() && $user->division_id === $document->division_id;
    }

    public function delete(User $user, Document $document): bool
    {
        if ($user->isAdmin()) return true;

        // Owner boleh hapus dokumen selama belum punya versi approved (active).
        return $user->id === $document->owner_id
            && !$document->versions()->where('status', 'active')->exists();
    }
}
