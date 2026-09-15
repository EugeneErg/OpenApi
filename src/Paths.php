<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi;

use EugeneErg\OpenApi\Exceptions\InvalidPathOpenapiException;
use EugeneErg\OpenApi\Paths\Path;

use function count;
use function sprintf;

/**
 * Секция paths: ключ — шаблон пути.
 *
 * Здесь проверяется только сам шаблон. Соответствие шаблона объявленным
 * path-параметрам проверяет Openapi: имя параметра может задаваться не локальным
 * ключом, а регистрацией в components.parameters, о которой Paths не знает.
 */
final readonly class Paths extends PathItems
{
    public function __construct(Path|Reference ...$paths)
    {
        foreach ($paths as $template => $path) {
            self::assertValidTemplate((string) $template);
        }

        parent::__construct(...$paths);
    }

    /**
     * Имена подстановок в шаблоне пути.
     *
     * @return list<string>
     */
    public static function templateVariables(string $template): array
    {
        preg_match_all('{\{([^/{}]+)\}}', $template, $matches);

        return $matches[1];
    }

    private static function assertValidTemplate(string $template): void
    {
        if (!str_starts_with($template, '/')) {
            throw new InvalidPathOpenapiException(sprintf('Path "%s" must start with a slash.', $template));
        }

        $variables = self::templateVariables($template);

        if (count($variables) !== count(array_unique($variables))) {
            throw new InvalidPathOpenapiException(sprintf(
                'Path "%s" declares the same template variable more than once.',
                $template,
            ));
        }
    }
}
