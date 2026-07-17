<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Student Dashboard
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white shadow-sm rounded-lg">
                <div class="p-6">

                    <h3 class="text-2xl font-bold">
                        Welcome, {{ Auth::user()->name }}
                    </h3>

                    <p class="mt-2">
                        You are logged in as a <strong>Student</strong>.
                    </p>

                    <div class="mt-6 flex gap-4">
                        <a href="{{ route('student.complaints.create') }}"
                           class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-3 rounded-lg">
                            Submit Complaint
                        </a>
                        <a href="{{ route('student.complaints.index') }}"
                           class="inline-block bg-gray-800 hover:bg-gray-900 text-white font-semibold px-6 py-3 rounded-lg">
                            My Complaints
                        </a>
                    </div>

                </div>
            </div>

        </div>
    </div>
</x-app-layout>