<form method="POST" action="{{ route('admin.accounts.destroy', $user) }}" onsubmit="return confirm('Remove this account? Accounts with related ticket records will be anonymized so their records are retained.');">
    @csrf
    @method('DELETE')
    <button type="submit" class="inline-flex h-6 items-center justify-center rounded-md border border-red-300 px-2 text-[11px] font-semibold text-red-700 transition-colors hover:bg-red-50" aria-label="Remove account">
        Remove
    </button>
</form>
