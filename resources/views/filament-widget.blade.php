<section class="rounded-xl bg-white p-6 shadow-sm dark:bg-gray-900" aria-label="Document extraction analytics">
    <h2 class="text-lg font-semibold">Document extraction</h2>
    <dl class="mt-4 grid grid-cols-2 gap-4">
        <div><dt>Documents</dt><dd>{{ $stats['documents'] }}</dd></div>
        <div><dt>AI cost</dt><dd>${{ number_format($stats['cost_usd'], 4) }}</dd></div>
        <div><dt>Mean confidence</dt><dd>{{ round($stats['average_confidence']*100, 1) }}%</dd></div>
        <div><dt>AI fallback rate</dt><dd>{{ round($stats['fallback_rate']*100, 1) }}%</dd></div>
    </dl>
</section>
