<?php

declare(strict_types=1);

namespace Membrane\Laravel;

use Membrane\OpenAPI\Exception\CannotProcessSpecification;
use Membrane\Renderer\Renderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

#[CoversClass(ApiProblemBuilder::class)]
#[UsesClass(ToSymfony::class)]
class ApiProblemBuilderTest extends TestCase
{

    #[Test]
    public function buildFromRendererTest(): void
    {
        $expected = [
            'errors' => [
                'id' => ['must be an integer'],
            ],
            'title' => 'Request payload failed validation',
            'status' => 400,
            'type' => 'about:blank',

        ];

        $sut = new ApiProblemBuilder(400, 'about:blank', []);
        $renderer = self::createMock(Renderer::class);
        $renderer->expects(self::once())
            ->method('jsonSerialize')
            ->willReturn(['id' => ['must be an integer']]);

        $actual = $sut->buildFromRenderer($renderer);

        self::assertEquals($expected, json_decode($actual->getContent(), true));
    }

    public static function dataSetsToBuildFromException(): array
    {
        return [
            'path not found, no apiResponseTypes' => [
                CannotProcessSpecification::pathNotFound('api.json', '/pets'),
                new SymfonyResponse(
                    '{"title":"Not Found","type":"about:blank","status":404,"detail":"\/pets does not match any specified paths in api.json"}',
                    404,
                    ['Content-Type' => 'application/problem+json']
                ),
                [],
            ],
            'path not found, no applicable apiResponseType' => [
                CannotProcessSpecification::pathNotFound('api.json', '/pets'),
                new SymfonyResponse(
                    '{"title":"Not Found","type":"about:blank","status":404,"detail":"\/pets does not match any specified paths in api.json"}',
                    404,
                    ['Content-Type' => 'application/problem+json']
                ),
                [418 => 'I\'m a teapot'],
            ],
            'path not found, applicable apiResponseType' => [
                CannotProcessSpecification::pathNotFound('api.json', '/pets'),
                new SymfonyResponse(
                    '{"title":"Not Found","type":"Path Not Found","status":404,"detail":"\/pets does not match any specified paths in api.json"}',
                    404,
                    ['Content-Type' => 'application/problem+json']
                ),
                [404 => 'Path Not Found', 418 => 'I\'m a teapot'],
            ],
            'method not found, applicable apiResponseType' => [
                CannotProcessSpecification::methodNotFound('get'),
                new SymfonyResponse(
                    '{"title":"Method Not Allowed","type":"Method Not Found","status":405,"detail":"get operation not specified on path"}',
                    405,
                    ['Content-Type' => 'application/problem+json']
                ),
                [404 => 'Path Not Found', 405 => 'Method Not Found', 418 => 'I\'m a teapot'],
            ],
        ];
    }

    #[Test]
    #[DataProvider('dataSetsToBuildFromException')]
    public function buildFromExceptionTest(
        CannotProcessSpecification $exception,
        SymfonyResponse $expected,
        array $apiResponseTypes
    ): void {
        $expected->setProtocolVersion('1.1');
        $sut = new ApiProblemBuilder(400, 'about:blank', $apiResponseTypes);

        $actual = $sut->buildFromException($exception);

        self::assertSame($expected->getContent(), $actual->getContent());
        self::assertSame($expected->getStatusCode(), $actual->getStatusCode());
        self::assertSame($expected->headers->get('Content-Type'), $actual->headers->get('Content-Type'));
    }

}
