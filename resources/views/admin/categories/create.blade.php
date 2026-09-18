<x-app-layout :role="'admin'" title="Create Category">

    <div class="mx-auto w-full max-w-4xl">
            <div class="surface p-4 sm:p-6">

                <form action="{{ route('admin.categories.store') }}" method="POST">

                    @csrf

                    @include('admin.categories.partials.form', [
                        'buttonText' => 'Save Category'
                    ])

                </form>

    </div>

</x-app-layout>