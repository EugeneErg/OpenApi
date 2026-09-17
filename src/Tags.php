<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi;

use EugeneErg\OpenApi\Support\ListedItems;
use EugeneErg\OpenApi\Tags\Tag;
use stdClass;

final readonly class Tags
{
    use ListedItems;

    /** @var Tag[] */
    public array $items;

    public function __construct(Tag ...$tags)
    {
        $this->items = self::listed($tags);
    }

    /**
     * The declaration of the tags at the top level of the document.
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
     * A reference to the tags from an operation: the specification asks for a list of
     * names here.
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
