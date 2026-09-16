<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\String;

/**
 * Значения format, определённые спецификацией.
 *
 * Список не исчерпывающий: `format` — открытое значение, и схема принимает
 * произвольную строку. Enum нужен ради подсказок и защиты от опечаток
 * в привычных случаях, а не чтобы запретить остальные.
 *
 * Первая группа описана в самой OpenAPI, вторая приходит из словаря форматов
 * JSON Schema и применима в 3.1. Список не закрытый: спецификация разрешает
 * произвольные значения, но эти гарантированно понимают инструменты.
 */
enum Format: string
{
    case Date = 'date';
    case DateTime = 'date-time';
    case Password = 'password';
    case Byte = 'byte';
    case Binary = 'binary';
    case Email = 'email';
    case Uuid = 'uuid';
    case Hostname = 'hostname';
    case IPv4 = 'ipv4';
    case IPv6 = 'ipv6';
    case Uri = 'uri';

    case Time = 'time';
    case Duration = 'duration';
    case IdnEmail = 'idn-email';
    case IdnHostname = 'idn-hostname';
    case UriReference = 'uri-reference';
    case UriTemplate = 'uri-template';
    case Iri = 'iri';
    case IriReference = 'iri-reference';
    case JsonPointer = 'json-pointer';
    case RelativeJsonPointer = 'relative-json-pointer';
    case Regex = 'regex';
}
