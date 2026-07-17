<div class="mb-5">

    <label for="category_id" class="block font-semibold text-gray-900 mb-2">
        Complaint Category
    </label>

    <select
        id="category_id"
        name="category_id"
        class="w-full border border-gray-300 rounded-lg p-3 text-gray-900 bg-white"
        required>

        <option value="">Select a category</option>

        @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>
                {{ $category->name }}
            </option>
        @endforeach

    </select>

    <x-input-error :messages="$errors->get('category_id')" class="mt-2" />

</div>

<div class="mb-5">

    <label for="subject_title" class="block font-semibold text-gray-900 mb-2">
        Subject
    </label>

    <input
        type="text"
        id="subject_title"
        name="subject_title"
        value="{{ old('subject_title') }}"
        class="w-full border border-gray-300 rounded-lg p-3 text-gray-900 bg-white"
        required>

    <x-input-error :messages="$errors->get('subject_title')" class="mt-2" />

</div>

<div class="mb-5">

    <label for="personnel_involved" class="block font-semibold text-gray-900 mb-2">
        Personnel Involved <span class="font-normal text-gray-500">(optional)</span>
    </label>

    <input
        type="text"
        id="personnel_involved"
        name="personnel_involved"
        value="{{ old('personnel_involved') }}"
        class="w-full border border-gray-300 rounded-lg p-3 text-gray-900 bg-white"
        placeholder="Names, department, role (if applicable)">

    <x-input-error :messages="$errors->get('personnel_involved')" class="mt-2" />

</div>

<div class="mb-5">

    <label for="description" class="block font-semibold text-gray-900 mb-2">
        Complaint Description
    </label>

    <textarea
        id="description"
        name="description"
        rows="6"
        class="w-full border border-gray-300 rounded-lg p-3 text-gray-900 bg-white"
        required>{{ old('description') }}</textarea>

    <x-input-error :messages="$errors->get('description')" class="mt-2" />

</div>

<div class="mb-8">

    <label for="file_attachment" class="block font-semibold text-gray-900 mb-2">
        Attachment <span class="font-normal text-gray-500">(optional)</span>
    </label>

    <input
        type="file"
        id="file_attachment"
        name="file_attachment"
        accept=".pdf,.jpg,.jpeg,.png"
        class="w-full border border-gray-300 rounded-lg p-3 text-gray-900 bg-white">

    <p class="mt-1 text-sm text-gray-500">
        Accepted formats: PDF, JPG, JPEG, PNG. Maximum size: 5 MB.
    </p>

    <x-input-error :messages="$errors->get('file_attachment')" class="mt-2" />

</div>

<div class="mb-8">

    <label class="flex items-center gap-3 cursor-pointer select-none">
        <input
            type="checkbox"
            name="is_anonymous"
            value="1"
            class="h-4 w-4 text-blue-600 border-gray-300 rounded"
            @checked(old('is_anonymous'))>

        <span class="font-semibold text-gray-900">
            Submit anonymously
        </span>
    </label>

    <p class="mt-1 text-sm text-gray-500">
        If selected, your complaint will be submitted without creating a support ticket or sending notifications.
    </p>

    <x-input-error :messages="$errors->get('is_anonymous')" class="mt-2" />

</div>

<div class="flex gap-4">

    <button
        type="submit"
        class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-3 rounded-lg">

        {{ $buttonText }}

    </button>

    <a
        href="{{ route('student.dashboard') }}"
        class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-lg font-semibold">

        Cancel

    </a>

</div>
