<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Division;
use App\Services\AuditService;
use App\Services\DocumentService;
use App\Services\VersionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DocumentController extends Controller
{
    public function __construct(
        protected DocumentService $documentService,
        protected VersionService $versionService,
        protected AuditService $auditService,
    ) {}

    public function index(Request $request): View
    {
        $user = auth()->user();

        $query = Document::with('owner', 'division', 'currentVersion')
            ->where(function ($q) use ($user) {
                $q->where('division_id', $user->division_id)
                  ->orWhere('is_public', true);
            });

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('document_number', 'like', "%{$search}%");
            });
        }

        if ($divisionId = $request->get('division_id')) {
            $query->where('division_id', $divisionId);
        }

        if ($status = $request->get('status')) {
            if ($status === 'active') {
                $query->whereHas('currentVersion', fn($q) => $q->where('status', 'active'));
            } elseif ($status === 'pending') {
                $query->whereDoesntHave('currentVersion')
                    ->orWhereHas('versions', fn($q) => $q->where('status', 'pending'));
            } elseif ($status === 'draft') {
                $query->whereDoesntHave('versions');
            }
        }

        $documents = $query->latest()->paginate(15)->withQueryString();
        $divisions = auth()->user()->isAdmin()
            ? Division::all()
            : Division::where('id', auth()->user()->division_id)->get();

        return view('documents.index', compact('documents', 'divisions'));
    }

    public function create(): View
    {
        $divisions = auth()->user()->isAdmin()
            ? Division::all()
            : Division::where('id', auth()->user()->division_id)->get();

        return view('documents.create', compact('divisions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Document::class);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'division_id' => 'required|exists:divisions,id',
        ]);

        $doc = $this->documentService->create($validated, auth()->id());

        $this->auditService->log(auth()->user(), 'document.created', 'document', $doc->id, [
            'title' => $doc->title,
            'document_number' => $doc->document_number,
        ]);

        return redirect()->route('documents.edit', $doc)->with('success', 'Document created.');
    }

    public function show(Document $document): View
    {
        $this->authorize('view', $document);

        $document->load('owner', 'division', 'currentVersion', 'versions.author');

        return view('documents.show', compact('document'));
    }

    public function edit(Document $document): View
    {
        $this->authorize('update', $document);

        $document->load('currentVersion');

        return view('documents.edit', compact('document'));
    }

    public function preview(Document $document): View
    {
        $this->authorize('view', $document);

        $document->load('owner', 'division', 'currentVersion');

        return view('documents.preview', compact('document'));
    }

    public function save(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('update', $document);

        $validated = $request->validate([
            'content' => 'required|string',
        ]);

        $version = $this->versionService->savePending($document, $validated['content'], auth()->user());

        $this->auditService->log(auth()->user(), 'version.created', 'document_version', $version->id, [
            'document_id' => $document->id,
            'version_number' => $version->version_number,
        ]);

        return redirect()->route('documents.show', $document)->with('success', 'Edit saved. Pending approval.');
    }

    public function togglePublic(Document $document): RedirectResponse
    {
        $this->authorize('update', $document);

        $document->update(['is_public' => !$document->is_public]);

        $this->auditService->log(auth()->user(), 'document.toggle_public', 'document', $document->id, [
            'is_public' => $document->is_public,
        ]);

        return back()->with('success', $document->is_public ? 'Document is now public.' : 'Document is now private.');
    }
}
