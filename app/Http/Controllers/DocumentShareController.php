<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\Document;
use App\Models\DocumentDivisionShare;
use App\Models\DocumentShare;
use App\Models\User;
use App\Services\AuditService;
use App\Services\DocumentShareService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DocumentShareController extends Controller
{
    public function __construct(
        protected DocumentShareService $shareService,
        protected AuditService $auditService,
    ) {}

    public function store(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('manageAccess', $document);

        $validated = $request->validate([
            'type' => 'required|in:user,division',
            'user_id' => 'required_without:division_id|exists:users,id',
            'division_id' => 'required_without:user_id|exists:divisions,id',
            'role' => 'required|in:editor,viewer',
        ]);

        $invitedBy = auth()->user();

        if ($validated['type'] === 'user') {
            $share = $this->shareService->addUserShare(
                $document,
                User::findOrFail($validated['user_id']),
                $validated['role'],
                $invitedBy,
            );
            $this->auditService->log($invitedBy, 'share.user.added', 'document_share', $share->id, [
                'document_id' => $document->id,
                'user_id' => $validated['user_id'],
                'role' => $validated['role'],
            ]);
        } else {
            $share = $this->shareService->addDivisionShare(
                $document,
                Division::findOrFail($validated['division_id']),
                $validated['role'],
                $invitedBy,
            );
            $this->auditService->log($invitedBy, 'share.division.added', 'document_division_share', $share->id, [
                'document_id' => $document->id,
                'division_id' => $validated['division_id'],
                'role' => $validated['role'],
            ]);
        }

        return back()->with('notice', 'Akses berhasil ditambahkan.');
    }

    public function updateUserShare(Request $request, Document $document, DocumentShare $share): RedirectResponse
    {
        $this->authorize('manageAccess', $document);

        $validated = $request->validate(['role' => 'required|in:editor,viewer']);

        $this->shareService->updateUserShareRole($share, $validated['role']);
        $this->auditService->log(auth()->user(), 'share.user.updated', 'document_share', $share->id, [
            'document_id' => $document->id,
            'role' => $validated['role'],
        ]);

        return back()->with('notice', 'Peran pengguna diperbarui.');
    }

    public function destroyUserShare(Document $document, DocumentShare $share): RedirectResponse
    {
        $this->authorize('manageAccess', $document);

        $this->shareService->removeUserShare($share);
        $this->auditService->log(auth()->user(), 'share.user.removed', 'document_share', $share->id, [
            'document_id' => $document->id,
        ]);

        return back()->with('notice', 'Akses pengguna dihapus.');
    }

    public function updateDivisionShare(Request $request, Document $document, DocumentDivisionShare $divisionShare): RedirectResponse
    {
        $this->authorize('manageAccess', $document);

        $validated = $request->validate(['role' => 'required|in:editor,viewer']);

        $this->shareService->updateDivisionShareRole($divisionShare, $validated['role']);
        $this->auditService->log(auth()->user(), 'share.division.updated', 'document_division_share', $divisionShare->id, [
            'document_id' => $document->id,
            'role' => $validated['role'],
        ]);

        return back()->with('notice', 'Peran divisi diperbarui.');
    }

    public function destroyDivisionShare(Document $document, DocumentDivisionShare $divisionShare): RedirectResponse
    {
        $this->authorize('manageAccess', $document);

        $this->shareService->removeDivisionShare($divisionShare);
        $this->auditService->log(auth()->user(), 'share.division.removed', 'document_division_share', $divisionShare->id, [
            'document_id' => $document->id,
        ]);

        return back()->with('notice', 'Akses divisi dihapus.');
    }

    public function updateGeneralAccess(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('manageAccess', $document);

        $validated = $request->validate([
            'general_access' => 'required|in:restricted,anyone_with_link',
        ]);

        $this->shareService->updateGeneralAccess($document, $validated['general_access']);
        $this->auditService->log(auth()->user(), 'share.general_access.updated', 'document', $document->id, [
            'general_access' => $validated['general_access'],
        ]);

        return back()->with('notice', 'Pengaturan akses umum diperbarui.');
    }

    public function regenerateToken(Document $document): JsonResponse
    {
        $this->authorize('manageAccess', $document);

        $token = $this->shareService->regenerateShareToken($document);
        $this->auditService->log(auth()->user(), 'share.token.regenerated', 'document', $document->id, []);

        return response()->json([
            'success' => true,
            'share_token' => $token,
            'share_url' => route('documents.shared', $token),
        ]);
    }

    public function shareData(Document $document): JsonResponse
    {
        $this->authorize('view', $document);

        $document->load(['shares.user', 'divisionShares.division']);

        return response()->json([
            'owner' => [
                'id' => $document->owner_id,
                'name' => $document->owner?->name,
            ],
            'general_access' => $document->general_access,
            'link_role' => $document->link_role,
            'share_token' => $document->share_token,
            'share_url' => $document->share_token ? route('documents.shared', $document->share_token) : null,
            'shares' => $document->shares->map(fn(DocumentShare $s) => [
                'id' => $s->id,
                'user_id' => $s->user_id,
                'name' => $s->user?->name,
                'email' => $s->user?->email,
                'role' => $s->role,
            ]),
            'division_shares' => $document->divisionShares->map(fn(DocumentDivisionShare $s) => [
                'id' => $s->id,
                'division_id' => $s->division_id,
                'name' => $s->division?->name,
                'role' => $s->role,
            ]),
        ]);
    }

    public function searchSharees(Request $request): JsonResponse
    {
        $term = trim($request->get('q', ''));

        $users = User::query()
            ->where('is_active', true)
            ->when($term !== '', fn($q) => $q->where(fn($q2) => $q2
                ->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")))
            ->limit(10)
            ->get(['id', 'name', 'email']);

        $divisions = Division::query()
            ->when($term !== '', fn($q) => $q->where('name', 'like', "%{$term}%"))
            ->limit(10)
            ->get(['id', 'name']);

        return response()->json([
            'users' => $users,
            'divisions' => $divisions,
        ]);
    }

    /**
     * Open a document via its share_token link.
     */
    public function accessByToken(string $token)
    {
        $document = Document::where('share_token', $token)->firstOrFail();

        if ($document->general_access !== DocumentShareService::GENERAL_ACCESS_ANYONE_WITH_LINK) {
            abort(404);
        }

        if (!auth()->check()) {
            return redirect()->guest(route('login'));
        }

        $this->authorize('view', $document);

        $document->load('owner', 'division', 'currentVersion', 'versions.author');

        return view('documents.show', compact('document'));
    }
}