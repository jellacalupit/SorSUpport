<x-app-layout>

    <x-slot name="header">
        <h2 class="text-2xl font-bold text-gray-900">
            Create Complaint Category
        </h2>
    </x-slot>

    <div class="py-10">

        <div class="max-w-4xl mx-auto">

            <div class="bg-white shadow rounded-lg p-8">

                <form action="{{ route('admin.categories.store') }}" method="POST">

                    @csrf

                    @include('admin.categories.partials.form', [
                        'buttonText' => 'Save Category'
                    ])

                </form>

            </div>

        </div>

    </div>

</x-app-layout>