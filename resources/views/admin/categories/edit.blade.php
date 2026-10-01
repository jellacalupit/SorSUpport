<x-app-layout :role="'admin'" title="Edit Category">

    <div class="mx-auto w-full max-w-4xl">
            <div class="surface p-4 sm:p-6">

                <form action="{{ route('admin.categories.update', $category) }}" method="POST">

                    @csrf
                    @method('PUT')

                    @include('admin.categories.partials.form', [
                        'buttonText' => 'Update Category'
                    ])

                </form>

    </div>

</x-app-layout>