<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\String;

/**
 * The format values the specification defines.
 *
 * The list is not exhaustive: `format` is an open value, and a schema accepts an arbitrary
 * string. The enum is here for the hints and for protection against typos in the usual
 * cases, not to forbid the rest.
 *
 * The first group is described in OpenAPI itself, the second comes from the JSON Schema
 * format vocabulary and applies in 3.1. The list is not closed: the specification allows
 * arbitrary values, but these are the ones tools are guaranteed to understand.
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
