<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi;

/**
 * Версия спецификации, по правилам которой собирается документ.
 *
 * Различия, которые пакет учитывает:
 *  - 3.0: `nullable: true`;  3.1: `type: ["string", "null"]`
 *  - 3.0: `exclusiveMinimum: true` рядом с `minimum`;  3.1: `exclusiveMinimum: <число>`
 *  - `webhooks` существует только в 3.1
 */
enum Version: string
{
    case V300 = '3.0.0';
    case V301 = '3.0.1';
    case V302 = '3.0.2';
    case V303 = '3.0.3';
    case V304 = '3.0.4';
    case V310 = '3.1.0';
    case V311 = '3.1.1';

    public function isV31(): bool
    {
        return str_starts_with($this->value, '3.1.');
    }
}
