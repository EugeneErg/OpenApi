<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Reader;

use Closure;
use EugeneErg\OpenApi\Tags\Tag;

/**
 * Разделяемое состояние разрешения ссылок.
 *
 * Вынесено из Registry, потому что Registry клонируется при переходе между файлами,
 * а реестр объявленных компонентов обязан оставаться общим.
 */
final class References
{
    /** @var array<string, object> */
    public array $resolved = [];

    /** @var array<string, true> */
    public array $building = [];

    /** @var array<string, Closure(Node): object> */
    public array $factories = [];

    /** @var array<string, Node> */
    public array $nodes = [];

    /** @var array<string, string> operationId => указатель */
    public array $operationIds = [];

    /** @var array<string, Tag> объявленные на верхнем уровне, по имени */
    public array $tags = [];
}
