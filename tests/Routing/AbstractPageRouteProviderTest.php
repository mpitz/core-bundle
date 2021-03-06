<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Routing;

use Contao\CoreBundle\Routing\AbstractPageRouteProvider;
use Contao\CoreBundle\Tests\TestCase;
use Contao\PageModel;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Routing\Route;

class AbstractPageRouteProviderTest extends TestCase
{
    /**
     * @dataProvider compareRoutesProvider
     */
    public function testCompareRoutes(Route $a, Route $b, ?array $languages, int $expected): void
    {
        $instance = $this->getMockForAbstractClass(AbstractPageRouteProvider::class, [], '', false);
        $class = new \ReflectionClass($instance);

        if (null !== $languages) {
            $method = $class->getMethod('convertLanguagesForSorting');
            $method->setAccessible(true);
            $languages = $method->invoke($instance, $languages);
        }

        $method = $class->getMethod('compareRoutes');
        $method->setAccessible(true);

        $result = $method->invoke($instance, $a, $b, $languages);

        $this->assertSame($expected, $result);
    }

    public function compareRoutesProvider(): \Generator
    {
        yield 'Sorts route with host higher' => [
            new Route('', [], [], [], 'www.example.com'),
            new Route('', [], [], [], ''),
            null,
            -1,
        ];

        yield 'Sorts route without host lower' => [
            new Route('', [], [], [], ''),
            new Route('', [], [], [], 'www.example.com'),
            null,
            1,
        ];

        yield 'Sorting unknown if route has no PageModel' => [
            new Route('', [], [], [], 'www.example.org'),
            new Route('', [], [], [], 'www.example.com'),
            null,
            0,
        ];

        yield 'Sorts route higher if it is fallback and no languages match' => [
            new Route('', ['pageModel' => $this->mockPageModel('en', true)]),
            new Route('', ['pageModel' => $this->mockPageModel('de')]),
            null,
            -1,
        ];

        yield 'Sorts route lower if it is not fallback and no languages match' => [
            new Route('', ['pageModel' => $this->mockPageModel('en')]),
            new Route('', ['pageModel' => $this->mockPageModel('de', true)]),
            null,
            1,
        ];

        yield 'Sorts route higher if it matches a preferred language' => [
            new Route('', ['pageModel' => $this->mockPageModel('en')]),
            new Route('', ['pageModel' => $this->mockPageModel('de')]),
            ['en'],
            -1,
        ];

        yield 'Sorts route lower if it does not match a preferred language' => [
            new Route('', ['pageModel' => $this->mockPageModel('en')]),
            new Route('', ['pageModel' => $this->mockPageModel('de')]),
            ['de'],
            1,
        ];

        yield 'Sorts route higher if preferred language has higher priority' => [
            new Route('', ['pageModel' => $this->mockPageModel('en')]),
            new Route('', ['pageModel' => $this->mockPageModel('de')]),
            ['en', 'de'],
            -1,
        ];

        yield 'Sorts route lower lower if preferred language has lower priority' => [
            new Route('', ['pageModel' => $this->mockPageModel('en')]),
            new Route('', ['pageModel' => $this->mockPageModel('de')]),
            ['de', 'en'],
            1,
        ];

        yield 'Sorts route higher if preferred language has higher priority with region' => [
            new Route('', ['pageModel' => $this->mockPageModel('en-US')]),
            new Route('', ['pageModel' => $this->mockPageModel('de-CH')]),
            ['en-US', 'de-CH'],
            -1,
        ];

        yield 'Sorts route lower lower if preferred language has lower priority with region' => [
            new Route('', ['pageModel' => $this->mockPageModel('en-US')]),
            new Route('', ['pageModel' => $this->mockPageModel('de-CH')]),
            ['de-CH', 'en-US'],
            1,
        ];

        yield 'Sorts route by preferred language if region does not match' => [
            new Route('', ['pageModel' => $this->mockPageModel('en')]),
            new Route('', ['pageModel' => $this->mockPageModel('de')]),
            ['de-CH', 'en-US'],
            1,
        ];

        yield 'Sorts route by preferred language if one region matches' => [
            new Route('', ['pageModel' => $this->mockPageModel('en')]),
            new Route('', ['pageModel' => $this->mockPageModel('de')]),
            ['de-CH', 'en-US', 'en'],
            1,
        ];

        yield 'Sorts route by language (1)' => [
            new Route('', ['pageModel' => $this->mockPageModel('de-CH')]),
            new Route('', ['pageModel' => $this->mockPageModel('en-US')]),
            ['en', 'de'],
            1,
        ];

        yield 'Sorts route by language (2)' => [
            new Route('', ['pageModel' => $this->mockPageModel('de-CH')]),
            new Route('', ['pageModel' => $this->mockPageModel('en-US')]),
            ['de', 'en'],
            -1,
        ];

        yield 'Sorts route by language (3)' => [
            new Route('', ['pageModel' => $this->mockPageModel('de-CH')]),
            new Route('', ['pageModel' => $this->mockPageModel('en-US')]),
            ['de', 'en-US'],
            -1,
        ];

        yield 'Sorts route by language (4)' => [
            new Route('', ['pageModel' => $this->mockPageModel('de')]),
            new Route('', ['pageModel' => $this->mockPageModel('en-US')]),
            ['en', 'de'],
            1,
        ];

        yield 'Sorts route by language (5)' => [
            new Route('', ['pageModel' => $this->mockPageModel('de')]),
            new Route('', ['pageModel' => $this->mockPageModel('en-US')]),
            ['en', 'de', 'en-US'],
            -1,
        ];

        yield 'Sorts route by language (6)' => [
            new Route('', ['pageModel' => $this->mockPageModel('de-CH')]),
            new Route('', ['pageModel' => $this->mockPageModel('en-US')]),
            ['en-GB', 'de', 'en-US'],
            -1,
        ];

        yield 'Sorts route by language (7)' => [
            new Route('', ['pageModel' => $this->mockPageModel('de-CH')]),
            new Route('', ['pageModel' => $this->mockPageModel('de-DE')]),
            ['de-AT', 'de-CH'],
            -1,
        ];

        yield 'Sorts route by root page priority' => [
            new Route('', ['pageModel' => $this->mockPageModel('de-CH', false, false, 256)]),
            new Route('', ['pageModel' => $this->mockPageModel('de-DE', false, false, 100)]),
            ['de', 'en-US'],
            1,
        ];

        yield 'Sorts route lower if it is a root page' => [
            new Route('', ['pageModel' => $this->mockPageModel('en')]),
            new Route('', ['pageModel' => $this->mockPageModel('de', false, true)]),
            null,
            -1,
        ];

        yield 'Sorts route higher if it is not a root page' => [
            new Route('', ['pageModel' => $this->mockPageModel('en', false, true)]),
            new Route('', ['pageModel' => $this->mockPageModel('de')]),
            null,
            1,
        ];

        yield 'Sorting is undefined if both are root page' => [
            new Route('', ['pageModel' => $this->mockPageModel('en', false, true)]),
            new Route('', ['pageModel' => $this->mockPageModel('de', false, true)]),
            null,
            0,
        ];

        yield 'Sorts by number of slashes in path (1)' => [
            new Route('/foo/bar', ['pageModel' => $this->mockPageModel('en')]),
            new Route('/bar', ['pageModel' => $this->mockPageModel('de')]),
            null,
            -1,
        ];

        yield 'Sorts by number of slashes in path (2)' => [
            new Route('/foo', ['pageModel' => $this->mockPageModel('en')]),
            new Route('/bar/foo', ['pageModel' => $this->mockPageModel('de')]),
            null,
            1,
        ];

        yield 'Sorts by number of slashes in path (3)' => [
            new Route('/foo/bar/baz', ['pageModel' => $this->mockPageModel('en')]),
            new Route('/bar/foo/baz/x', ['pageModel' => $this->mockPageModel('de')]),
            null,
            1,
        ];

        yield 'Sorts by path string if it has the same number of slashes' => [
            new Route('/foo/bar/baz', ['pageModel' => $this->mockPageModel('en')]),
            new Route('/bar/foo/baz', ['pageModel' => $this->mockPageModel('de')]),
            null,
            1,
        ];
    }

    /**
     * @dataProvider ordersRoutesByPreferredLanguages
     */
    public function testOrdersRoutesByPreferredLanguages(array $pageLanguages, array $preferredLanguages, array $expected): void
    {
        $instance = $this->getMockForAbstractClass(AbstractPageRouteProvider::class, [], '', false);
        $class = new \ReflectionClass($instance);

        $method = $class->getMethod('convertLanguagesForSorting');
        $method->setAccessible(true);
        $preferredLanguages = $method->invoke($instance, $preferredLanguages);

        $method = $class->getMethod('compareRoutes');
        $method->setAccessible(true);

        $sorting = 0;

        $routes = array_map(
            function ($language) use ($sorting) {
                return new Route('', ['pageModel' => $this->mockPageModel($language, false, false, ++$sorting)]);
            },
            $pageLanguages
        );

        usort(
            $routes,
            static function ($a, $b) use ($method, $instance, $preferredLanguages) {
                return $method->invoke($instance, $a, $b, $preferredLanguages);
            }
        );

        $result = array_map(
            static function (Route $route) {
                return $route->getDefault('pageModel')->rootLanguage;
            },
            $routes
        );

        $this->assertSame($expected, $result);
    }

    public function ordersRoutesByPreferredLanguages(): \Generator
    {
        yield [
            ['de', 'en'],
            ['en', 'de'],
            ['en', 'de'],
        ];

        yield [
            ['de-CH', 'fr-CH', 'it-CH'],
            ['it-IT', 'de'],
            ['it-CH', 'de-CH', 'fr-CH'],
        ];

        yield [
            ['en-US', 'de-DE', 'en-GB'],
            ['en', 'de'],
            ['en-US', 'en-GB', 'de-DE'],
        ];

        yield [
            ['en', 'de-DE', 'en-GB'],
            ['en', 'de'],
            ['en', 'en-GB', 'de-DE'],
        ];

        yield [
            ['en-US', 'de-DE', 'fr-FR'],
            ['fr', 'de-CH'],
            ['fr-FR', 'de-DE', 'en-US'],
        ];

        yield [
            ['de-CH', 'fr-CH', 'it-CH'],
            ['de-DE', 'it-CH', 'fr-FR', 'de'],
            ['it-CH', 'fr-CH', 'de-CH'],
        ];
    }

    /**
     * @dataProvider convertLanguageForSortingProvider
     */
    public function testConvertLanguagesForSorting(array $languages, array $expected): void
    {
        $instance = $this->getMockForAbstractClass(AbstractPageRouteProvider::class, [], '', false);

        $class = new \ReflectionClass($instance);
        $method = $class->getMethod('convertLanguagesForSorting');
        $method->setAccessible(true);

        $result = $method->invoke($instance, $languages);

        $this->assertSame($expected, $result);
    }

    public function convertLanguageForSortingProvider(): \Generator
    {
        yield 'Does nothing on empty array' => [
            [],
            [],
        ];

        yield 'Does not change the sorting' => [
            ['de-DE', 'de-CH', 'fr', 'de', 'en-US', 'en'],
            array_flip(['de-DE', 'de', 'de-CH', 'fr', 'de', 'en-US', 'en', 'en']),
        ];

        yield 'Adds primary language if it does not exist' => [
            ['de_DE'],
            array_flip(['de-DE', 'de']),
        ];

        yield 'Adds primary languages after first region' => [
            ['de_DE', 'de_CH', 'en', 'en_US', 'fr_FR'],
            array_flip(['de-DE', 'de', 'de-CH', 'en', 'en-US', 'fr-FR', 'fr']),
        ];

        yield 'Strips array keys' => [
            ['foo' => 'de', 'bar' => 'en'],
            array_flip(['de', 'en']),
        ];
    }

    /**
     * @return PageModel&MockObject
     */
    private function mockPageModel(string $language, bool $fallback = false, bool $root = false, int $rootSorting = 128): PageModel
    {
        /** @var PageModel&MockObject $pageModel */
        $pageModel = $this->mockClassWithProperties(PageModel::class);
        $pageModel->type = $root ? 'root' : 'regular';
        $pageModel->rootLanguage = $language;
        $pageModel->rootIsFallback = $fallback;
        $pageModel->rootSorting = $rootSorting;

        return $pageModel;
    }
}
