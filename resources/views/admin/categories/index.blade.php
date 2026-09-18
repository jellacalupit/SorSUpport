<x-app-layout :role="'admin'" title="Complaint Categories">
    <x-page-section>
        <x-slot name="action">
            <a href="{{ route('admin.settings', ['add_category' => 1]) }}" class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-sm font-semibold text-primary-foreground transition-colors hover:bg-primary/90">
                <x-icons.plus class="h-4 w-4" /> Create Category
            </a>
        </x-slot>
        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="border-b bg-muted/50"><tr class="text-left"><th class="px-3 py-3 font-semibold">Category</th><th class="px-3 py-3 font-semibold">Recipient</th><th class="px-3 py-3 font-semibold">SLA</th><th class="px-3 py-3 font-semibold">Status</th><th class="px-3 py-3 text-right font-semibold">Actions</th></tr></thead>
                <tbody class="divide-y">
                    @forelse ($categories as $category)
                        <tr class="transition-colors hover:bg-primary-soft">
                            <td class="px-3 py-3 font-medium">{{ $category->name }}</td>
                            <td class="px-3 py-3 text-muted-foreground">{{ $category->recipient?->department ?? 'Not assigned' }}<span class="block text-xs">{{ $category->recipient?->user?->name }}</span></td>
                            <td class="whitespace-nowrap px-3 py-3 text-muted-foreground">{{ $category->resolution_deadline_days }} days</td>
                            <td class="px-3 py-3"><span class="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-semibold {{ $category->is_active ? 'border-primary/40 bg-primary-soft text-primary' : 'border-border text-muted-foreground' }}">{{ $category->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td class="whitespace-nowrap px-3 py-3 text-right"><a href="{{ route('admin.categories.edit', $category) }}" class="mr-3 text-sm font-semibold text-primary hover:underline">Edit</a><form action="{{ route('admin.categories.toggle-status', $category) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure?');">@csrf @method('PATCH')<button type="submit" class="text-sm font-semibold {{ $category->is_active ? 'text-destructive' : 'text-primary' }} hover:underline">{{ $category->is_active ? 'Deactivate' : 'Activate' }}</button></form></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-10 text-center text-muted-foreground">No complaint categories found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($categories->hasPages())
            <div class="mt-5">{{ $categories->links() }}</div>
        @endif
    </x-page-section>
</x-app-layout>
