<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Routing\Page;

use Contao\CoreBundle\Routing\Page\ContentCompositionInterface;
use Contao\CoreBundle\Routing\Page\DynamicRouteInterface;
use Contao\CoreBundle\Routing\Page\PageRegistry;
use Contao\CoreBundle\Routing\Page\PageRoute;
use Contao\CoreBundle\Routing\Page\RouteConfig;
use Contao\CoreBundle\Tests\TestCase;
use Contao\PageModel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\MockObject\MockObject;

class PageRegistryTest extends TestCase
{
    public function testReturnsParameteredPageRouteIfPathIsNullWithoutRequireItem(): void
    {
        /** @var PageModel&MockObject $pageModel */
        $pageModel = $this->mockClassWithProperties(PageModel::class, [
            'type' => 'foo',
            'alias' => 'bar',
            'urlPrefix' => 'foo',
            'urlSuffix' => '.baz',
            'requireItem' => '',
        ]);

        $registry = new PageRegistry($this->createMock(Connection::class));
        $route = $registry->getRoute($pageModel);

        $this->assertSame('/foo/bar{!parameters}.baz', $route->getPath());
        $this->assertSame('', $route->getDefault('parameters'));
        $this->assertSame('(/.+?)?', $route->getRequirement('parameters'));
    }

    public function testReturnsParameteredPageRouteIfPathIsNullWithRequireItem(): void
    {
        /** @var PageModel&MockObject $pageModel */
        $pageModel = $this->mockClassWithProperties(PageModel::class, [
            'type' => 'foo',
            'alias' => 'bar',
            'urlPrefix' => 'foo',
            'urlSuffix' => '.baz',
            'requireItem' => '1',
        ]);

        $registry = new PageRegistry($this->createMock(Connection::class));
        $route = $registry->getRoute($pageModel);

        $this->assertSame('/foo/bar{!parameters}.baz', $route->getPath());
        $this->assertSame('', $route->getDefault('parameters'));
        $this->assertSame('/.+', $route->getRequirement('parameters'));
    }

    /**
     * @dataProvider pageRouteWithPathProvider
     */
    public function testReturnsPageRouteWithPath(RouteConfig $config, string $urlPrefix, string $alias, string $urlSuffix, string $expectedPath): void
    {
        /** @var PageModel&MockObject $pageModel */
        $pageModel = $this->mockClassWithProperties(PageModel::class, [
            'type' => 'foo',
            'alias' => $alias,
            'urlPrefix' => $urlPrefix,
            'urlSuffix' => $urlSuffix,
        ]);

        $registry = new PageRegistry($this->createMock(Connection::class));
        $registry->add('foo', $config);

        $route = $registry->getRoute($pageModel);

        $this->assertSame($expectedPath, $route->getPath());
    }

    public function pageRouteWithPathProvider(): \Generator
    {
        yield 'Does not add parameters for empty path' => [
            new RouteConfig(''),
            'foo',
            'bar',
            '.baz',
            '/foo/bar.baz',
        ];

        yield 'Prepends the page alias for a relative path' => [
            new RouteConfig('{alias}'),
            'foo',
            'bar',
            '.baz',
            '/foo/bar/{alias}.baz',
        ];

        yield 'URL Suffix from route config overrides the page settings' => [
            new RouteConfig('{alias}', null, '.html'),
            'foo',
            'bar',
            '.baz',
            '/foo/bar/{alias}.html',
        ];

        yield 'Adds URL suffix for absolute path' => [
            new RouteConfig('/foo'),
            '',
            'bar',
            '.baz',
            '/foo.baz',
        ];

        yield 'Adds URL prefix and suffix for absolute path' => [
            new RouteConfig('/not-bar'),
            'foo',
            'bar',
            '.baz',
            '/foo/not-bar.baz',
        ];

        yield 'Override URL Suffix for absolute path' => [
            new RouteConfig('/foo', null, '.html'),
            '',
            'bar',
            '.baz',
            '/foo.html',
        ];

        yield 'Allow config with full path' => [
            new RouteConfig('/feed/{alias}.atom', null, ''),
            '',
            'bar',
            '.baz',
            '/feed/{alias}.atom',
        ];

        yield 'Adds URL prefix to config with full path' => [
            new RouteConfig('/feed/{alias}.atom', null, ''),
            'foo',
            'bar',
            '.baz',
            '/foo/feed/{alias}.atom',
        ];
    }

    public function testConfiguresTheRoute(): void
    {
        /** @var PageModel&MockObject $pageModel */
        $pageModel = $this->mockClassWithProperties(PageModel::class, ['type' => 'foo']);

        $enhancer1 = $this->createMock(DynamicRouteInterface::class);
        $enhancer1
            ->expects($this->once())
            ->method('configurePageRoute')
            ->with($this->callback(
                static function ($route) use ($pageModel) {
                    return $route instanceof PageRoute && $route->getPageModel() === $pageModel;
                }
            ))
        ;

        $enhancer2 = $this->createMock(DynamicRouteInterface::class);
        $enhancer2
            ->expects($this->never())
            ->method($this->anything())
        ;

        $registry = new PageRegistry($this->createMock(Connection::class));
        $registry->add('foo', new RouteConfig(), $enhancer1);
        $registry->add('bar', new RouteConfig(), $enhancer2);

        $registry->getRoute($pageModel);
    }

    public function testReturnsConfigKeys(): void
    {
        $registry = new PageRegistry($this->createMock(Connection::class));
        $registry->add('foo', new RouteConfig());
        $registry->add('bar', new RouteConfig());

        $this->assertSame(['foo', 'bar'], $registry->keys());
    }

    public function testGetPathRegex(): void
    {
        $registry = new PageRegistry($this->createMock(Connection::class));
        $registry->add('foo', new RouteConfig('', '/foo'));
        $registry->add('bar', new RouteConfig('', '/bar/[a-z]+'));
        $registry->add('baz', new RouteConfig());

        $this->assertSame(['foo' => '/foo', 'bar' => '/bar/[a-z]+'], $registry->getPathRegex());
    }

    public function testGetUrlPrefixes(): void
    {
        $connection = $this->mockConnectionWithPrefixAndSuffix('en');
        $registry = new PageRegistry($connection);

        $this->assertSame(['en'], $registry->getUrlPrefixes());
    }

    public function testGetsUrlSuffixes(): void
    {
        $connection = $this->mockConnectionWithPrefixAndSuffix('', 'foo');

        $enhancer1 = $this->createMock(DynamicRouteInterface::class);
        $enhancer1
            ->expects($this->once())
            ->method('getUrlSuffixes')
            ->willReturn(['foo', 'bar'])
        ;

        $enhancer2 = $this->createMock(DynamicRouteInterface::class);
        $enhancer2
            ->expects($this->once())
            ->method('getUrlSuffixes')
            ->willReturn(['foo', 'baz', ''])
        ;

        $registry = new PageRegistry($connection);
        $registry->add('bar', new RouteConfig(), $enhancer1);
        $registry->add('baz', new RouteConfig(), $enhancer2);
        $registry->add('baz', new RouteConfig('', null, '.html'));

        $this->assertSame(['foo', '.html', 'bar', 'baz', ''], $registry->getUrlSuffixes());
    }

    public function testSupportsContentCompositionReturnsTrueForUnknownType(): void
    {
        /** @var PageModel&MockObject $pageModel */
        $pageModel = $this->mockClassWithProperties(PageModel::class, ['type' => 'foo']);
        $registry = new PageRegistry($this->createMock(Connection::class));

        $this->assertTrue($registry->supportsContentComposition($pageModel));

        $registry->add('bar', new RouteConfig());

        $this->assertTrue($registry->supportsContentComposition($pageModel));
    }

    public function testSupportsContentCompositionWithBoolean(): void
    {
        /** @var PageModel&MockObject $fooModel */
        $fooModel = $this->mockClassWithProperties(PageModel::class, ['type' => 'foo']);

        /** @var PageModel&MockObject $barModel */
        $barModel = $this->mockClassWithProperties(PageModel::class, ['type' => 'bar']);

        $registry = new PageRegistry($this->createMock(Connection::class));
        $registry->add('foo', new RouteConfig(), null, false);
        $registry->add('bar', new RouteConfig(), null, true);

        $this->assertFalse($registry->supportsContentComposition($fooModel));
        $this->assertTrue($registry->supportsContentComposition($barModel));
    }

    public function testSupportsContentCompositionFromPage(): void
    {
        /** @var PageModel&MockObject $pageModel */
        $pageModel = $this->mockClassWithProperties(PageModel::class, ['type' => 'foo']);

        $page = $this->createMock(ContentCompositionInterface::class);
        $page
            ->expects($this->once())
            ->method('supportsContentComposition')
            ->with($pageModel)
            ->willReturn(true)
        ;

        $registry = new PageRegistry($this->createMock(Connection::class));
        $registry->add('foo', new RouteConfig(), null, $page);

        $this->assertTrue($registry->supportsContentComposition($pageModel));
    }

    public function testOverwritesExistingTypes(): void
    {
        /** @var PageModel&MockObject $pageModel */
        $pageModel = $this->mockClassWithProperties(PageModel::class, ['type' => 'foo']);

        $config1 = new RouteConfig();
        $config2 = new RouteConfig();

        $enhancer1 = $this->createMock(DynamicRouteInterface::class);
        $enhancer1
            ->expects($this->never())
            ->method($this->anything())
        ;

        $enhancer2 = $this->createMock(DynamicRouteInterface::class);
        $enhancer2
            ->expects($this->once())
            ->method('getUrlSuffixes')
            ->willReturn([])
        ;

        $composite1 = $this->createMock(ContentCompositionInterface::class);
        $composite1
            ->expects($this->never())
            ->method($this->anything())
        ;

        $composite2 = $this->createMock(ContentCompositionInterface::class);
        $composite2
            ->expects($this->once())
            ->method('supportsContentComposition')
            ->willReturn(true)
        ;

        $registry = new PageRegistry($this->mockConnectionWithPrefixAndSuffix());
        $registry->add('foo', $config1, $enhancer1, $composite1);
        $registry->add('foo', $config2, $enhancer2, $composite2);

        $registry->getRoute($pageModel);
        $registry->getUrlSuffixes();
        $registry->supportsContentComposition($pageModel);
    }

    public function testRemovesType(): void
    {
        /** @var PageModel&MockObject $pageModel */
        $pageModel = $this->mockClassWithProperties(
            PageModel::class,
            [
                'type' => 'foo',
                'alias' => 'baz',
                'urlPrefix' => 'bar',
                'urlSuffix' => '.html',
            ]
        );

        $enhancer = $this->createMock(DynamicRouteInterface::class);
        $enhancer
            ->expects($this->never())
            ->method($this->anything())
        ;

        $composite = $this->createMock(ContentCompositionInterface::class);
        $composite
            ->expects($this->never())
            ->method($this->anything())
        ;

        $registry = new PageRegistry($this->mockConnectionWithPrefixAndSuffix());
        $registry->add('foo', new RouteConfig('/foo'), $enhancer, $composite);
        $registry->remove('foo');

        $route = $registry->getRoute($pageModel);

        $this->assertSame('/bar/baz{!parameters}.html', $route->getPath());

        $registry->getUrlSuffixes();
        $registry->supportsContentComposition($pageModel);
    }

    private function mockConnectionWithPrefixAndSuffix(string $urlPrefix = '', string $urlSuffix = '.html'): Connection
    {
        $statement = $this->createMock(Statement::class);
        $statement
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn([compact('urlPrefix', 'urlSuffix')])
        ;

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('query')
            ->with("SELECT urlPrefix, urlSuffix FROM tl_page WHERE type='root'")
            ->willReturn($statement)
        ;

        return $connection;
    }
}
