<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi;

use EugeneErg\OpenApi\Exceptions\InvalidPathOpenapiException;
use EugeneErg\OpenApi\Paths\Path;

use function count;
use function sprintf;

/**
 * The paths section: the key is a path template.
 *
 * Only the template itself is checked here. Whether it matches the declared path
 * parameters is checked by Openapi: a parameter's name may come not from the local key
 * but from a registration in components.parameters, which Paths knows nothing about.
 */
final readonly class Paths extends PathItems
{
    public function __construct(?Extensions $extensions = null, Path|Reference ...$paths)
    {
        parent::__construct($extensions, ...$paths);

        foreach ($this->items as $template => $path) {
            self::assertValidTemplate((string) $template);
        }
    }

    /**
     * The names of the substitutions in a path template.
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
