<?php

declare(strict_types=1);

$path = $argv[1] ?? 'coverage.xml';
if (! is_file($path)) {
    fwrite(STDERR, "Coverage report is missing: {$path}\n");
    exit(1);
}
$report = simplexml_load_file($path);
if ($report === false) {
    exit(1);
}
$groups = ['Pipeline', 'Quality', 'Schema', 'Normalizers', 'Drivers/Ai'];
$failed = false;
foreach ($groups as $group) {
    $covered = 0;
    $total = 0;
    foreach ($report->xpath('//file') ?: [] as $file) {
        if (! str_contains(str_replace('\\', '/', (string) $file['name']), '/src/'.$group.'/')) {
            continue;
        }
        $covered += (int) $file->metrics['coveredstatements'];
        $total += (int) $file->metrics['statements'];
    }
    $percent = $total > 0 ? 100 * $covered / $total : 0;
    printf("%s: %.2f%% (%d/%d)\n", $group, $percent, $covered, $total);
    if ($percent < 90) {
        $failed = true;
    }
}
exit($failed ? 1 : 0);
