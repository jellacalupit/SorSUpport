<div class="mb-5">

    <label class="block font-semibold text-gray-900 mb-2">
        Category Name
    </label>

    <input
        type="text"
        name="name"
        value="{{ old('name', $category->name ?? '') }}"
        class="w-full border border-gray-300 rounded-lg p-3 text-gray-900 bg-white"
        required>

</div>

<div class="mb-5">

    <label class="block font-semibold text-gray-900 mb-2">
        Description
    </label>

    <textarea
        name="description"
        rows="4"
        class="w-full border border-gray-300 rounded-lg p-3 text-gray-900 bg-white">{{ old('description', $category->description ?? '') }}</textarea>

</div>

<div class="mb-5">

    <label class="block font-semibold text-gray-900 mb-2">
        Assigned Office
    </label>

    <select
        name="recipient_id"
        class="w-full border border-gray-300 rounded-lg p-3 text-gray-900 bg-white">

        <option value="">
            Select Office
        </option>

        @foreach($recipients as $recipient)

            <option
                value="{{ $recipient->id }}"
                @selected(old('recipient_id', $category->recipient_id ?? '') == $recipient->id)>

                {{ $recipient->unit }}
                —
                {{ $recipient->user->name }}

            </option>

        @endforeach

    </select>

</div>

<div class="mb-8">

    <label class="block font-semibold text-gray-900 mb-2">
        Resolution Deadline (Days)
    </label>

    <input
        type="number"
        min="1"
        name="resolution_deadline_days"
        value="{{ old('resolution_deadline_days', $category->resolution_deadline_days ?? '') }}"
        class="w-full border border-gray-300 rounded-lg p-3 text-gray-900 bg-white"
        required>

</div>

<div class="mb-8">

    <label class="block font-semibold text-gray-900 mb-2">
        Escalation Hierarchy (Recipient IDs, in order)
    </label>

    <textarea
        name="escalation_hierarchy"
        rows="4"
        class="w-full border border-gray-300 rounded-lg p-3 text-gray-900 bg-white"
        placeholder="Enter recipient IDs separated by commas or spaces">{{ old('escalation_hierarchy', isset($category) ? $category->escalationHierarchies->pluck('recipient_id')->implode(', ') : '') }}</textarea>

    <p class="text-sm text-gray-500 mt-2">
        Example: 1, 2, 3
    </p>

</div>

<div class="flex gap-4">

    <button
        type="submit"
        class="bg-blue-600 hover:bg-blue-700 text-black font-semibold px-6 py-3 rounded-lg">

        {{ $buttonText }}

    </button>

    <a
        href="{{ route('admin.categories.index') }}"
        class="bg-gray-500 hover:bg-gray-600 text-black px-6 py-3 rounded-lg font-semibold">

        Cancel

    </a>

</div>