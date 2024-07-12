<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi;

use EugeneErg\OpenApi\Components\Links\Link;
use EugeneErg\OpenApi\Components\Parameters\Abstract\AbstractSchemaParameter;
use EugeneErg\OpenApi\Components\Parameters\ContentParameter;
use EugeneErg\OpenApi\Components\Parameters\CustomParameter;
use EugeneErg\OpenApi\Components\Parameters\Header\SchemaParameter as HeaderSchemaParameter;
use EugeneErg\OpenApi\Components\RequestBodies\RequestBody;
use EugeneErg\OpenApi\Components\Responses\Response;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractSchema;
use EugeneErg\OpenApi\Components\Schemas\Abstract\AbstractValues;
use LogicException;
use stdClass;

final readonly class Builder
{
    /** @var Openapi[] */
    public array $openapi;

    public function __construct(Openapi ...$openapi)
    {
        $this->openapi = $openapi;
    }

    /**
     * @return array<string, stdClass>
     */
    public function prepareToSave(string $path = ''): array
    {
        $result = [];

        foreach ($this->openapi as $subPath => $value) {
            $result[$path . '/' . $subPath] = $value->toObject($this);
        }

        return $result;
    }

    public function findResponse(Openapi $openapi, Response $value): ?stdClass
    {
        return $this->toRef(static fn (Openapi $openapi) => $openapi->findResponse($value), $openapi, 'responses');
    }

    public function findRequestBody(Openapi $openapi, RequestBody $value): ?stdClass
    {
        return $this->toRef(static fn (Openapi $openapi) => $openapi->findRequestBody($value), $openapi, 'requestBodies');
    }

    public function findLink(Openapi $openapi, Link $value): ?stdClass
    {
        return $this->toRef(static fn (Openapi $openapi) => $openapi->findLink($value), $openapi, 'links');
    }

    public function findHeader(Openapi $openapi, ContentParameter|HeaderSchemaParameter $value): ?stdClass
    {
        return $this->toRef(static fn (Openapi $openapi) => $openapi->findHeader($value), $openapi, 'headers');
    }

    public function findSchema(Openapi $openapi, AbstractSchema $value): ?stdClass
    {
        return $this->toRef(static fn (Openapi $openapi) => $openapi->findSchema($value), $openapi, 'schemas');
    }

    public function findCallback(Openapi $openapi, Paths $value): ?stdClass
    {
        return $this->toRef(static fn (Openapi $openapi) => $openapi->findCallback($value), $openapi, 'callbacks');
    }

    public function findExample(Openapi $openapi, null|AbstractValues|int|float|string|bool $value): ?stdClass
    {
        return $this->toRef(static fn (Openapi $openapi) => $openapi->findExample($value), $openapi, 'examples');
    }

    public function findOperation(Openapi $openapi, Paths\Operation $value): string
    {
        $result = $this->toRefString(static fn (Openapi $openapi) => $openapi->findOperation($value), $openapi, 'paths');

        if ($result === null) {
            throw new LogicException('Operation not found.');
        }

        return $result;
    }

    public function findParameter(Openapi $openapi, CustomParameter|AbstractSchemaParameter $value): ?stdClass
    {
        return $this->toRef(static fn (Openapi $openapi) => $openapi->findParameter($value), $openapi, 'parameters');
    }

    private function toRef(callable $callback, Openapi $openapi, string $component): ?stdClass
    {
        $result = $this->toRefString($callback, $openapi, 'components/' . $component);

        return $result === null ? null : (object) ['$ref' => $result];
    }

    private function toRefString(callable $callback, Openapi $openapi, string $component): ?string
    {
        $result = $callback($openapi);

        if ($result !== null) {
            return '#/' . $component . '/' . $result;
        }

        foreach ($this->openapi as $path => $item) {
            if ($item !== $openapi) {
                $result = $callback($openapi);

                if ($result !== null) {
                    return $path . '#/' . $component . '/' . $result;
                }
            }
        }

        return null;
    }
}
