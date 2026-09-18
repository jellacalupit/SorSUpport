<x-app-layout :role="'admin'" title="Bulk Upload">
    <div class="max-w-4xl mx-auto py-8">

        <h1 class="text-2xl font-bold mb-6 text-black">
            Bulk Upload Accounts
        </h1>

        <form
            action="{{ route('admin.accounts.upload.store') }}"
            method="POST"
            enctype="multipart/form-data"
            class="space-y-4">

            @csrf

            <div>

                <label class="block font-semibold text-black mb-2">
                    CSV or Excel File Only
                </label>

                <input
                    type="file"
                    name="file"
                    accept=".csv,.xlsx,.xls"
                    required
                    class="border rounded w-full p-2 text-black">

            </div>

            <button
                class="bg-blue-600 text-black px-6 py-2 rounded hover:bg-blue-700">

                Upload File

            </button>

        </form>

    </div>
</x-app-layout>