<?php

namespace Oro\Component\Routing\Tests\Unit\Loader;

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

use Oro\Component\Routing\Loader\CumulativeRoutingFileLoader;

class CumulativeRoutingFileLoaderTest extends \PHPUnit_Framework_TestCase
{
    /** @var \PHPUnit_Framework_MockObject_MockObject */
    private $kernel;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    private $routeOptionsResolver;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    private $loaderResolver;

    /** @var CumulativeRoutingFileLoader */
    private $loader;

    protected function setUp()
    {
        $this->kernel = $this->getMock('Symfony\Component\HttpKernel\KernelInterface');

        $this->routeOptionsResolver = $this->getMock('Oro\Component\Routing\Resolver\RouteOptionsResolverInterface');

        $this->loaderResolver = $this->getMock('Symfony\Component\Config\Loader\LoaderResolverInterface');

        $this->loader = new CumulativeRoutingFileLoader(
            $this->kernel,
            $this->routeOptionsResolver,
            ['Resources/config/routing.yml'],
            'auto'
        );
        $this->loader->setResolver($this->loaderResolver);
    }

    public function testSupports()
    {
        $this->assertTrue($this->loader->supports(null, 'auto'));
        $this->assertFalse($this->loader->supports(null, 'another'));
    }

    public function testLoad()
    {
        $rootDir = str_replace('\\', '/', realpath(__DIR__ . '/../Fixtures/Bundles'));

        $loadedRoutes = new RouteCollection();
        $loadedRoutes->add('route1', new Route('/route1'));
        $loadedRoutes->add('route2', new Route('/route2', [], [], ['priority' => -1]));

        /** @var \PHPUnit_Framework_MockObject_MockObject[] $bundles */
        $bundles = [
            'bundle1' => $this->getMock('Symfony\Component\HttpKernel\Bundle\BundleInterface'),
            'bundle2' => $this->getMock('Symfony\Component\HttpKernel\Bundle\BundleInterface'),
            'bundle3' => $this->getMock('Symfony\Component\HttpKernel\Bundle\BundleInterface')
        ];

        $bundles['bundle1']->expects($this->any())
            ->method('getPath')
            ->willReturn($rootDir . '/Bundle1');
        $bundles['bundle2']->expects($this->any())
            ->method('getPath')
            ->willReturn($rootDir . '/Bundle2');
        $bundles['bundle3']->expects($this->any())
            ->method('getPath')
            ->willReturn($rootDir . '/Bundle3');

        $this->kernel->expects($this->once())
            ->method('getBundles')
            ->willReturn($bundles);

        $yamlLoader = $this->getMock('Symfony\Component\Config\Loader\LoaderInterface');

        $this->loaderResolver->expects($this->exactly(2))
            ->method('resolve')
            ->willReturnMap(
                [
                    [
                        str_replace('\\', '/', $rootDir . '/Bundle1/Resources/config/routing.yml'),
                        null,
                        $yamlLoader
                    ],
                    [
                        str_replace('\\', '/', $rootDir . '/Bundle2/Resources/config/routing.yml'),
                        null,
                        $yamlLoader
                    ]
                ]
            );

        $yamlLoader->expects($this->exactly(2))
            ->method('load')
            ->willReturnMap(
                [
                    [
                        str_replace('\\', '/', $rootDir . '/Bundle1/Resources/config/routing.yml'),
                        null,
                        $loadedRoutes
                    ],
                    [
                        str_replace('\\', '/', $rootDir . '/Bundle2/Resources/config/routing.yml'),
                        null,
                        new RouteCollection()
                    ]
                ]
            );

        $this->routeOptionsResolver->expects($this->at(0))
            ->method('resolve')
            ->with(
                $this->identicalTo($loadedRoutes->get('route1')),
                $this->isInstanceOf('Oro\Component\Routing\Resolver\RouteCollectionAccessor')
            );
        $this->routeOptionsResolver->expects($this->at(1))
            ->method('resolve')
            ->with(
                $this->identicalTo($loadedRoutes->get('route2')),
                $this->isInstanceOf('Oro\Component\Routing\Resolver\RouteCollectionAccessor')
            );

        $routes = $this->loader->load(null, 'auto');

        $this->assertEquals(
            ['route2', 'route1'],
            array_keys($routes->all())
        );
    }
}
