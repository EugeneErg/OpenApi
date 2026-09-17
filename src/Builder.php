<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi;

use EugeneErg\OpenApi\Exceptions\InvalidArgumentOpenapiException;
use EugeneErg\OpenApi\Exceptions\Place;
use EugeneErg\OpenApi\Serialization\EncoderInterface;
use EugeneErg\OpenApi\Serialization\JsonEncoder;
use RuntimeException;
use stdClass;

use function dirname;
use function is_string;
use function sprintf;

/**
 * Builds one or several OpenAPI documents.
 *
 * Process is what resolves the references: Builder only keeps the documents and knows
 * the file name each of them will be saved under.
 */
final readonly class Builder
{
    /** @var array<string, Openapi> */
    public array $openapi;

    /**
     * The keys are file names, and they are what cross-file $refs carry:
     *
     *     new Builder(...['openapi.json' => $openapi]);
     *
     * Positional arguments would give numeric keys and, as a consequence, invalid
     * references of the form `0#/components/schemas/User`.
     */
    public function __construct(Openapi ...$openapi)
    {
        foreach ($openapi as $fileName => $item) {
            if (!is_string($fileName) || $fileName === '') {
                throw new InvalidArgumentOpenapiException(
                    'Each Openapi document must be passed with its file name as the array key, '
                    . "for example: new Builder(...['openapi.json' => \$openapi]).",
                );
            }
        }

        $this->openapi = $openapi;
    }

    /**
     * @return array<string, stdClass>
     */
    public function prepareToSave(string $path = '', bool $verbose = false): array
    {
        $path = rtrim($path, '/');
        $result = [];

        foreach ($this->openapi as $fileName => $value) {
            // the file name is the first step of the place of failure: with several
            // documents there is no telling which of them is at fault without it
            $result[$path === '' ? $fileName : $path . '/' . $fileName] = Place::in(
                fn (): stdClass => $value->toObject(new Process($this, $value, $verbose)),
                $fileName,
            );
        }

        return $result;
    }

    /**
     * Serialises the documents without writing them.
     *
     * @return array<string, string> a map of file path => its contents
     */
    public function encode(string $path = '', ?EncoderInterface $encoder = null, bool $verbose = false): array
    {
        $encoder ??= new JsonEncoder();
        $result = [];

        foreach ($this->prepareToSave($path, $verbose) as $fileName => $document) {
            $result[$fileName] = $encoder->encode($document);
        }

        return $result;
    }

    /**
     * Serialises the documents and writes them to disk.
     *
     * @return array<string, string> a map of file path => its contents
     */
    public function save(string $path = '', ?EncoderInterface $encoder = null, bool $verbose = false): array
    {
        $result = $this->encode($path, $encoder, $verbose);

        foreach ($result as $fileName => $content) {
            $directory = dirname($fileName);

            if ($directory !== '' && !is_dir($directory) && !mkdir($directory, 0o775, true) && !is_dir($directory)) {
                throw new RuntimeException(sprintf('Cannot create directory "%s".', $directory));
            }

            if (file_put_contents($fileName, $content) === false) {
                throw new RuntimeException(sprintf('Cannot write file "%s".', $fileName));
            }
        }

        return $result;
    }
}
