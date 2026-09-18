<?php

declare(strict_types = 1);

namespace Tests;

use EugeneErg\OpenApi\Components\Links\Link\Parameter;
use PHPUnit\Framework\TestCase;

/**
 * A Link parameter is a runtime expression, and the named constructors are there so that
 * the expression does not have to be spelled by hand. What they produce is pinned down
 * here, because a wrong expression is not a build error anywhere: it is a link that
 * quietly points at nothing at run time.
 */
final class LinkParameterTest extends TestCase
{
    /**
     * @dataProvider provideExpressionCases
     */
    public function testExpression(Parameter $parameter, string $expected): void
    {
        self::assertSame($expected, (string) $parameter);
    }

    /**
     * @return iterable<string, array{Parameter, string}>
     */
    public static function provideExpressionCases(): iterable
    {
        yield 'request path' => [Parameter::requestPath('id'), '$request.path.id'];

        yield 'request query' => [Parameter::requestQuery('page'), '$request.query.page'];

        yield 'request header' => [Parameter::requestHeader('X-Trace'), '$request.header.X-Trace'];

        yield 'response header' => [Parameter::responseHeader('Location'), '$response.header.Location'];

        yield 'the whole request body' => [Parameter::requestBody(), '$request.body'];

        yield 'the whole response body' => [Parameter::responseBody(), '$response.body'];

        // the argument is a JSON Pointer, and a pointer starts with a slash — both
        // spellings mean the same member, so writing one does not quietly change it
        yield 'a pointer into the request body' => [Parameter::requestBody('/user/id'), '$request.body#/user/id'];

        yield 'a pointer written without its slash' => [Parameter::requestBody('id'), '$request.body#/id'];

        yield 'a pointer into the response body' => [Parameter::responseBody('/next'), '$response.body#/next'];

        yield 'a response pointer without its slash' => [Parameter::responseBody('next'), '$response.body#/next'];

        yield 'a constant' => [Parameter::constant('1'), '1'];

        yield 'a JSON value' => [Parameter::json(['id' => 1]), '{"id":1}'];

        yield 'a JSON scalar' => [Parameter::json(true), 'true'];

        // whatever the named constructors do not cover, including a pointer to the
        // member named "" that the old spelling produced by accident
        yield 'an expression of one\'s own' => [
            Parameter::expression('$response.body#//next'),
            '$response.body#//next',
        ];
    }
}
