<x-app-layout :role="'admin'" title="Create Account">

    <div class="mx-auto w-full max-w-4xl">
            <div class="surface p-4 sm:p-6">

                <form action="{{ route('admin.accounts.store') }}" method="POST" class="admin-account-form">

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
                                    <option value="1">1</option>
                                    <option value="2">2</option>
                                    <option value="3">3</option>
                                    <option value="4">4</option>
                                </select>
                            </div>

                            <div>
                                <label class="block font-semibold text-gray-900 mb-2">
                                    Block
                                </label>

                                <input
                                    type="number"
                                    name="block"
                                    min="1"
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

                                <div x-data="{ selected: '{{ old('recipient_department', '') }}', options: ['Administrative', 'Maintenance', 'CICT', 'CBME', 'Student Organization'] }" class="relative">
                                    <input type="hidden" name="recipient_department" :value="selected" />
                                    <details x-data="{}" class="group relative w-full" x-on:click.outside="$el.removeAttribute('open')">
                                        <summary class="flex h-9 w-full cursor-pointer list-none items-center justify-between rounded-md border border-input bg-muted px-3 py-2 text-xs shadow-sm outline-none transition-colors hover:bg-muted/80 [&::-webkit-details-marker]:hidden">
                                            <span class="truncate" x-text="selected || 'Select department'" :class="selected ? '' : 'text-muted-foreground'"></span>
                                            <svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                                        </summary>
                                        <div class="absolute top-full z-50 mt-1 max-h-56 w-full overflow-y-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                                            <button type="button" @click="selected = ''; $event.target.closest('details').removeAttribute('open')" class="relative flex w-full items-center rounded-sm px-2 py-1.5 text-left text-xs" :class="!selected ? 'bg-primary-soft text-primary' : 'hover:bg-accent hover:text-accent-foreground'">
                                                <svg x-show="!selected" class="absolute right-2 h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 10 3 3 7-7" /></svg>
                                                <span>Select department</span>
                                            </button>
                                            <template x-for="option in options" :key="option">
                                                <button type="button" @click="selected = option; $event.target.closest('details').removeAttribute('open')" class="relative flex w-full items-center rounded-sm px-2 py-1.5 text-left text-xs" :class="selected === option ? 'bg-primary-soft text-primary' : 'hover:bg-accent hover:text-accent-foreground'">
                                                    <svg x-show="selected === option" class="absolute right-2 h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 10 3 3 7-7" /></svg>
                                                    <span x-text="option"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </details>
                                </div>

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
                            class="inline-flex h-10 items-center justify-center rounded-md bg-primary px-5 text-sm font-semibold text-primary-foreground transition-colors hover:bg-primary/90">

                            Create Account

                        </button>

                    </div>

                </form>

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