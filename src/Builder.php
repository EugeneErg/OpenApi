<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi;

use EugeneErg\OpenApi\Exceptions\InvalidArgumentOpenapiException;
use EugeneErg\OpenApi\Serialization\EncoderInterface;
use EugeneErg\OpenApi\Serialization\JsonEncoder;
use RuntimeException;
use stdClass;

use function dirname;
use function is_string;
use function sprintf;

/**
 * Собирает один или несколько документов OpenAPI.
 *
 * Разрешением ссылок занимается Process: Builder только хранит документы и знает,
 * под каким именем файла каждый из них будет сохранён.
 */
final readonly class Builder
{
    /** @var array<string, Openapi> */
    public array $openapi;

    /**
     * Ключи — имена файлов, именно они попадают в кросс-файловые $ref:
     *
     *     new Builder(...['openapi.json' => $openapi]);
     *
     * Позиционные аргументы дали бы числовые ключи и, как следствие,
     * невалидные ссылки вида `0#/components/schemas/User`.
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
    public function prepareToSave(string $path = ''): array
    {
        $path = rtrim($path, '/');
        $result = [];

        foreach ($this->openapi as $fileName => $value) {
            $result[$path === '' ? $fileName : $path . '/' . $fileName]
                = $value->toObject(new Process($this, $value));
        }

        return $result;
    }

    /**
     * Сериализует документы, не записывая их.
     *
     * @return array<string, string> карта «путь к файлу => его содержимое»
     */
    public function encode(string $path = '', ?EncoderInterface $encoder = null): array
    {
        $encoder ??= new JsonEncoder();
        $result = [];

        foreach ($this->prepareToSave($path) as $fileName => $document) {
            $result[$fileName] = $encoder->encode($document);
        }

        return $result;
    }

    /**
     * Сериализует документы и пишет их на диск.
     *
     * @return array<string, string> карта «путь к файлу => его содержимое»
     */
    public function save(string $path = '', ?EncoderInterface $encoder = null): array
    {
        $result = $this->encode($path, $encoder);

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
