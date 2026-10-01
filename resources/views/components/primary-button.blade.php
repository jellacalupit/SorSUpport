<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-4 py-2 bg-primary border border-primary rounded-lg font-semibold text-sm text-primary-foreground uppercase tracking-widest hover:bg-primary/90 focus:bg-primary active:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:ring-offset-background disabled:opacity-50 disabled:cursor-not-allowed transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
