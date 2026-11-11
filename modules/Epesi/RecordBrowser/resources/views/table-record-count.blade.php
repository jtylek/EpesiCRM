<x-filament::badge color="gray" size="md">
    {{ trans_choice('{1} :count record|[2,*] :count records', $count, ['count' => Illuminate\Support\Number::format($count)]) }}
</x-filament::badge>
