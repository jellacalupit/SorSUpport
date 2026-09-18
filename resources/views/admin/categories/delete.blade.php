<x-app-layout :role="'admin'" title="Delete Category">
    <div class="max-w-xl">
        <div class="rounded-lg border border-destructive/30 bg-card p-5 shadow-sm sm:p-6">
            <h2 class="font-display text-lg font-bold text-destructive">Delete category?</h2>
            <p class="mt-2 text-sm text-muted-foreground">
                You are about to delete <span class="font-semibold text-foreground">{{ $category->name }}</span>.
                This action may also remove related complaint records and cannot be undone.
            </p>
            <div class="mt-5 flex items-center justify-end gap-2">
                <a href="{{ route('admin.settings') }}" class="inline-flex h-9 items-center rounded-md border border-input px-3 text-sm font-semibold text-foreground transition-colors hover:bg-muted">Cancel</a>
                <form method="POST" action="{{ route('admin.categories.destroy', $category) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex h-9 items-center gap-1.5 rounded-md bg-destructive px-3 text-sm font-semibold text-white transition-colors hover:bg-destructive/90">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v5M14 11v5"/></svg>
                        Delete category
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
