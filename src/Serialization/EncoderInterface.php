<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Serialization;

use stdClass;

/**
 * Превращает собранный документ в текст файла.
 *
 * Отдельный интерфейс нужен, чтобы можно было подставить свою реализацию —
 * например поверх symfony/yaml, если она уже есть в проекте.
 */
interface EncoderInterface
{
    public function encode(stdClass $document): string;
}
