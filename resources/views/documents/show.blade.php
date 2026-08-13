<x-app-layout>
    <x-slot name="header">Document Detail</x-slot>

    @if(!auth()->user()->isAdmin() && !auth()->user()->isHead())
        <x-confirm-modal
            name="confirm-discard-{{ $document->id }}"
            :title="__('Discard Document?')"
            :message="__('Are you sure you want to discard this document?')"
            :action="route('documents.discard', $document)"
            method="POST"
            :confirmLabel="__('Discard')"
        />
    @endif

    {{-- Konfirmasi approve rollback (banner pending rollback) --}}
    @if($document->hasPendingRollback() && auth()->user()->can('approve', $document))
        <x-confirm-modal
            name="confirm-approve-rollback"
            title="Approve Rollback?"
            message="Versi setelah v{{ $document->pendingRollbackVersion->version_number }} akan dihapus permanen dan tidak bisa dikembalikan. Lanjutkan?"
            :action="route('approvals.rollback-request.approve', $document)"
            method="POST"
            confirmLabel="Approve Rollback"
            confirmClass="btn-success"
        />
    @endif

    {{-- Konfirmasi ajukan rollback (modal Version History) --}}
    @foreach($document->versions as $version)
        @if($version->id !== $document->current_version_id
            && $version->status !== 'pending'
            && !($version->status === 'discarded' || $version->discarded_at)
            && !$document->hasPendingRollback()
            && auth()->user()->can('update', $document))
            <x-confirm-modal
                name="confirm-rollback-{{ $version->id }}"
                title="Rollback ke v{{ $version->version_number }}?"
                message="Permintaan rollback akan diajukan ke kepala divisi. Jika disetujui, semua versi setelah v{{ $version->version_number }} akan dihapus permanen."
                :action="route('approvals.rollback', [$document, $version])"
                method="POST"
                confirmLabel="Ajukan Rollback"
                reopenOnCancel="version-modal"
            />
        @endif
    @endforeach

    <div class="py-6">
        <div class="max-w-7xl mx-auto w-full">
            @if(session('success'))
                <div class="alert alert-success mb-4">
                    <span>{{ session('success') }}</span>
                </div>
            @endif
            @if(session('error'))
                <div class="alert alert-error mb-4">
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            <!-- Pending Rollback Banner -->
            @if($document->hasPendingRollback())
                <div class="alert alert-warning mb-6 shadow-sm">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex items-start sm:items-center gap-3 min-w-0">
                            <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                            </svg>
                            <div>
                                <p class="font-semibold text-sm">{{ __('Permintaan rollback ke') }} v{{ $document->pendingRollbackVersion->version_number }}</p>
                                <p class="text-xs text-base-content/70">
                                    {{ __('Diajukan oleh') }} {{ $document->rollbackRequestedBy?->name ?? '—' }}.
                                    {{ __('Versi setelah') }} v{{ $document->pendingRollbackVersion->version_number }} {{ __('akan dihapus permanen jika disetujui.') }}
                                </p>
                            </div>
                        </div>
                        @can('approve', $document)
                            <div class="flex flex-wrap gap-2 shrink-0">
                                <form method="POST" action="{{ route('approvals.rollback-request.approve', $document) }}" class="inline">
                                    @csrf
                                    <button class="btn btn-success btn-sm">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                        {{ __('Approve') }} Rollback
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('approvals.rollback-request.reject', $document) }}" class="inline">
                                    @csrf
                                    <button class="btn btn-outline btn-error btn-sm">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                        {{ __('Reject') }}
                                    </button>
                                </form>
                            </div>
                        @endcan
                    </div>
                </div>
            @endif

            <!-- Pending Banner (paling atas) -->
            @php $pendingVersion = $document->versions->firstWhere('status', 'pending'); @endphp
            @if($pendingVersion)
                <div class="alert alert-warning mb-6 shadow-sm">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex items-start sm:items-center gap-3 min-w-0">
                            <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <div>
                                <p class="font-semibold text-sm">{{ __('Menunggu Persetujuan') }} (v{{ $pendingVersion->version_number }})</p>
                                <p class="text-xs text-base-content/70">{{ __('Versi menunggu review oleh kepala divisi.') }}</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 shrink-0">
                            @can('update', $document)
                                @if(auth()->user()->isAdmin() || auth()->user()->isHead())
                                    <form method="POST" action="{{ route('documents.discard', $document) }}" class="inline">
                                        @csrf
                                        <button class="btn btn-outline btn-warning btn-xs">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            {{ __('Discard') }}
                                        </button>
                                    </form>
                                @else
                                    <button type="button" class="btn btn-outline btn-warning btn-xs" x-on:click="$dispatch('open-modal', 'confirm-discard-{{ $document->id }}')">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            {{ __('Discard') }}
                                    </button>
                                @endif
                            @endcan
                            @can('approve', $document)
                                <form method="POST" action="{{ route('approvals.approve', [$document, $pendingVersion]) }}" class="inline">
                                    @csrf
                                    <button class="btn btn-success btn-sm">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                        {{ __('Approve') }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('approvals.reject', [$document, $pendingVersion]) }}" class="inline">
                                    @csrf
                                    <button class="btn btn-error btn-sm">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                        {{ __('Reject') }}
                                    </button>
                                </form>
                            @endcan
                        </div>
                    </div>
                </div>
            @endif

            <!-- Metadata -->
            @php
                $hasDraft = $document->versions->contains('status', 'draft');
            @endphp
            <div class="card bg-base-100 border border-base-300 shadow-sm mb-6">
                <div class="card-body">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-base-200 pb-4">
                        <h1 class="text-xl font-bold text-base-content truncate min-w-0">{{ $document->title }}</h1>
                        <span class="badge badge-outline badge-sm shrink-0">{{ $document->document_number }}</span>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-x-4 gap-y-5 pt-4 text-sm">
                        <div>
                            <span class="text-xs uppercase tracking-wide text-base-content/50">{{ __('Divisi') }}</span>
                            <p class="font-medium mt-1">{{ $document->division?->code ?? '—' }}</p>
                        </div>
                        <div>
                            <span class="text-xs uppercase tracking-wide text-base-content/50">{{ __('Pengguna') }}</span>
                            <p class="font-medium mt-1">{{ $document->owner->name }}</p>
                        </div>
                        <div>
                            <span class="text-xs uppercase tracking-wide text-base-content/50">{{ __('Status') }}</span>
                            <p class="font-medium mt-1">
                                @if($document->currentVersion)
                                    {{ __('Aktif') }} (v{{ $document->currentVersion->version_number }})
                                @elseif($pendingVersion)
                                    <span class="text-warning">{{ __('Menunggu Persetujuan') }} (v{{ $pendingVersion->version_number }})</span>
                                @elseif($hasDraft)
                                    <span class="text-warning">{{ __('Draf') }}</span>
                                @else
                                    <span class="text-warning">{{ __('Menunggu Persetujuan Pertama') }}</span>
                                @endif
                            </p>
                        </div>
                        <div>
                            <span class="text-xs uppercase tracking-wide text-base-content/50">{{ __('Visibilitas') }}</span>
                            <p class="font-medium mt-1">
                                @if($document->isGeneral())
                                    <span class="badge badge-success badge-sm">{{ __('Umum') }}</span>
                                @elseif($document->isPersonal())
                                    <span class="badge badge-info badge-sm">{{ __('Personal') }}</span>
                                @else
                                    <span class="badge badge-neutral badge-sm">{{ $document->division?->code ?? __('Divisi') }} {{ __('saja') }}</span>
                                @endif
                            </p>
                        </div>
                    </div>

                    {{-- Actions (di bawah keterangan, sejajar menyamping) --}}
                    @php $isFileBased = $document->displayVersion()?->file_path; @endphp
                    <div class="flex flex-wrap items-center gap-2 mt-5 pt-4 border-t border-base-200">
                        @can('update', $document)
                            @if($isFileBased)
                                <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('edit-restricted-modal').showModal()">
                                    {{ __('Edit Dokumen') }}
                                </button>
                            @elseif($hasDraft && !$pendingVersion && !$document->currentVersion)
                                <a href="{{ route('documents.edit', $document) }}" class="btn btn-primary btn-sm">
                                    {{ __('Edit Draft') }}
                                </a>
                            @else
                                <a href="{{ route('documents.edit', $document) }}" class="btn btn-primary btn-sm">
                                    {{ __('Edit Dokumen') }}
                                </a>
                            @endif
                        @endcan
                        @can('update', $document)
                        @endcan
                        @can('manageAccess', $document)
                            <button type="button" onclick="openShareModal()" class="btn btn-outline btn-primary btn-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 0a3 3 0 11-5.367 2.684 3 3 0 015.367-2.684z" /></svg>
                                Bagikan
                            </button>
                        @endcan

                        <button
                            type="button"
                            class="btn btn-ghost btn-sm border border-base-300"
                            onclick="document.getElementById('version-modal').showModal()"
                        >
                            {{ __('Lihat Versi') }} ({{ $document->versions->count() }})
                        </button>

                        @can('update', $document)
                            <button type="button" class="btn btn-ghost btn-sm border border-base-300" onclick="document.getElementById('scope-modal').showModal()">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                {{ __('Ubah Cakupan') }}
                            </button>
                        @endcan

                        {{-- Export to PDF (hanya untuk dokumen hasil editor) —
                             buka modal supaya ukuran kertas bisa dipilih dulu
                             sebelum export, terpisah dari paper_size tersimpan
                             di dokumen (lihat #export-pdf-modal). --}}
                        @if(!$isFileBased)
                            <button type="button" class="btn btn-ghost btn-sm border border-base-300"
                                    onclick="document.getElementById('export-pdf-modal').showModal()">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                {{ __('Export PDF') }}
                            </button>
                        @endif

                        <button type="button" class="btn btn-ghost btn-sm border border-base-300"
                                onclick="loadSummary()">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                            Ringkas Dokumen
                        </button>
                    </div>

                    @if(session('pdf_export'))
                        <div class="alert alert-success mt-3">
                            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 w-full">
                                <span>PDF berhasil dibuat. <span class="font-medium">{{ session('pdf_export.filename') }}</span></span>
                                <a href="{{ session('pdf_export.url') }}" target="_blank" rel="noopener" class="btn btn-primary btn-sm shrink-0">
                                    Download PDF
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Content -->
            <!-- Content -->
            {{-- Ringkasan Dokumen (card, bukan modal) --}}
            @php
                $hasSummary = !empty($document->summary) && $document->summary_status === \App\Models\Document::SUMMARY_COMPLETED;
                $isProcessing = $document->summary_status === \App\Models\Document::SUMMARY_PROCESSING;
                $isFailed = $document->summary_status === \App\Models\Document::SUMMARY_FAILED;
            @endphp
            <div id="summary-card" class="card bg-base-100 border border-primary/20 shadow-md mb-6 {{ (!$hasSummary && !$isProcessing && !$isFailed) ? 'hidden' : '' }}">
                <div class="card-body p-5 sm:p-6">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-base-200 pb-3 mb-4">
                        <div class="flex items-center gap-2.5">
                            <span class="p-2 rounded-xl bg-primary/10 text-primary">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                            </span>
                            <div>
                                <h3 class="font-bold text-base text-base-content flex items-center gap-2">
                                    Ringkasan AI Dokumen
                                </h3>
                                <p class="text-xs text-base-content/60">Ringkasan otomatis berbasis konten asli dokumen</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-4">
                            <div class="flex items-center gap-2">
                                <label for="summary-percentage" class="text-xs text-base-content/70">Kepadatan:</label>
                                <input type="range" id="summary-percentage" min="20" max="80" value="30" step="1" class="range range-xs range-primary w-24" oninput="document.getElementById('pct-val').textContent = this.value + '%'" />
                                <span id="pct-val" class="text-xs font-medium w-8">30%</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" id="summary-copy-btn" class="btn btn-ghost btn-xs border border-base-300 text-xs {{ !$hasSummary ? 'hidden' : '' }}" onclick="copySummaryText()">
                                    <svg class="w-3.5 h-3.5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                    <span id="copy-btn-label">Salin Ringkasan</span>
                                </button>
                                <button type="button" id="summary-regenerate" class="btn btn-primary btn-outline btn-xs text-xs" onclick="loadSummary(true)">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                    Ringkas Ulang
                                </button>
                            </div>
                        </div>
                    </div>

                    <div id="summary-loading" class="{{ $isProcessing ? '' : 'hidden' }} my-3">
                        <div class="summary-loading-bar mb-3" aria-hidden="true">
                            <span class="summary-loading-shimmer"></span>
                        </div>
                        <p class="text-xs text-primary font-medium inline-flex items-center gap-2">
                            <span class="loading loading-spinner loading-xs"></span>
                            AI sedang membaca & meringkas dokumen... Mohon tunggu sebentar.
                        </p>
                    </div>

                    <div id="summary-body-wrapper" class="{{ !$hasSummary || $isProcessing ? 'hidden' : '' }}">
                        <div id="summary-body" class="bg-base-200/50 p-4 sm:p-5 rounded-xl border border-base-300/80 text-sm sm:text-base font-normal text-base-content leading-relaxed space-y-2">
                            @if($hasSummary)
                                {!! nl2br(e($document->summary)) !!}
                            @endif
                        </div>
                    </div>

                    <div id="summary-error" class="{{ $isFailed ? '' : 'hidden' }} alert alert-error text-sm mt-3">
                        <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <span>{{ $document->summary_error ?? 'Ringkasan gagal dibuat. Silakan coba lagi.' }}</span>
                    </div>
                </div>
            </div>

            <div class="card bg-base-100 border border-base-300 shadow-sm mb-6">
                <div class="card-body p-0">
                    @php $display = $document->displayVersion(); @endphp
                    @if($display && $display->file_path)
                        @include('documents._file-preview', ['document' => $document, 'version' => $display])
                    @elseif($display)
                        @include('documents._paper', [
                            'content' => $display->content,
                            'document' => $document,
                            'liveStorage' => 'doc-preview-' . $document->id,
                            'paperSize' => $document->paper_size ?? 'A4',
                            'paperMargin' => $document->paper_margin,
                        ])
                    @else
                        <p class="text-base-content/60 italic p-4 sm:p-6">No approved content yet.</p>
                    @endif
                </div>
            </div>

            @if($errors->has('export'))
                <div class="alert alert-error mb-6">
                    <span>{{ $errors->first('export') }} Silakan coba lagi.</span>
                </div>
            @endif

            {{-- Bagikan Modal (Google Docs model) --}}
            <dialog id="share-modal" class="modal">
                <div class="modal-box max-w-xl max-h-[85vh] overflow-y-auto">
                    <div class="flex flex-wrap items-center justify-between mb-4">
                        <h3 class="font-semibold">Bagikan "{{ $document->title }}"</h3>
                        <button type="button" class="btn btn-ghost btn-sm btn-circle" onclick="document.getElementById('share-modal').close()">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    {{-- Invite search --}}
                    <div class="form-control mb-2 relative">
                        <input id="share-search-input" type="text" placeholder="Cari nama pengguna atau divisi&hellip;"
                               class="input input-bordered w-full" autocomplete="off">
                        <div id="share-search-results" class="hidden absolute top-full left-0 right-0 z-10 mt-1 bg-base-100 border border-base-300 rounded-box shadow-lg max-h-64 overflow-y-auto"></div>
                    </div>
                    <p id="share-search-hint" class="text-xs text-base-content/50 mb-4">Tambahkan orang atau divisi untuk mengakses dokumen ini.</p>

                    {{-- People with access --}}
                    <div class="mb-5">
                        <h4 class="text-sm font-medium text-base-content/70 mb-2">Orang dengan akses</h4>
                        <div id="share-list" class="space-y-2 text-sm">
                            <div class="text-base-content/50 italic">Memuat&hellip;</div>
                        </div>
                    </div>

                    {{-- General access --}}
                    <div class="border-t border-base-200 pt-4">
                        <h4 class="text-sm font-medium text-base-content/70 mb-3">Akses umum</h4>
                        <div class="flex flex-col gap-2">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="general_access" value="restricted" class="radio radio-sm" onchange="updateGeneralAccess()">
                                <span class="text-sm">Restricted — hanya orang yang diundang</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="general_access" value="anyone_with_link" class="radio radio-sm" onchange="updateGeneralAccess()">
                                <span class="text-sm">Siapa saja yang punya link</span>
                            </label>
                            <div class="flex flex-wrap items-center gap-2 mt-2">
                                <button type="button" class="btn btn-outline btn-primary btn-sm" onclick="copyShareUrl(this)">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                                    Salin Link
                                </button>
                                <button type="button" id="regenerate-token-btn" class="btn btn-ghost btn-sm" onclick="regenerateToken(this)">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                                    Buat link baru
                                </button>
                            </div>
                            {{-- Feedback: link disalin --}}
                            <div id="share-copied-feedback" class="hidden mt-3 p-3 bg-success/10 border border-success/20 rounded-lg transition-all">
                                <p class="text-xs font-semibold text-success flex items-center gap-1.5 mb-1.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                    Link berhasil disalin
                                </p>
                                <input id="share-copied-url" type="text" class="input input-bordered input-sm w-full text-xs bg-base-100" readonly onclick="this.select()" />
                            </div>
                            {{-- Feedback: link baru dibuat --}}
                            <div id="share-regenerated-feedback" class="hidden mt-3 p-3 rounded-lg transition-all"></div>
                        </div>
                    </div>
                </div>
                <form method="dialog" class="modal-backdrop">
                    <button>close</button>
                </form>
            </dialog>

            {{-- Export PDF Modal — pilih ukuran kertas HANYA untuk export ini
                 (tidak mengubah paper_size tersimpan di dokumen). Margin ikut
                 margin dokumen; kalau tidak muat di kertas yang dipilih,
                 PdfExportService akan meng-clamp-nya otomatis (lihat
                 clampMarginToPage() di resources/js/jodit.js — logikanya
                 sengaja dibuat identik dengan PdfExportService::buildHtml()). --}}
            @if(!$isFileBased)
                <dialog id="export-pdf-modal" class="modal">
                <div class="modal-box max-w-sm max-h-[85vh] overflow-y-auto">
                        <div class="flex flex-wrap items-center justify-between mb-4">
                            <h3 class="font-semibold">Export ke PDF</h3>
                            <button type="button" class="btn btn-ghost btn-sm btn-circle" onclick="document.getElementById('export-pdf-modal').close()">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <form method="POST" action="{{ route('documents.export-pdf', $document) }}"
                              onsubmit="this.querySelector('button[type=submit]').disabled = true;
                                        this.querySelector('button[type=submit]').classList.add('loading');
                                        this.querySelector('button[type=submit]').innerHTML = 'Membuat PDF&hellip;';
                                        return true;">
                            @csrf
                            <div class="form-control w-full mb-2">
                                <label class="label"><span class="label-text font-medium">Ukuran Kertas</span></label>
                                <select name="paper_size" class="select select-bordered w-full">
                                    @foreach(['A4','A5','A3','Letter','Legal'] as $size)
                                        <option value="{{ $size }}" {{ ($document->paper_size ?? 'A4') === $size ? 'selected' : '' }}>{{ $size }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <p class="text-xs text-base-content/50 mb-4">
                                Margin tetap mengikuti margin dokumen saat ini; kalau tidak muat di kertas yang dipilih, margin akan disesuaikan otomatis.
                            </p>
                            <div class="flex flex-wrap justify-end gap-2">
                                <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('export-pdf-modal').close()">{{ __('Batal') }}</button>
                                <button type="submit" class="btn btn-primary btn-sm">{{ __('Export') }}</button>
                            </div>
                        </form>
                    </div>
                    <form method="dialog" class="modal-backdrop">
                        <button>close</button>
                    </form>
                </dialog>
            @endif

        </div>
    </div>

    {{-- Ringkasan Dokumen: card di atas preview, bukan modal --}}
    <style>
        .summary-loading-bar {
            position: relative;
            height: 6px;
            border-radius: 9999px;
            background: var(--fallback-bc, oklch(0.278 0.033 256.848)) / 0.15;
            background: color-mix(in oklab, var(--fallback-bc, oklch(0.278 0.033 256.848)) 15%, transparent);
            overflow: hidden;
        }
        .summary-loading-shimmer {
            position: absolute;
            inset: 0;
            width: 40%;
            border-radius: 9999px;
            background: linear-gradient(90deg, transparent, var(--fallback-p, oklch(0.546 0.245 262.881)), transparent);
            animation: summary-shimmer 1.2s ease-in-out infinite;
        }
        @keyframes summary-shimmer {
            0%   { transform: translateX(-100%); }
            100% { transform: translateX(350%); }
        }
    </style>

    <script>
        const SUMMARY_KEY = 'dokuflow:summary:{{ $document->id }}';
        const SUMMARY_STATUS_URL = '{{ route('documents.summary-status', $document) }}';
        const SUMMARY_START_URL = '{{ route('documents.summarize', $document) }}';

        /**
         * Render teks ringkasan sebagai paragraf HTML sederhana.
         * Memecah berdasarkan baris kosong, lalu setiap blok jadi satu <p>.
         */
        function renderParagraphs(text) {
            if (!text) return '';
            return text
                .split(/\n\s*\n/)
                .map(p => p.trim())
                .filter(p => p.length > 0)
                .map(p => {
                    // Escape HTML
                    let safe = p.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
                    
                    // Render bold (**text**)
                    safe = safe.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
                    
                    // Render bullet list (* item)
                    let lines = safe.split('\n');
                    let isList = false;
                    for (let i = 0; i < lines.length; i++) {
                        if (lines[i].match(/^(\*|-)\s+/)) {
                            lines[i] = '<li class="ml-5 list-disc">' + lines[i].replace(/^(\*|-)\s+/, '') + '</li>';
                            isList = true;
                        }
                    }
                    
                    if (isList) {
                        return '<ul class="mb-3 last:mb-0 leading-relaxed text-base-content space-y-1">' + lines.join('\n') + '</ul>';
                    }

                    // Biarkan newline dalam paragraf biasa jadi <br>
                    safe = safe.replace(/\n/g, '<br>');
                    return '<p class="mb-3 last:mb-0 leading-relaxed text-base-content">' + safe + '</p>';
                })
                .join('');
        }

        function loadSummary(force = false) {
            const card = document.getElementById('summary-card');
            const bodyWrapper = document.getElementById('summary-body-wrapper');
            const loading = document.getElementById('summary-loading');
            const error = document.getElementById('summary-error');
            const copyBtn = document.getElementById('summary-copy-btn');

            if (force) localStorage.removeItem(SUMMARY_KEY);

            card.classList.remove('hidden');
            bodyWrapper.classList.add('hidden');
            error.classList.add('hidden');
            loading.classList.remove('hidden');
            if (copyBtn) copyBtn.classList.add('hidden');

            card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

            const percentage = parseInt(document.getElementById('summary-percentage')?.value || 30);

            fetch(SUMMARY_START_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ force: force, percentage: percentage }),
            })
            .then(r => r.json().then(data => ({ ok: r.ok, data })))
            .then(({ ok, data }) => {
                if (!ok) throw new Error(data.error || 'Gagal memulai ringkasan.');
                if (data.status === 'completed' && data.summary) { finishSummary(data.summary); return; }
                if (data.status === 'failed') { showError(data.error); return; }
                pollSummary();
            })
            .catch(err => showError(err.message));
        }

        function pollSummary() {
            fetch(SUMMARY_STATUS_URL, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'completed' && data.summary) { finishSummary(data.summary); return; }
                if (data.status === 'failed') { showError(data.error); return; }
                setTimeout(pollSummary, 2000);
            })
            .catch(() => setTimeout(pollSummary, 2000));
        }

        function finishSummary(summary) {
            document.getElementById('summary-loading').classList.add('hidden');
            localStorage.setItem(SUMMARY_KEY, summary);
            const body = document.getElementById('summary-body');
            body.innerHTML = renderParagraphs(summary);
            document.getElementById('summary-body-wrapper').classList.remove('hidden');
            const copyBtn = document.getElementById('summary-copy-btn');
            if (copyBtn) copyBtn.classList.remove('hidden');
        }

        function showError(msg) {
            document.getElementById('summary-loading').classList.add('hidden');
            const error = document.getElementById('summary-error');
            const span = error.querySelector('span');
            if (span) span.textContent = msg || 'Ringkasan gagal dibuat. Silakan coba lagi.';
            error.classList.remove('hidden');
        }

        function copySummaryText() {
            const text = localStorage.getItem(SUMMARY_KEY) || document.getElementById('summary-body').innerText;
            const label = document.getElementById('copy-btn-label');
            navigator.clipboard.writeText(text).then(() => {
                label.textContent = 'Tersalin!';
                setTimeout(() => { label.textContent = 'Salin'; }, 2000);
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            const saved = {!! json_encode($document->summary) !!};
            if (saved) {
                const body = document.getElementById('summary-body');
                if (body) body.innerHTML = renderParagraphs(saved);
            }
            @if($document->summary_status === 'processing')
                pollSummary();
            @endif
        });
    </script>

    {{-- Share link modal (reusable) --}}
    <style>
        #share-link-modal::backdrop { background: rgba(0, 0, 0, 0.5); }
    </style>
    <script>
        @if($errors->has('file'))
            document.getElementById('upload-version-modal').showModal();
        @endif

        // ---- Bagikan modal (Google Docs model) ----
        const shareDataUrl = @json(route('shares.data', $document));
        const shareStoreUrl = @json(route('shares.store', $document));
        const shareSearchUrl = @json(route('shares.search', $document));
        const shareGeneralUrl = @json(route('shares.general-access.update', $document));
        const shareRegenUrl = @json(route('shares.regenerate-token', $document));
        let shareState = null;

        async function openShareModal() {
            document.getElementById('share-modal').showModal();
            await loadShareData();
        }

        async function loadShareData() {
            const list = document.getElementById('share-list');
            list.innerHTML = '<div class="text-base-content/50 italic">Memuat&hellip;</div>';
            try {
                const res = await fetch(shareDataUrl, { headers: { 'Accept': 'application/json' } });
                shareState = await res.json();
                renderShareList();
                renderGeneralAccess();
            } catch (e) {
                list.innerHTML = '<div class="text-error">Gagal memuat data akses.</div>';
            }
        }

        function renderShareList() {
            const list = document.getElementById('share-list');
            const rows = [];

            rows.push(`<div class="flex items-center justify-between gap-2 py-1">
                <div class="min-w-0">
                    <p class="font-medium truncate">${escapeHtml(shareState.owner.name)}</p>
                    <p class="text-xs text-base-content/50">Pemilik</p>
                </div>
                <span class="badge badge-primary badge-sm shrink-0">owner</span>
            </div>`);

            shareState.shares.forEach(s => {
                rows.push(`<div class="flex items-center justify-between gap-2 py-1">
                    <div class="min-w-0">
                        <p class="font-medium truncate">${escapeHtml(s.name)}</p>
                        <p class="text-xs text-base-content/50 truncate">${escapeHtml(s.email)}</p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <select class="select select-bordered select-xs" onchange="updateUserShare(${s.id}, this.value)">
                            <option value="viewer" ${s.role === 'viewer' ? 'selected' : ''}>Viewer</option>
                            <option value="editor" ${s.role === 'editor' ? 'selected' : ''}>Editor</option>
                        </select>
                        <button type="button" class="text-error hover:underline text-xs" onclick="removeUserShare(${s.id})">Hapus</button>
                    </div>
                </div>`);
            });

            shareState.division_shares.forEach(s => {
                rows.push(`<div class="flex items-center justify-between gap-2 py-1">
                    <div class="min-w-0">
                        <p class="font-medium truncate">${escapeHtml(s.name)}</p>
                        <p class="text-xs text-base-content/50">Divisi</p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <select class="select select-bordered select-xs" onchange="updateDivisionShare(${s.id}, this.value)">
                            <option value="viewer" ${s.role === 'viewer' ? 'selected' : ''}>Viewer</option>
                            <option value="editor" ${s.role === 'editor' ? 'selected' : ''}>Editor</option>
                        </select>
                        <button type="button" class="text-error hover:underline text-xs" onclick="removeDivisionShare(${s.id})">Hapus</button>
                    </div>
                </div>`);
            });

            list.innerHTML = rows.join('') || '<div class="text-base-content/50 italic">Belum ada akses lain.</div>';
        }

        function renderGeneralAccess() {
            const restricted = document.querySelector('input[name="general_access"][value="restricted"]');
            const anyone = document.querySelector('input[name="general_access"][value="anyone_with_link"]');
            if (shareState.general_access === 'anyone_with_link') {
                anyone.checked = true;
            } else {
                restricted.checked = true;
            }
        }

        function escapeHtml(str) {
            return String(str ?? '').replace(/[&<>"']/g, c => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            }[c]));
        }

        async function postForm(url, data) {
            const body = new URLSearchParams(data);
            body.append('_token', document.querySelector('meta[name="csrf-token"]')?.content ?? '');
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
                body,
            });
            if (!res.ok) throw new Error('Request failed');
        }

        async function updateUserShare(id, role) {
            const url = @json(route('shares.update', [$document, '__id__'])).replace('__id__', id);
            await postForm(url, { _method: 'PATCH', role });
            await loadShareData();
        }

        async function removeUserShare(id) {
            const url = @json(route('shares.destroy', [$document, '__id__'])).replace('__id__', id);
            await postForm(url, { _method: 'DELETE' });
            await loadShareData();
        }

        async function updateDivisionShare(id, role) {
            const url = @json(route('shares.division.update', [$document, '__id__'])).replace('__id__', id);
            await postForm(url, { _method: 'PATCH', role });
            await loadShareData();
        }

        async function removeDivisionShare(id) {
            const url = @json(route('shares.division.destroy', [$document, '__id__'])).replace('__id__', id);
            await postForm(url, { _method: 'DELETE' });
            await loadShareData();
        }

        async function updateGeneralAccess() {
            const access = document.querySelector('input[name="general_access"]:checked').value;
            await postForm(shareGeneralUrl, { _method: 'PATCH', general_access: access });
            await loadShareData();
        }

        async function regenerateToken(btn) {
            if (!btn) btn = document.getElementById('regenerate-token-btn');
            const feedbackDiv = document.getElementById('share-regenerated-feedback');

            // Save original button content and show loading state
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="loading loading-spinner loading-xs"></span> Memproses…';
            feedbackDiv.classList.add('hidden');

            try {
                const res = await fetch(shareRegenUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                });

                if (!res.ok) {
                    const errData = await res.json().catch(() => ({}));
                    throw new Error(errData.message || 'Gagal membuat link baru.');
                }

                const data = await res.json();

                // Update the frontend state immediately so "Salin Link" copies the new URL
                if (shareState) {
                    shareState.share_token = data.share_token;
                    shareState.share_url = data.share_url;
                }

                // Show success feedback
                feedbackDiv.className = 'mt-3 p-3 bg-success/10 border border-success/20 rounded-lg transition-all';
                feedbackDiv.innerHTML = `
                    <p class="text-xs font-semibold text-success flex items-center gap-1.5 mb-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        Link baru berhasil dibuat
                    </p>
                    <input type="text" class="input input-bordered input-sm w-full text-xs bg-base-100" readonly value="${escapeHtml(data.share_url)}" onclick="this.select()" />
                `;
                feedbackDiv.classList.remove('hidden');

                // Auto-hide after 5 seconds
                setTimeout(() => { feedbackDiv.classList.add('hidden'); }, 5000);

                // Also refresh the full share data to stay in sync
                await loadShareData();
            } catch (err) {
                // Show error feedback
                feedbackDiv.className = 'mt-3 p-3 bg-error/10 border border-error/20 rounded-lg transition-all';
                feedbackDiv.innerHTML = `
                    <p class="text-xs font-semibold text-error flex items-center gap-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        ${escapeHtml(err.message)}
                    </p>
                `;
                feedbackDiv.classList.remove('hidden');
                setTimeout(() => { feedbackDiv.classList.add('hidden'); }, 5000);
            } finally {
                // Restore button
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }

        function copyShareUrl(btn) {
            if (!shareState?.share_url) return;

            const fallbackCopy = () => {
                prompt("Gagal menyalin otomatis. Silakan salin link berikut secara manual:", shareState.share_url);
            };

            const showFeedback = () => {
                const feedbackDiv = document.getElementById('share-copied-feedback');
                const inputUrl = document.getElementById('share-copied-url');
                
                inputUrl.value = shareState.share_url;
                feedbackDiv.classList.remove('hidden');
                
                // Ubah state tombol
                const originalHtml = btn.innerHTML;
                btn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg> Tersalin`;
                btn.classList.add('btn-success', 'text-success-content');
                btn.classList.remove('btn-outline', 'btn-primary');

                setTimeout(() => {
                    feedbackDiv.classList.add('hidden');
                    btn.innerHTML = originalHtml;
                    btn.classList.remove('btn-success', 'text-success-content');
                    btn.classList.add('btn-outline', 'btn-primary');
                }, 3000);
            };

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(shareState.share_url)
                    .then(showFeedback)
                    .catch(fallbackCopy);
            } else {
                fallbackCopy();
            }
        }

        // Invite autocomplete
        const searchInput = document.getElementById('share-search-input');
        const searchResults = document.getElementById('share-search-results');
        let searchTimer = null;

        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(async () => {
                const q = searchInput.value.trim();
                if (q.length < 1) { searchResults.classList.add('hidden'); return; }
                const res = await fetch(shareSearchUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                renderSearchResults(data);
            }, 250);
        });

        function renderSearchResults(data) {
            const items = [];
            data.users.forEach(u => {
                items.push(`<button type="button" class="w-full text-left px-3 py-2 hover:bg-base-200 flex items-center justify-between gap-2"
                    onclick="inviteUser(${u.id}, '${escapeHtml(u.name).replace(/'/g, "\\'")}')">
                    <span class="min-w-0"><span class="font-medium">${escapeHtml(u.name)}</span>
                    <span class="text-xs text-base-content/50 block truncate">${escapeHtml(u.email)}</span></span>
                    <span class="badge badge-ghost badge-sm shrink-0">Pengguna</span>
                </button>`);
            });
            data.divisions.forEach(d => {
                items.push(`<button type="button" class="w-full text-left px-3 py-2 hover:bg-base-200 flex items-center justify-between gap-2"
                    onclick="inviteDivision(${d.id}, '${escapeHtml(d.name).replace(/'/g, "\\'")}')">
                    <span class="font-medium">${escapeHtml(d.name)}</span>
                    <span class="badge badge-ghost badge-sm shrink-0">Divisi</span>
                </button>`);
            });
            searchResults.innerHTML = items.join('') || '<div class="px-3 py-2 text-base-content/50">Tidak ditemukan.</div>';
            searchResults.classList.remove('hidden');
        }

        async function inviteUser(id, name) {
            await postForm(shareStoreUrl, { type: 'user', user_id: id, role: 'viewer' });
            searchInput.value = '';
            searchResults.classList.add('hidden');
            await loadShareData();
        }

        async function inviteDivision(id, name) {
            await postForm(shareStoreUrl, { type: 'division', division_id: id, role: 'viewer' });
            searchInput.value = '';
            searchResults.classList.add('hidden');
            await loadShareData();
        }

        document.addEventListener('click', (e) => {
            if (!searchResults.contains(e.target) && e.target !== searchInput) {
                searchResults.classList.add('hidden');
            }
        });
    </script>

    {{-- Version History modal --}}
    <dialog id="version-modal" class="modal">
        <div class="modal-box max-w-2xl max-h-[85vh] overflow-y-auto">
            <div class="flex flex-wrap items-center justify-between mb-4">
                <h3 class="font-semibold">Version History</h3>
                <button type="button" class="btn btn-ghost btn-sm btn-circle" onclick="document.getElementById('version-modal').close()">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            @if($document->hasPendingRollback())
                <div class="alert alert-warning alert-sm mb-3 text-xs">
                    Rollback ke v{{ $document->pendingRollbackVersion->version_number }} sedang menunggu approval — opsi rollback lain dinonaktifkan sementara.
                </div>
            @endif
            @forelse($document->versions->sortByDesc('version_number') as $version)
                <div class="flex flex-wrap items-center justify-between gap-2 py-2 border-b border-base-200 text-sm">
                    <div class="min-w-0">
                        <span class="font-medium">v{{ $version->version_number }}</span>
                        @if($version->file_path)
                            <span class="badge badge-ghost badge-sm ml-1">Berkas</span>
                        @endif
                        <span class="text-base-content/60">by {{ $version->author_name }}</span>
                        <span class="text-base-content/40">{{ $version->created_at->format('M d, Y H:i') }}</span>
                        @if($version->id === $document->current_version_id)
                            <span class="badge badge-success badge-sm ml-2">Active</span>
                        @elseif($version->status === 'inactive')
                            <span class="badge badge-neutral badge-sm ml-2">Inactive</span>
                        @elseif($version->status === 'pending')
                            <span class="badge badge-warning badge-sm ml-2">Pending</span>
                        @elseif($version->status === 'discarded' || $version->discarded_at)
                            <span class="badge badge-neutral badge-sm ml-2">Discarded</span>
                        @elseif($version->status === 'rejected')
                            <span class="badge badge-error badge-sm ml-2">Rejected</span>
                        @endif
                        @if($document->hasPendingRollback() && $document->pending_rollback_version_id === $version->id)
                            <span class="badge badge-warning badge-sm ml-2">Target Rollback</span>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-2 shrink-0">
                        <a href="{{ route('documents.preview-version', [$document, $version]) }}"
                           class="btn btn-ghost btn-xs">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                            Preview
                        </a>
                        @can('update', $document)
                            @if($version->id !== $document->current_version_id
                                && $version->status !== 'pending'
                                && !($version->status === 'discarded' || $version->discarded_at)
                                && !$document->hasPendingRollback())
                                <button type="button"
                                        class="btn btn-outline btn-warning btn-xs"
                                        onclick="document.getElementById('version-modal').close(); window.dispatchEvent(new CustomEvent('open-modal', { detail: 'confirm-rollback-{{ $version->id }}' }))">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                                    Rollback
                                </button>
                            @endif
                        @endcan
                    </div>
                </div>
            @empty
                <p class="text-base-content/60 text-sm">No versions yet.</p>
            @endforelse
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>

    {{-- Change scope modal --}}
    <dialog id="scope-modal" class="modal">
        <div class="modal-box max-w-sm max-h-[85vh] overflow-y-auto">
            <div class="flex flex-wrap items-center justify-between mb-4">
                <h3 class="font-semibold">Change Scope</h3>
                <button type="button" class="btn btn-ghost btn-sm btn-circle" onclick="document.getElementById('scope-modal').close()">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <form method="POST" action="{{ route('documents.update-visibility', $document) }}" class="space-y-3">
                @csrf
                @method('PATCH')
                <label class="label cursor-pointer justify-start gap-3 rounded-lg border border-base-300 p-3 hover:bg-base-200/50">
                    <input type="radio" name="visibility" value="general" class="radio radio-sm radio-primary"
                           {{ $document->isGeneral() ? 'checked' : '' }}>
                    <span class="block min-w-0">
                        <span class="block font-medium text-sm">General (public)</span>
                        <span class="block text-xs text-base-content/60">Terlihat oleh semua pengguna.</span>
                    </span>
                </label>
                <label class="label cursor-pointer justify-start gap-3 rounded-lg border border-base-300 p-3 hover:bg-base-200/50">
                    <input type="radio" name="visibility" value="division" class="radio radio-sm radio-primary"
                           {{ $document->isDivision() ? 'checked' : '' }}>
                    <span class="block min-w-0">
                        <span class="block font-medium text-sm">Division only</span>
                        <span class="block text-xs text-base-content/60">Hanya divisi {{ $document->division?->code ?? '' }} yang bisa melihat.</span>
                    </span>
                </label>
                <label class="label cursor-pointer justify-start gap-3 rounded-lg border border-base-300 p-3 hover:bg-base-200/50">
                    <input type="radio" name="visibility" value="personal" class="radio radio-sm radio-primary"
                           {{ $document->isPersonal() ? 'checked' : '' }}>
                    <span class="block min-w-0">
                        <span class="block font-medium text-sm">Personal</span>
                        <span class="block text-xs text-base-content/60">Hanya kamu yang bisa melihat.</span>
                    </span>
                </label>
                <div class="flex flex-wrap justify-end gap-2 pt-2">
                    <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('scope-modal').close()">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        Save
                    </button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>

    {{-- Edit restricted modal (dokumen berbasis unggahan) --}}
    <dialog id="edit-restricted-modal" class="modal">
        <div class="modal-box max-w-md max-h-[85vh] overflow-y-auto">
            <div class="flex flex-wrap items-center justify-between mb-4">
                <h3 class="font-semibold">{{ __('Dokumen Tidak Dapat Diedit Langsung') }}</h3>
                <button type="button" class="btn btn-ghost btn-sm btn-circle" onclick="document.getElementById('edit-restricted-modal').close()">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <p class="text-sm text-base-content/70 mb-5">
                {{ __('Dokumen ini berasal dari berkas yang diunggah, bukan ditulis melalui editor, sehingga isinya tidak dapat diedit secara langsung. Terdapat dua cara untuk memperbarui dokumen:') }}
            </p>
            <ul class="text-sm space-y-2 mb-5 list-disc list-inside text-base-content/80">
                <li><span class="font-medium">{{ __('Rollback') }}</span> {{ __('ke versi sebelumnya yang masih tersimpan.') }}</li>
                <li><span class="font-medium">{{ __('Unggah versi terbaru') }}</span> {{ __('untuk menggantikan isi dokumen saat ini.') }}</li>
            </ul>
            <div class="flex flex-wrap justify-end gap-2">
                <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('edit-restricted-modal').close(); document.getElementById('version-modal').showModal();">
                    {{ __('Lihat Versi') }}
                </button>
                <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('edit-restricted-modal').close(); document.getElementById('upload-version-modal').showModal();">
                    {{ __('Unggah Versi Terbaru') }}
                </button>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>

    {{-- Upload new version modal --}}
    <dialog id="upload-version-modal" class="modal">
        <div class="modal-box max-w-md max-h-[85vh] overflow-y-auto">
            <div class="flex flex-wrap items-center justify-between mb-4">
                <h3 class="font-semibold">{{ __('Unggah Versi Terbaru') }}</h3>
                <button type="button" class="btn btn-ghost btn-sm btn-circle" onclick="document.getElementById('upload-version-modal').close()">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <form method="POST" action="{{ route('documents.upload-version', $document) }}" enctype="multipart/form-data">
                @csrf
                <div class="form-control w-full mb-4">
                    <label for="upload-version-file" class="label">
                        <span class="label-text font-medium">{{ __('Berkas Pengganti') }}</span>
                    </label>
                    <input type="file" name="file" id="upload-version-file" accept=".pdf,.docx" class="file-input file-input-bordered w-full" required>
                    <p class="text-xs text-base-content/50 mt-1">{{ __('Hanya PDF atau DOCX, maksimal 10MB. Versi baru akan menunggu approval kepala divisi.') }}</p>
                    @error('file') <p class="text-sm text-error mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-wrap justify-end gap-2">
                    <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('upload-version-modal').close()">{{ __('Batal') }}</button>
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('Unggah') }}</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>
</x-app-layout>