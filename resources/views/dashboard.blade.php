<x-app-layout>
    <x-slot name="header">{{ __('Dashboard') }}</x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto w-full">
            @if(auth()->user()->isAdmin())
                {{-- Admin dashboard: General Dokumen list (tab hidden from admin navbar) --}}
                @include('documents._search', ['type' => 'general'])

                @if(session('success'))
                    <div class="alert alert-success mb-4">
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                <div class="card bg-base-100 border border-base-300 shadow-sm">
                    <div class="card-body p-0">
                        <div class="divide-y divide-base-200">
                            @forelse($documents as $doc)
                                @include('documents._list', ['doc' => $doc])
                            @empty
                                <div class="p-4 sm:p-6 text-base-content/60">{{ __('Tidak ada dokumen.') }}</div>
                            @endforelse
                        </div>
                        @if($documents->hasPages())
                            <div class="p-4 border-t border-base-200">
                                {{ $documents->links() }}
                            </div>
                        @endif                    </div>
                </div>
            @else
                {{-- Non-admin dashboard: stats + recent documents --}}
                <div class="max-w-7xl mx-auto w-full space-y-6">
                    <!-- Search -->
                    <form method="GET" action="{{ route('dashboard') }}" class="card bg-base-100 border border-base-300 rounded-box p-4">
                        <div class="flex flex-col sm:flex-row gap-2">
                            <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Cari judul atau nomor...') }}"
                                   class="input input-bordered input-sm w-full sm:flex-1">
                            <select name="document_type_id" class="select select-bordered select-sm w-full sm:w-auto" onchange="this.form.submit()">
                                <option value="">{{ __('Semua tipe') }}</option>
                                @foreach($documentTypes as $dt)
                                    <option value="{{ $dt->id }}" {{ request('document_type_id') == $dt->id ? 'selected' : '' }}>
                                        {{ $dt->code }} - {{ $dt->name }}
                                    </option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-outline btn-primary btn-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                                {{ __('Cari') }}
                            </button>
                            @if(request('search') || request('document_type_id'))
                                <a href="{{ route('dashboard') }}" class="btn btn-ghost btn-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                    {{ __('Bersihkan') }}
                                </a>
                            @endif
                        </div>
                    </form>

                    @if($results)
                        <!-- Search Results -->
                        <div class="bg-base-100 border border-base-300 rounded-box">
                            <div class="px-4 sm:px-5 py-4 border-b border-base-300 flex flex-wrap items-center justify-between gap-2">
                                <h2 class="font-semibold text-base-content">{{ __('Hasil Pencarian') }}</h2>
                                <span class="text-sm text-base-content/50">{{ $results->total() }} {{ __('ditemukan') }}</span>
                            </div>
                            <div class="divide-y divide-base-200">
                                @forelse($results as $doc)
                                    <div class="px-4 sm:px-5 py-3.5 flex flex-wrap items-center justify-between gap-2 hover:bg-base-50 transition-colors">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <svg class="w-8 h-8 shrink-0 text-base-content/20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                            <div class="min-w-0">
                                                <a href="{{ route('documents.show', $doc) }}" class="text-sm font-medium text-base-content hover:text-primary truncate block">{{ $doc->title }}</a>
                                                <p class="text-xs text-base-content/40 mt-0.5">
                                                    {{ $doc->document_number }} · {{ $doc->division?->code ?? '—' }} · {{ $doc->owner->name }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-3 shrink-0">
                                            <a href="{{ route('documents.preview', $doc) }}" title="{{ __('Pratinjau') }}" class="inline-flex items-center justify-center w-6 h-6 rounded-full text-base-content/60 hover:text-base-content hover:bg-base-200">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </a>
                                            <div class="text-xs text-base-content/40 shrink-0">
                                                @if($doc->isGeneral()) <span class="text-success">{{ __('Umum') }}</span>
                                                @elseif($doc->isPersonal()) <span class="text-info">{{ __('Personal') }}</span>
                                                @else {{ $doc->division?->code }} @endif
                                                · {{ $doc->created_at->diffForHumans() }}
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="px-4 sm:px-5 py-10 text-center text-sm text-base-content/50">{{ __('Tidak ada dokumen yang cocok') }} "{{ request('search') }}".</div>
                                @endforelse
                            </div>
                            @if($results->hasPages())
                                <div class="p-4 border-t border-base-300">{{ $results->links() }}</div>
                            @endif
                        </div>
                    @endif

                    <!-- Stats -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="stat bg-base-100 border border-base-300 rounded-box p-4">
                            <div class="stat-title text-base-content/50 text-xs font-medium">{{ __('Total Dokumen') }}</div>
                            <div class="stat-value text-2xl font-bold text-primary mt-1">{{ auth()->user()->documents()->count() }}</div>
                            <div class="stat-desc text-xs text-base-content/40 mt-1">{{ __('Sepanjang waktu') }}</div>
                        </div>
                        <div class="stat bg-base-100 border border-base-300 rounded-box p-4">
                            <div class="stat-title text-base-content/50 text-xs font-medium">{{ __('Dokumen Aktif') }}</div>
                            <div class="stat-value text-2xl font-bold text-primary mt-1">
                                {{ auth()->user()->documents()->whereHas('currentVersion', fn($q) => $q->where('status', 'active'))->count() }}
                            </div>
                            <div class="stat-desc text-xs text-base-content/40 mt-1">{{ __('Disetujui & diterbitkan') }}</div>
                        </div>
                        <div class="stat bg-base-100 border border-base-300 rounded-box p-4">
                            <div class="stat-title text-base-content/50 text-xs font-medium">{{ __('Menunggu Persetujuan') }}</div>
                            <div class="stat-value text-2xl font-bold text-primary mt-1">
                                {{ auth()->user()->documents()->whereHas('versions', fn($q) => $q->where('status', 'pending'))->count() }}
                            </div>
                            <div class="stat-desc text-xs text-base-content/40 mt-1">{{ __('Menunggu review kepala') }}</div>
                        </div>
                    </div>

                    <!-- Recent Documents -->
                    <div class="bg-base-100 border border-base-300 rounded-box">
                        <div class="px-4 sm:px-5 py-4 border-b border-base-300 flex flex-wrap items-center justify-between gap-2">
                            <h2 class="font-semibold text-base-content">{{ __('Dokumen Terbaru') }}</h2>
                            <a href="{{ route('documents.index', ['type' => 'mine']) }}" class="text-sm font-medium text-primary hover:text-primary/80 transition-colors">{{ __('Lihat semua') }}</a>
                        </div>
                        <div class="divide-y divide-base-200">
                            @forelse($recent as $doc)
                                <div class="px-4 sm:px-5 py-3.5 flex flex-wrap items-center justify-between gap-2 hover:bg-base-50 transition-colors">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <svg class="w-8 h-8 shrink-0 text-base-content/20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                        <div class="min-w-0">
                                            <a href="{{ route('documents.show', $doc) }}" class="text-sm font-medium text-base-content hover:text-primary truncate block">{{ $doc->title }}</a>
                                            <p class="text-xs text-base-content/40 mt-0.5">
                                                {{ $doc->document_number }} · {{ $doc->division?->code ?? '—' }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2 sm:gap-3 shrink-0">
                                        <a href="{{ route('documents.preview', $doc) }}" title="{{ __('Pratinjau') }}" class="inline-flex items-center justify-center w-6 h-6 rounded-full text-base-content/60 hover:text-base-content hover:bg-base-200">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </a>
                                        @if($doc->currentVersion)
                                            <span class="badge badge-success badge-sm gap-1">
                                                <span class="w-1 h-1 rounded-full bg-white"></span>
                                                {{ __('Aktif') }}
                                            </span>
                                        @else
                                            <span class="badge badge-warning badge-sm">{{ __('Tertunda') }}</span>
                                        @endif
                                        <span class="text-xs text-base-content/30">{{ $doc->created_at->diffForHumans() }}</span>
                                    </div>
                                </div>
                            @empty
                                <div class="px-4 sm:px-5 py-10 text-center">
                                    <svg class="w-10 h-10 mx-auto text-base-content/20 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                    <p class="text-sm text-base-content/50 mb-1">{{ __('Belum ada dokumen') }}</p>
                                    <p class="text-xs text-base-content/30">{{ __('Buat dokumen pertama Anda untuk memulai.') }}</p>
                                    <a href="{{ route('documents.create') }}" class="btn btn-primary btn-sm mt-4">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                        {{ __('Buat Dokumen') }}
                                    </a>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

</x-app-layout>