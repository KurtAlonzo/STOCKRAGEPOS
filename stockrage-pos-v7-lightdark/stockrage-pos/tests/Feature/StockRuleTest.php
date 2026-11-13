<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Guide, Part 4 step 2: "Check that nothing changes stock outside StockService."
 * This test does that search for you on every run, so Stock History can never be skipped by accident.
 */
class StockRuleTest extends TestCase
{
    public function test_only_stock_service_changes_the_stock_column(): void
    {
        $patterns = [
            '~->stock\s*=[^=]~'                       => '$product->stock = ...',
            '~->(increment|decrement)\(\s*[\'"]stock~' => 'increment/decrement of stock',
            '~update\(\s*\[\s*[\'"]stock[\'"]~'        => "update(['stock' => ...])",
            '~->(forceFill|fill)\(\s*\[\s*[\'"]stock~' => 'fill(["stock" => ...])',
        ];

        $offenders = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php' || $file->getFilename() === 'StockService.php') {
                continue;
            }
            $code = file_get_contents($file->getPathname());
            foreach ($patterns as $regex => $label) {
                if (preg_match($regex, $code)) {
                    $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname()).'  ('.$label.')';
                }
            }
        }

        $this->assertSame([], $offenders, "Stock must only change through App\\Services\\StockService:\n".implode("\n", $offenders));
    }
}
