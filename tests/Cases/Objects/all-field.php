<?php

declare(strict_types = 1);

use EugeneErg\OpenApi\Components\Parameters\ContentParameter;
use EugeneErg\OpenApi\Components\Parameters\Cookie\Cookies as CookieParameters;
use EugeneErg\OpenApi\Components\Parameters\Header\Headers as HeaderParameters;
use EugeneErg\OpenApi\Components\Parameters\Header\SchemaParameter as HeaderParameter;
use EugeneErg\OpenApi\Components\Parameters\Path\Paths as PathParameters;
use EugeneErg\OpenApi\Components\Parameters\Parameters;
use EugeneErg\OpenApi\Components\Parameters\Query\Queries as QueryParameters;
use EugeneErg\OpenApi\Components\Responses;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Access;
use EugeneErg\OpenApi\Components\Schemas\Abstract\Xml;
use EugeneErg\OpenApi\Components\Schemas\String\Schema as StringSchema;
use EugeneErg\OpenApi\Components\Schemas\String\EnumSchema as StringEnumSchema;
use EugeneErg\OpenApi\Components\Schemas\String\Schemas;
use EugeneErg\OpenApi\Components\Schemas\String\Strings;
use EugeneErg\OpenApi\Components\Schemas\String\Value as StringValue;
use EugeneErg\OpenApi\ExternalDocs;
use EugeneErg\OpenApi\Info;
use EugeneErg\OpenApi\Openapi;
use EugeneErg\OpenApi\Paths;
use EugeneErg\OpenApi\Servers;

return [
    new Openapi(
        new Info(
            title: 'info1 title',
            version: 'info1 version',
            description: 'info1 description',
            termsOfService: 'info1 terms of service',
            contacts: new Info\Contacts(
                name: 'contacts1 name',
                url: 'contacts1 url',
                email: 'contacts1 email'
            ),
            license: new Info\License(
                name: 'license1 name',
                url: 'license1 url',
            ),
        ),
        new Paths(
            path1: new Paths\Path(
                get: new Paths\Operation(
                    responses: new Responses(),
                    summary: 'operation1 summary',
                    description: 'operation1 description',
                    id: 'operation1 id',
                    deprecated: true,
                    parameters: new Parameters(
                        headers: new HeaderParameters(
                            header1: new HeaderParameter(
                                schema: new StringSchema(
                                    title: 'schema1 title',
                                    description: 'schema1 description',
                                    nullable: true,
                                    access: Access::ReadOnly,
                                    deprecated: true,
                                    externalDocs: new ExternalDocs(
                                        url: 'external-docs1 url',
                                        description: 'external-docs1 description',
                                    ),
                                    xml: new Xml(
                                        name: 'xml1 name',
                                        namespace: 'xml1 namespace',
                                        prefix: 'xml1 prefix',
                                        attribute: true,
                                        wrapped: true,
                                    ),
                                    default: new StringValue('value1'),
                                    anyOf: new Schemas(
                                        new StringEnumSchema(
                                            enums: new Strings('string1', 'string2'),
                                            title: 'string-schema1 title',
                                            description: 'string-schema1 description',
                                            nullable: false,
                                            access: Access::WriteOnly,
                                            deprecated: false,
                                            externalDocs: new ExternalDocs(
                                                url: 'external-docs2 url',
                                                description: 'external-docs2 description',
                                            ),
                                            xml: new Xml(
                                                name: 'xml2 name',
                                                namespace: 'xml2 namespace',
                                                prefix: 'xml2 prefix',
                                                attribute: false,
                                                wrapped: false,
                                            ),
                                            default: new StringValue('value2'),
                                        ),
                                    ),

                                    '',
                                    '',
                                    '',
                                    '',
                                    '',
                                    '',
                                    '',
                                    '',
                                ),
                                '',
                                '',
                                '',
                                '',
                                '',
                            ),
                            header2: new ContentParameter(),
                        ),
                        cookies: new CookieParameters(...[

                        ]),
                        paths: new PathParameters(...[

                        ]),
                        queries: new QueryParameters(...[

                        ]),
                    ),
                    requestBody: '',
                    tags: '',
                    security: '',
                    servers: '',
                    callbacks: '',
                    externalDocs: '',
                ),
                servers: new Servers(),
                parameters: new Parameters()
            ),
        ),
    ),
];
