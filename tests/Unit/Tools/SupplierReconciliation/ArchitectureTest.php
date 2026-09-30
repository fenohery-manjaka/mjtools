<?php

namespace Tests\Unit\Tools\SupplierReconciliation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Keeps the reconciliation core independent of the framework, HTTP, UI and
 * file formats, so it can be tested alone and fed by other sources later.
 */
class ArchitectureTest extends TestCase
{
    private const MODULE = __DIR__.'/../../../../app/Tools/SupplierReconciliation';

    private const FORBIDDEN = ['Illuminate\\', 'Inertia\\', 'OpenSpout\\', 'App\\Http\\', 'App\\Models\\'];

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function pureLayers(): array
    {
        $module = 'App\\Tools\\SupplierReconciliation\\';

        return [
            'domain' => ['Domain', [$module.'Import\\', $module.'Mapping\\', $module.'Matching\\', $module.'Result\\', $module.'Review\\', $module.'Export\\', $module.'Runs\\', $module.'Http\\']],
            'normalization' => ['Normalization', [$module.'Import\\', $module.'Mapping\\', $module.'Matching\\', $module.'Review\\', $module.'Export\\', $module.'Runs\\', $module.'Http\\']],
            'matching' => ['Matching', [$module.'Import\\', $module.'Mapping\\', $module.'Review\\', $module.'Export\\', $module.'Runs\\', $module.'Http\\']],
            'result' => ['Result', [$module.'Import\\', $module.'Mapping\\', $module.'Matching\\', $module.'Review\\', $module.'Export\\', $module.'Runs\\', $module.'Http\\']],
            'review' => ['Review', [$module.'Import\\', $module.'Mapping\\', $module.'Export\\', $module.'Runs\\', $module.'Http\\']],
        ];
    }

    /**
     * @param  list<string>  $forbiddenModuleLayers
     */
    #[DataProvider('pureLayers')]
    public function test_core_layers_do_not_depend_on_framework_or_outer_layers(string $directory, array $forbiddenModuleLayers): void
    {
        $path = self::MODULE.'/'.$directory;

        if (! is_dir($path)) {
            $this->markTestSkipped("{$directory} does not exist yet.");
        }

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS));
        $checked = 0;

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());
            preg_match_all('/^use\s+([^;]+);/m', $source, $matches);

            foreach ($matches[1] as $import) {
                foreach ([...self::FORBIDDEN, ...$forbiddenModuleLayers] as $forbidden) {
                    $this->assertStringStartsNotWith($forbidden, ltrim($import, '\\'), "{$file->getFilename()} must not use {$import}.");
                }
            }

            $this->assertStringNotContainsString('\\Illuminate\\', $source, "{$file->getFilename()} must not reference Illuminate.");
            $checked++;
        }

        $this->assertGreaterThan(0, $checked);
    }
}
