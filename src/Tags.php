<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi;

use EugeneErg\OpenApi\Tags\Tag;
use stdClass;

final readonly class Tags
{
    /** @var Tag[] */
    public array $items;

    public function __construct(Tag ...$tags)
    {
        $this->items = $tags;
    }

    /**
     * @return array<int, stdClass>
     */
    /**
     * Объявление тегов на верхнем уровне документа.
     *
     * @return list<stdClass>
     */
    public function toArray(): array
    {
        $result = [];

        foreach ($this->items as $tag) {
            $result[] = $tag->toObject();
        }

        return $result;
    }

    /**
     * Ссылка на теги из операции: спецификация требует здесь список имён.
     *
     * @return list<string>
     */
    public function toNames(): array
    {
        $result = [];

        foreach ($this->items as $tag) {
            $result[] = $tag->name;
        }

        return $result;
    }
}
