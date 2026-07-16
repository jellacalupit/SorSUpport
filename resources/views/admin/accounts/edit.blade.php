<x-app-layout>

<x-slot name="header">

<h2 class="text-2xl font-bold text-gray-900">
Edit Account
</h2>

</x-slot>


<div class="py-10">

<div class="max-w-4xl mx-auto bg-white p-8 rounded-lg shadow">


<form method="POST"
action="{{ route('admin.accounts.update',$user->id) }}">

@csrf
@method('PUT')


<div class="mb-5">

<label class="block text-gray-900 font-semibold">
Name
</label>

<input
type="text"
name="name"
value="{{ $user->name }}"
class="w-full border p-3 rounded text-gray-900">

</div>



<div class="mb-5">

<label class="block text-gray-900 font-semibold">
Email
</label>

<input
type="email"
name="email"
value="{{ $user->email }}"
class="w-full border p-3 rounded text-gray-900">

</div>

@if($user->role === 'student')

<hr class="my-8">

<h3 class="text-xl font-bold text-blue-700 mb-5">
Student Information
</h3>


<div class="mb-5">

<label class="block text-gray-900 font-semibold">
Student ID
</label>

<input
type="text"
name="student_id"
value="{{ $user->student->student_id ?? '' }}"
class="w-full border p-3 rounded text-gray-900">

</div>

<div class="mb-5">

<label class="block text-gray-900 font-semibold">
Department
</label>

<input
type="text"
name="department"
value="{{ $user->student->department ?? '' }}"
class="w-full border p-3 rounded text-gray-900">

</div>

<div class="mb-5">

<label class="block text-gray-900 font-semibold">
Course
</label>

<input
type="text"
name="course"
value="{{ $user->student->course ?? '' }}"
class="w-full border p-3 rounded text-gray-900">

</div>


<div class="mb-5">

<label class="block text-gray-900 font-semibold">
Year Level
</label>

<input
type="text"
name="year_level"
value="{{ $user->student->year_level ?? '' }}"
class="w-full border p-3 rounded text-gray-900">

</div>

<div class="mb-5">

<label class="block text-gray-900 font-semibold">
Block
</label>

<input
type="text"
name="block"
value="{{ $user->student->block ?? '' }}"
class="w-full border p-3 rounded text-gray-900">

</div>


@endif



@if($user->role === 'recipient')

<hr class="my-8">

<h3 class="text-xl font-bold text-green-700 mb-5">
Recipient Information
</h3>


<div class="mb-5">

<label class="block text-gray-900 font-semibold">
Staff ID
</label>

<input
type="text"
name="staff_id"
value="{{ $user->recipient->staff_id ?? '' }}"
class="w-full border p-3 rounded text-gray-900">

</div>


<div class="mb-5">

<label class="block text-gray-900 font-semibold">
Designation
</label>

<input
type="text"
name="designation"
value="{{ $user->recipient->designation ?? '' }}"
class="w-full border p-3 rounded text-gray-900">

</div>


@endif



<button
class="bg-blue-600 text-black px-5 py-3 rounded">

Update Account

</button>


</form>


</div>

</div>

</x-app-layout>