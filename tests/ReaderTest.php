<?php

declare(strict_types = 1);

namespace Tests;

use EugeneErg\OpenApi\Builder;
use EugeneErg\OpenApi\Exceptions\InvalidDocumentOpenapiException;
use EugeneErg\OpenApi\Openapi;
use EugeneErg\OpenApi\Reader;
use EugeneErg\OpenApi\Serialization\JsonEncoder;
use PHPUnit\Framework\TestCase;
use stdClass;
use UnexpectedValueException;

use function count;
use function is_array;
use function is_string;
use function sprintf;

/**
 * Разбор проверяется полным кругом: объект → JSON → объект → JSON.
 *
 * Совпадение двух текстов означает и то, что структура восстановлена, и то,
 * что `$ref` снова указывают куда надо: разные экземпляры вместо общего дали бы
 * развёрнутые копии вместо ссылок.
 */
final class ReaderTest extends TestCase
{
    /**
     * @dataProvider provideDocumentSurvivesRoundTripCases
     */
    public function testDocumentSurvivesRoundTrip(string $json): void
    {
        self::assertSame($json, (new JsonEncoder())->encode(self::build(Reader::read($json))));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideDocumentSurvivesRoundTripCases(): iterable
    {
        foreach (glob(__DIR__ . '/Cases/BuilderTest/Objects/*.php') ?: [] as $objectPath) {
            $documents = self::load($objectPath);

            foreach ($documents as $fileName => $document) {
                // мультифайловые кейсы проверяются отдельно: одиночное чтение
                // не знает о соседях и не смогло бы разрешить ссылки между файлами
                if (count($documents) > 1) {
                    continue;
                }

                yield basename($objectPath, '.php') => [
                    (new JsonEncoder())->encode(self::build($document, $fileName)),
                ];
            }
        }
    }

    /**
     * Ссылка из одного файла в другой обязана дать тот же объект, что и объявление:
     * иначе при обратной записи она развернулась бы копией.
     */
    public function testCrossFileReferencesShareObjects(): void
    {
        $documents = self::load(__DIR__ . '/Cases/BuilderTest/Objects/components-in-outside.php');
        $built = (new Builder(...$documents))->prepareToSave();
        $contents = [];

        foreach ($built as $fileName => $document) {
            $contents[$fileName] = (new JsonEncoder())->encode($document);
        }

        $again = (new Builder(...Reader::readAll($contents)))->prepareToSave();

        foreach ($built as $fileName => $document) {
            self::assertSame(
                (new JsonEncoder())->encode($document),
                (new JsonEncoder())->encode($again[$fileName] ?? new stdClass()),
            );
        }
    }

    public function testRecursiveSchemaBecomesACycle(): void
    {
        $document = Reader::read((string) json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'Tree', 'version' => '1.0.0'],
            'paths' => new stdClass(),
            'components' => ['schemas' => ['Node' => [
                'type' => 'object',
                'properties' => ['next' => ['$ref' => '#/components/schemas/Node']],
            ]]],
        ]));

        self::assertArrayHasKey('Node', $document->components->schemas->items);

        // ссылка ведёт на тот же объект, а не на его копию
        self::assertStringContainsString(
            '"$ref": "#/components/schemas/Node"',
            (new JsonEncoder())->encode(self::build($document)),
        );
    }

    public function testRejectsUnknownReference(): void
    {
        $this->expectException(InvalidDocumentOpenapiException::class);
        $this->expectExceptionMessage('does not point at anything');

        Reader::read((string) json_encode([
            'openapi' => '3.0.3',
            'info' => ['title' => 'x', 'version' => '1'],
            'paths' => new stdClass(),
            'components' => ['schemas' => ['A' => ['$ref' => '#/components/schemas/Missing']]],
        ]));
    }

    public function testRejectsUnsupportedVersion(): void
    {
        $this->expectException(InvalidDocumentOpenapiException::class);
        $this->expectExceptionMessage('a supported OpenAPI version');

        Reader::read((string) json_encode([
            'openapi' => '2.0',
            'info' => ['title' => 'x', 'version' => '1'],
            'paths' => new stdClass(),
        ]));
    }

    private static function build(Openapi $document, string $fileName = 'a.json'): stdClass
    {
        $result = (new Builder(...[$fileName => $document]))->prepareToSave();

        return $result[$fileName] ?? throw new UnexpectedValueException('Builder returned nothing.');
    }

    /**
     * @return array<string, Openapi>
     */
    private static function load(string $path): array
    {
        $documents = require $path;

        if (!is_array($documents)) {
            throw new UnexpectedValueException(sprintf('Case "%s" must return an array.', $path));
        }

        $result = [];

        foreach ($documents as $fileName => $document) {
            if (!is_string($fileName) || !$document instanceof Openapi) {
                throw new UnexpectedValueException(sprintf('Case "%s" returned an unexpected value.', $path));
            }

            $result[$fileName] = $document;
        }

        return $result;
    }
}
