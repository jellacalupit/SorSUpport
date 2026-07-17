<x-app-layout>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Submit Complaint
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

            @if ($errors->any())
                <div class="mb-6 rounded-lg bg-red-100 border border-red-300 text-red-800 px-4 py-3">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white shadow-sm rounded-lg">
                <div class="p-6">

                    @if ($categories->isEmpty())
                        <p class="text-gray-600">
                            No complaint categories are available at this time. Please try again later.
                        </p>

                        <a href="{{ route('student.dashboard') }}"
                           class="inline-block mt-4 bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg font-semibold">
                            Back to Dashboard
                        </a>
                    @else
                        <form action="{{ route('student.complaints.store') }}" method="POST" enctype="multipart/form-data">
                            @csrf

                            @include('student.complaints.partials.form', [
                                'buttonText' => 'Submit Complaint',
                            ])
                        </form>
                    @endif

                </div>
            </div>

        </div>
    </div>

</x-app-layout>
