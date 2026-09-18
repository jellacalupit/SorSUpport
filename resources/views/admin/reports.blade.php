<x-app-layout :role="'admin'" title="Reports">
    <div class="grid gap-5 lg:grid-cols-[1fr_1.4fr]">
        <x-page-section title="Generate a report">
            <form method="GET" action="{{ route('admin.analytics.export.pdf') }}" class="grid gap-4">
                <div class="grid gap-1.5">
                    <label for="report-from" class="text-sm font-medium">From</label>
                    <input id="report-from" type="date" name="start_date" class="h-9 w-full min-w-[10.5rem] rounded-md border border-input bg-transparent px-3 text-sm focus:outline-none focus:ring-1 focus:ring-ring" />
                </div>
                <div class="grid gap-1.5">
                    <label for="report-to" class="text-sm font-medium">To</label>
                    <input id="report-to" type="date" name="end_date" class="h-9 w-full min-w-[10.5rem] rounded-md border border-input bg-transparent px-3 text-sm focus:outline-none focus:ring-1 focus:ring-ring" />
                </div>
                <div class="grid gap-1.5">
                    <label for="report-category" class="text-sm font-medium">Category</label>
                    <details x-data="{}" class="group relative" x-on:click.outside="$el.removeAttribute('open')">
                        <summary id="report-category" class="flex h-9 cursor-pointer list-none items-center justify-between rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm transition-colors hover:bg-muted [&::-webkit-details-marker]:hidden">
                            <span class="truncate">All categories</span><svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                        </summary>
                        <div class="absolute top-full z-50 mt-1 max-h-72 w-full overflow-y-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                            <button type="button" data-category-value="" class="flex w-full items-center rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent hover:text-accent-foreground">All categories</button>
                            @foreach ($categoryOptions as $category)
                                <button type="button" data-category-value="{{ $category->id }}" data-category-label="{{ $category->name }}" class="flex w-full items-center rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent hover:text-accent-foreground">{{ $category->name }}</button>
                            @endforeach
                        </div>
                    </details>
                    <input type="hidden" id="report-category-value" name="category_id" value="" />
                </div>
                <div class="grid gap-2 sm:grid-cols-2">
                    <button type="submit" formaction="{{ route('admin.analytics.export.pdf') }}" class="inline-flex h-9 items-center justify-center gap-1.5 rounded-md bg-primary px-3 text-sm font-semibold text-primary-foreground hover:bg-primary/90"><x-icons.file-text class="h-4 w-4" /> Generate PDF</button>
                    <button type="submit" formaction="{{ route('admin.analytics.export.excel') }}" class="inline-flex h-9 items-center justify-center gap-1.5 rounded-md border border-border bg-background px-3 text-sm font-semibold hover:bg-muted"><x-icons.file-spreadsheet class="h-4 w-4" /> Generate Excel</button>
                </div>
            </form>
        </x-page-section>

        <x-page-section title="Previously generated reports">
            <div class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground">
                No reports generated yet. Generate a PDF or Excel report to download the current complaint data.
            </div>
        </x-page-section>
    </div>

    <script>
        document.querySelectorAll('[data-category-value]').forEach((option) => {
            option.addEventListener('click', () => {
                document.getElementById('report-category-value').value = option.dataset.categoryValue ?? '';
                option.closest('details').querySelector('summary span').textContent = option.dataset.categoryLabel ?? 'All categories';
                option.closest('details').removeAttribute('open');
            });
        });
    </script>
</x-app-layout>
