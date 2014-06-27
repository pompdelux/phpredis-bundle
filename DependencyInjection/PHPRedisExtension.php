<?php

namespace Pompdelux\PHPRedisBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * This is the class that loads and manages bundle configuration
 */
class PHPRedisExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

        $config = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($config, $configs);

        foreach ($config['class'] as $name => $settings) {
            $def = new Definition($container->getParameter('pdl.phpredis.class'));
            $def->setPublic(true);
            $def->setScope(ContainerInterface::SCOPE_CONTAINER);
            $def->addArgument($name);
            $def->addArgument($container->getParameter('kernel.environment'));
            $def->addArgument($settings);
            $def->addMethodCall('setLogger', [new Reference('pdl.phpredis.logger')]);
            $container->setDefinition(sprintf('pdl.phpredis.%s', $name), $def);
        }
    }
}
