<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <span>{{ $document->title }}</span>
            <span class="text-sm font-normal text-base-content/60">{{ $document->document_number }}</span>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto">
            <div class="card bg-base-100 border border-base-300 shadow-sm">
                <div class="card-body">
                    <div class="flex justify-between items-center mb-4 pb-4 border-b border-base-300">
                        <div class="text-sm">
                            <div><span class="text-base-content/60">Division:</span> {{ $document->division->code }}</div>
                            <div><span class="text-base-content/60">Owner:</span> {{ $document->owner->name }}</div>
                        </div>
                        <a href="{{ route('documents.edit', $document) }}" class="btn btn-primary btn-sm">Back to Edit</a>
                    </div>

                    @if($document->currentVersion)
                        <div class="prose max-w-none">
                            {!! $document->currentVersion->content !!}
                        </div>
                    @else
                        <p class="text-base-content/60 italic">No approved content yet.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
