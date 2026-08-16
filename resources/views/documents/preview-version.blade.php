<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-2 justify-between">
            <span class="min-w-0 truncate">{{ $document->title }} — v{{ $version->version_number }}</span>
            <span class="text-sm font-normal text-base-content/60 shrink-0">{{ $document->document_number }}</span>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto w-full px-0">
            <div class="card bg-base-100 border border-base-300 shadow-sm">
                <div class="card-body">
                    <div class="flex flex-wrap justify-between items-center gap-3 mb-4 pb-4 border-b border-base-300">
                        <div class="text-sm">
                            <div><span class="text-base-content/60">Version:</span> v{{ $version->version_number }}</div>
                            <div><span class="text-base-content/60">Author:</span> {{ $version->author_name }}</div>
                            <div><span class="text-base-content/60">Status:</span>
                                @if($version->id === $document->current_version_id)
                                    <span class="badge badge-success badge-sm">Active</span>
                                @elseif($version->status === 'inactive')
                                    <span class="badge badge-neutral badge-sm">Inactive</span>
                                @elseif($version->status === 'pending')
                                    <span class="badge badge-warning badge-sm">Pending</span>
                                @elseif($version->status === 'discarded' || $version->discarded_at)
                                    <span class="badge badge-neutral badge-sm">Discarded</span>
                                @elseif($version->status === 'rejected')
                                    <span class="badge badge-error badge-sm">Rejected</span>
                                @else
                                    <span class="badge badge-ghost badge-sm">{{ $version->status }}</span>
                                @endif
                            </div>
                        </div>
                        <a href="{{ route('documents.show', $document) }}" class="btn btn-ghost btn-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                            Back
                        </a>
                    </div>

                    @if($version->file_path)
                        @include('documents._file-preview', ['document' => $document, 'version' => $version])
                    @else
                        @include('documents._paper', ['content' => $version->content ?? '', 'document' => $document])
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>