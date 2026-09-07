<a href="{{ $href }}" {{ $attributes->merge(['class' => 'inline-flex items-center rounded-lg border border-gray-200 px-4 py-2 text-sm text-secondary-500 hover:bg-gray-50 dark:border-gray-600 dark:text-white dark:hover:bg-secondary-600']) }}>
    {{ $slot }}
</a>
