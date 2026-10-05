<x-app-layout :role="'student'" title="Create Ticket">
    <div x-data="{ showSubmitted: {{ session('submittedComplaint') ? 'true' : 'false' }} }">
        @if ($errors->any())
            <div class="mb-5 rounded-lg border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive">
                <ul class="grid gap-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('student.complaints.store') }}" method="POST" enctype="multipart/form-data" novalidate class="grid gap-5" x-data="{ anonymous: {{ old('is_anonymous') ? 'true' : 'false' }}, showValidation: false, attachmentFiles: [], addAttachments(event) { this.attachmentFiles = [...this.attachmentFiles, ...Array.from(event.target.files)].slice(0, 10); this.syncAttachments(); }, removeAttachment(index) { this.attachmentFiles.splice(index, 1); this.syncAttachments(); }, syncAttachments() { const dataTransfer = new DataTransfer(); this.attachmentFiles.forEach((file) => dataTransfer.items.add(file)); document.getElementById('file_attachment').files = dataTransfer.files; }, validate(event) { this.showValidation = true; if (!document.getElementById('subject_title').value.trim() || !document.getElementById('category_id').value || !document.getElementById('description').value.trim()) event.preventDefault(); }, recipients: {{ Js::from($recipientOptions) }}, categoryRecipients: {{ Js::from($categoryRecipients) }}, recipientId: '{{ old('suggested_recipient_id') }}', get recipientChoices() { const suggested = this.categoryRecipients[this.categoryId] ?? []; return suggested.length ? this.recipients.filter((recipient) => suggested.includes(recipient.id)) : this.recipients; }, get recipientLabel() { return this.recipients.find((recipient) => recipient.id === this.recipientId)?.name ?? 'Select a recipient'; }, categoryDescription: '', categoryId: '{{ old('category_id') }}', categoryLabel: '{{ old('category_id') ? addslashes($categories->firstWhere('id', old('category_id'))?->name ?? 'Select a category') : 'Select a category' }}' }" x-on:submit="validate($event)" x-on:reset-create-form.window="anonymous = false; showValidation = false; attachmentFiles = []; categoryDescription = ''; categoryId = ''; recipientId = ''; categoryLabel = 'Select a category'; $el.reset(); document.getElementById('category_id').value = ''; syncAttachments();">
            @csrf
            @include('student.complaints.partials.form', ['buttonText' => 'Submit Ticket'])
        </form>

        @include('student.complaints.submitted')
    </div>
</x-app-layout>
