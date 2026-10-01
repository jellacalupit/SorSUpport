@php
    $userRole = Auth::user()?->role ?? 'guest';
    $roleMap = [
        'sds_admin' => 'admin',
        'student' => 'student',
        'recipient' => 'recipient',
    ];
    $appRole = $roleMap[$userRole] ?? 'student';
@endphp

<x-layouts.app-shell :role="$role !== 'student' ? $role : $appRole" :title="$title ?: ($header ?? '')" :description="$description ?? ''">
    {{ $slot }}
</x-layouts.app-shell>
