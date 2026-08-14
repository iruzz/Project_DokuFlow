<x-app-layout>

    @if(!auth()->user()->isAdmin() && !auth()->user()->isHead())
        <x-confirm-modal
            name="confirm-discard-{{ $document->id }}"
            title="Discard Document?"
            message="Are you sure you want to discard this document?"
            :action="route('documents.discard', $document)"
            method="POST"
            confirmLabel="Discard"
        />
    @endif

    @php
        $pending = $document->versions->first(fn($v) => $v->status === 'pending' && !$v->discarded_at);
        $hasDraftOnly = !$pending && !$document->currentVersion;
    @endphp

    <div class="h-full overflow-hidden bg-base-200/50 flex flex-col">

        {{-- Canvas / Dokumen --}}
        <div class="flex-1 flex flex-col min-h-0 py-4 px-2 sm:px-4">
            <div class="max-w-6xl mx-auto w-full flex-1 flex flex-col min-h-0">

                @if(session('success'))
                    <div class="mb-4">
                        <div class="alert alert-success shadow-sm">
                            <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>{{ session('success') }}</span>
                        </div>
                    </div>
                @endif

                @if($errors->any())
                    <div class="mb-4">
                        <div class="alert alert-error py-2 text-sm">
                            <span>{{ $errors->first() }}</span>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Pending version warning: saving updates the pending version in place --}}
            @php
                $pending = $document->versions->first(fn($v) => $v->status === 'pending' && !$v->discarded_at);
                $hasDraftOnly = !$pending && !$document->currentVersion;
            @endphp
            @if($pending)
                <div class="max-w-6xl mx-auto px-3 sm:px-6 pb-3">
                    <div class="alert alert-warning shadow-sm">
                        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 w-full">
                            <div class="flex items-start sm:items-center gap-2 text-sm min-w-0">
                                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                <span>
                                    Ada versi pending (v{{ $pending->version_number }}) yang belum di-review.
                                    <strong>Save akan memperbarui versi pending tersebut (tanpa versi baru).</strong>
                                </span>
                            </div>
                            @if(!auth()->user()->isAdmin() && !auth()->user()->isHead())
                                <button type="button" class="btn btn-outline btn-warning btn-sm" x-on:click="$dispatch('open-modal', 'confirm-discard-{{ $document->id }}')">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    {{ __('Discard pending (v:version)', ['version' => $pending->version_number]) }}
                                </button>
                            @else
                                <form method="POST" action="{{ route('documents.discard', $document) }}" class="shrink-0">
                                    @csrf
                                    <button type="submit" class="btn btn-outline btn-warning btn-sm">
                @endif

                {{-- Pending version warning: saving updates the pending version in place --}}
                @if($pending)
                    <div class="mb-4">
                        <div class="alert alert-warning shadow-sm">
                            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 w-full">
                                <div class="flex items-start sm:items-center gap-2 text-sm min-w-0">
                                    <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                    <span>
                                        Ada versi pending (v{{ $pending->version_number }}) yang belum di-review.
                                        <strong>Save akan memperbarui versi pending tersebut (tanpa versi baru).</strong>
                                    </span>
                                </div>
                                @if(!auth()->user()->isAdmin() && !auth()->user()->isHead())
                                    <button type="button" class="btn btn-outline btn-warning btn-sm" x-on:click="$dispatch('open-modal', 'confirm-discard-{{ $document->id }}')">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        {{ __('Discard pending (v:version)', ['version' => $pending->version_number]) }}
                                    </button>
                                @else
                                    <form method="POST" action="{{ route('documents.discard', $document) }}" class="shrink-0">
                                        @csrf
                                        <button type="submit" class="btn btn-outline btn-warning btn-sm">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            Discard pending (v{{ $pending->version_number }})
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('documents.save', $document) }}" id="editor-form" class="flex flex-col flex-1 min-h-0">
                    @csrf
                    @method('PUT')

                    {{-- Kotak gabungan: title bar + toolbar + editor Jodit jadi satu kotak --}}
                    <div id="jodit-merge-box" class="bg-base-100 rounded-xl shadow-md border border-base-300 flex flex-col flex-1 min-h-0">

                        {{-- Title/Action row --}}
                        <div class="bg-base-100 px-3 sm:px-6 py-3 flex flex-wrap items-center justify-between gap-3 rounded-t-xl">

                            <div class="flex items-center gap-3 min-w-0">
                                <svg class="w-6 h-6 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <h1 class="text-base sm:text-lg font-semibold truncate min-w-0">{{ $document->title }}</h1>
                                <span class="badge badge-ghost badge-sm hidden sm:inline-flex">
                                    {{ $document->document_number ?? '' }}
                                </span>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 shrink-0">
                                <div class="hidden md:flex items-center gap-2 pr-3 mr-1 border-r border-base-300">
                                    <div class="avatar placeholder">
                                        <div class="bg-neutral text-neutral-content rounded-full w-8">
                                            <span class="text-xs">{{ substr(auth()->user()->name, 0, 1) }}</span>
                                        </div>
                                    </div>
                                    <span class="text-sm font-medium">{{ auth()->user()->name }}</span>
                                </div>

                                <a href="{{ route('documents.show', $document) }}" class="btn btn-ghost btn-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                    Cancel
                                </a>

                                <button type="submit" form="editor-form" class="btn btn-primary btn-sm px-6">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                    Save Changes
                                </button>
                                @if($hasDraftOnly)
                                    <button type="submit" form="draft-form" class="btn btn-neutral btn-sm">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" /></svg>
                                        Save as Draft
                                    </button>
                                @endif
                            </div>
                        </div>

                        {{-- Jodit Editor (toolbar akan muncul langsung menyambung di bawah title row di atas) --}}
                        <textarea
                            name="content"
                            id="jodit-editor"
                            data-upload-url="{{ route('jodit.upload') }}"
                            data-csrf-token="{{ csrf_token() }}"
                            data-live-storage="doc-preview-{{ $document->id }}"
                            data-qr-image-url="{{ route('documents.qrcode', $document) }}"
                        >{{ $document->displayVersion()->content ?? '' }}</textarea>
                    </div>

                    <input type="hidden" name="paper_size" id="paper-size-input">
                    <input type="hidden" name="paper_margin" id="paper-margin-input">

                    <p class="text-center text-xs text-base-content/50 mt-4 px-2">
                        @if($hasDraftOnly)
                            <strong>Save Changes</strong> mengirim draft untuk approval (status jadi pending).
                        @else
                            Save akan membuat versi baru yang menunggu approval Head.
                        @endif
                        @if($pending ?? null)
                            Versi pending yang ada akan diperbarui (bukan versi baru).
                        @endif
                    </p>
                </form>

                @if($hasDraftOnly)
                    <form method="POST" action="{{ route('documents.save-draft', $document) }}" id="draft-form">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="content" id="draft-content">
                        <input type="hidden" name="paper_size" id="draft-paper-size">
                        <input type="hidden" name="paper_margin" id="draft-paper-margin">
                    </form>
                @endif

                {{-- Live preview removed --}}
            </div>
        </div>
    </div>

    <style>
        /* ── Jodit container: hapus border/shadow/radius bawaan, jadikan flex
           child yang mengisi sisa ruang #jodit-merge-box. ── */
        #jodit-merge-box .jodit-container {
            border: none !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            margin: 0 !important;
            display: flex !important;
            flex-direction: column !important;
            flex: 1 1 auto !important;
            min-height: 0 !important;
        }

        /* ── Toolbar: rata tanpa radius, TIDAK BOLEH menyusut. ── */
        #jodit-merge-box .jodit-toolbar__box,
        #jodit-merge-box .jodit-toolbar_box {
            border-radius: 0 !important;
            margin: 0 !important;
            flex-shrink: 0 !important;        /* kunci: toolbar tidak boleh collapse */
            z-index: 20 !important;
        }

        /* ── Workplace: flex-grow mengisi sisa, tapi TIDAK scroll sendiri.
           Scroll terjadi di dalam <iframe> (lihat rule berikutnya). ── */
        #jodit-merge-box .jodit-workplace {
            flex: 1 1 auto !important;
            min-height: 0 !important;
            overflow: hidden !important;       /* workplace sendiri tidak scroll */
        }

        /* ── KUNCI UTAMA: paksa <iframe> editor tinggi 100% dari workplace,
           bukan auto-grow mengikuti dokumen. Scroll terjadi di DALAM iframe
           (browser otomatis scroll isi iframe kalau kontennya lebih tinggi
           dari frame-nya). ── */
        #jodit-merge-box .jodit-workplace iframe {
            height: 100% !important;
            min-height: 0 !important;
            max-height: none !important;
        }
    </style>

    <script>
        (function () {
            // Isi hidden input draft-form dengan konten editor saat submit
            const draftForm = document.getElementById('draft-form');
            if (draftForm) {
                draftForm.addEventListener('submit', () => {
                    const ta = document.getElementById('jodit-editor');
                    const inst = window.__joditInstances?.get(ta.id);
                    document.getElementById('draft-content').value = inst ? inst.value : ta.value;

                    // FIX: draft-form tidak lewat submit handler bawaan
                    // initJoditEditor (yang cuma dipasang di form terdekat
                    // dari textarea, yaitu #editor-form) — jadi paper_size &
                    // paper_margin harus diisi manual di sini juga, dari
                    // instance editor yang sama (window.__joditInstances),
                    // supaya draft yang disimpan juga membawa margin yang
                    // sedang aktif, bukan cuma konten.
                    const sizeKey = inst && window.__findPaperKey
                        ? (window.__findPaperKey(inst.currentPaperSize) || 'A4')
                        : 'A4';
                    document.getElementById('draft-paper-size').value = sizeKey;
                    document.getElementById('draft-paper-margin').value = inst
                        ? JSON.stringify(inst.currentMargin || {})
                        : '';
                });
            }
        })();
    </script>
</x-app-layout>