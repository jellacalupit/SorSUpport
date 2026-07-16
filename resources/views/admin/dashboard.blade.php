<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            SDS Administrator Dashboard
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">

                    <h3 class="text-2xl font-bold">
                        Welcome, {{ Auth::user()->name }}
                    </h3>

                    <p class="mt-2">
                        You are logged in as the
                        <strong>SDS Administrator</strong>.
                    </p>

                    <hr class="my-6">

                    <h4 class="text-lg font-semibold">
                        Module 1
                    </h4>

                    <ul class="list-disc ml-6 mt-3 space-y-2">
                        <li>Manage Student Accounts</li>
                        <li>Manage Recipient Accounts</li>
                        <li>Bulk Upload Users</li>
                        <li>Configure Complaint Categories</li>
                    </ul>

                </div>
            </div>

        </div>
    </div>
</x-app-layout>