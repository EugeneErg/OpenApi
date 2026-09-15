<?php

declare(strict_types = 1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * README расходится с кодом незаметно, поэтому каждый его PHP-пример исполняется.
 *
 * Самодостаточные примеры запускаются как есть, фрагменты — поверх общего пролога
 * из tests/Fixtures/readme-prelude.php, где заведены переменные, на которые они ссылаются.
 */
final class ReadmeTest extends TestCase
{
    /**
     * @dataProvider provideExampleRunsCases
     */
    public function testExampleRuns(string $code): void
    {
        $file = tempnam(sys_get_temp_dir(), 'readme') . '.php';

        file_put_contents($file, $code);

        try {
            $output = [];
            $status = 0;

            exec(escapeshellcmd(PHP_BINARY) . ' -d error_reporting=E_ALL ' . escapeshellarg($file) . ' 2>&1', $output, $status);

            self::assertSame(0, $status, "Пример из README не выполнился:\n" . implode("\n", $output));
            self::assertSame([], array_values(array_filter(
                $output,
                static fn (string $line): bool => str_contains($line, 'Warning') || str_contains($line, 'Deprecated'),
            )));
        } finally {
            @unlink($file);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideExampleRunsCases(): iterable
    {
        $readme = (string) file_get_contents(__DIR__ . '/../README.MD');
        $prelude = (string) file_get_contents(__DIR__ . '/Fixtures/readme-prelude.php');
        $autoload = realpath(__DIR__ . '/../vendor/autoload.php');

        preg_match_all('{```php\n(.*?)```}s', $readme, $matches);

        foreach ($matches[1] as $index => $block) {
            if (str_starts_with(ltrim($block), '<?php')) {
                $code = preg_replace('{<\?php}', "<?php require '{$autoload}';", $block, 1) ?? $block;
            } else {
                $lines = array_filter(
                    explode("\n", $block),
                    static fn (string $line): bool => !str_starts_with($line, 'use '),
                );
                $code = str_replace('__AUTOLOAD__', (string) $autoload, $prelude) . "\n" . implode("\n", $lines);
            }

            $code = str_replace(
                ["__DIR__ . '/public/docs'", "'docs'"],
                ["sys_get_temp_dir() . '/openapi-readme'", "sys_get_temp_dir() . '/openapi-readme'"],
                $code,
            );

            yield 'example #' . ($index + 1) => [$code];
        }
    }
}
