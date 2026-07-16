<x-app-layout>

    <x-slot name="header">
        <h2 class="text-2xl font-bold text-gray-900">
            Create Account
        </h2>
    </x-slot>

    <div class="py-10">

        <div class="max-w-4xl mx-auto">

            <div class="bg-white shadow rounded-lg p-8">

                <form action="{{ route('admin.accounts.store') }}" method="POST">

                    @csrf

                    <h3 class="text-xl font-bold text-gray-900 mb-6">
                        Basic Information
                    </h3>

                    <div class="mb-5">

                        <label class="block font-semibold text-gray-900 mb-2">
                            Full Name
                        </label>

                        <input
                            type="text"
                            name="name"
                            class="w-full border border-gray-300 rounded-lg p-3 text-gray-900 bg-white"
                            required>

                    </div>

                    <div class="mb-5">

                        <label class="block font-semibold text-gray-900 mb-2">
                            Email Address
                        </label>

                        <input
                            type="email"
                            name="email"
                            class="w-full border border-gray-300 rounded-lg p-3 text-gray-900 bg-white"
                            required>

                    </div>

                    <div class="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-4">

                        <h3 class="font-semibold text-blue-800">
                            Account Credentials
                        </h3>

                        <p class="mt-2 text-sm text-gray-800">
                            The username will automatically be set to the
                            <strong>Student ID</strong> or
                            <strong>Staff ID</strong>.
                        </p>

                        <p class="mt-1 text-sm text-gray-800">
                            The initial password for every new account is:
                        </p>

                        <p class="mt-2 font-bold text-red-600">
                            Welcome@123
                        </p>

                        <p class="mt-2 text-sm text-gray-700">
                            Users will be required to change this password after their first successful login.
                        </p>

                    </div>

                    <div class="mb-8">

                        <label class="block font-semibold text-gray-900 mb-2">
                            Account Type
                        </label>

                        <select
                            id="role"
                            name="role"
                            class="w-full border border-gray-300 rounded-lg p-3 text-gray-900 bg-white"
                            required>

                            <option value="">Select Account Type</option>
                            <option value="student">Student</option>
                            <option value="recipient">Recipient</option>

                        </select>

                    </div>

                    <hr class="my-8">


                    <div id="student-fields" class="hidden">

                        <h3 class="text-xl font-bold text-blue-700 mb-5">
                            Student Information
                        </h3>

                        <div class="grid grid-cols-2 gap-5">

                            <div>
                                <label class="block font-semibold text-gray-900 mb-2">
                                    Student ID
                                </label>

                                <input
                                    type="text"
                                    name="student_id"
                                    class="w-full border border-gray-300 rounded-lg p-3 text-gray-900 bg-white">
                            </div>

                            <div>
                                <label class="block font-semibold text-gray-900 mb-2">
                                    Department
                                </label>

                                <input
                                    type="text"
                                    name="department"
                                    class="w-full border border-gray-300 rounded-lg p-3 text-gray-900 bg-white">
                            </div>

                            <div>
                                <label class="block font-semibold text-gray-900 mb-2">
                                    Course
                                </label>

                                <input
                                    type="text"
                                    name="course"
                                    class="w-full border border-gray-300 rounded-lg p-3 text-gray-900 bg-white">
                            </div>

                            <div>
                                <label class="block font-semibold text-gray-900 mb-2">
                                    Year Level
                                </label>

                                <select
                                    name="year_level"
                                    class="w-full border border-gray-300 rounded-lg p-3 text-gray-900 bg-white">

                                    <option value="">Select Year</option>
                                    <option>1st Year</option>
                                    <option>2nd Year</option>
                                    <option>3rd Year</option>
                                    <option>4th Year</option>
                                </select>
                            </div>

                            <div>
                                <label class="block font-semibold text-gray-900 mb-2">
                                    Block
                                </label>

                                <input
                                    type="text"
                                    name="block"
                                    class="w-full border border-gray-300 rounded-lg p-3 text-gray-900 bg-white">
                            </div>

                        </div>

                    </div>


                    <div id="recipient-fields" class="hidden">

                        <h3 class="text-xl font-bold text-green-700 mb-5">
                            Recipient Information
                        </h3>

                        <div class="grid grid-cols-2 gap-5">

                            <div>

                                <label class="block font-semibold text-gray-900 mb-2">
                                    Staff ID
                                </label>

                                <input
                                    type="text"
                                    name="staff_id"
                                    class="w-full border border-gray-300 rounded-lg p-3 text-gray-900 bg-white">

                            </div>

                            <div>

                                <label class="block font-semibold text-gray-900 mb-2">
                                    Department
                                </label>

                                <input
                                    type="text"
                                    name="recipient_department"
                                    class="w-full border border-gray-300 rounded-lg p-3 text-gray-900 bg-white">

                            </div>

                            <div>

                                <label class="block font-semibold text-gray-900 mb-2">
                                    Designation
                                </label>

                                <input
                                    type="text"
                                    name="designation"
                                    class="w-full border border-gray-300 rounded-lg p-3 text-gray-900 bg-white">

                            </div>

                        </div>

                    </div>


                    <div class="mt-8">

                        <button
                            type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-black font-semibold px-6 py-3 rounded-lg">

                            Create Account

                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>

    <script>

    document.addEventListener('DOMContentLoaded', function () {

        const role = document.getElementById('role');

        const studentFields = document.getElementById('student-fields');

        const recipientFields = document.getElementById('recipient-fields');

        function toggleFields() {

            studentFields.classList.add('hidden');
            recipientFields.classList.add('hidden');

            if (role.value === 'student') {
                studentFields.classList.remove('hidden');
            }

            if (role.value === 'recipient') {
                recipientFields.classList.remove('hidden');
            }

        }

        role.addEventListener('change', toggleFields);

    });

    </script>

</x-app-layout>