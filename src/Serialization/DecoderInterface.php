<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Serialization;

use stdClass;

/**
 * Разбирает текст файла в структуру, с которой работает Reader.
 *
 * Обратная сторона EncoderInterface. Отдельный интерфейс нужен по той же причине:
 * чтобы можно было подставить свою реализацию — например поверх symfony/yaml.
 */
interface DecoderInterface
{
    /**
     * Карты должны возвращаться как stdClass, а не как ассоциативные массивы:
     * пустая карта и пустой список в JSON различимы, и это различие значимо.
     */
    public function decode(string $content): stdClass;
}
