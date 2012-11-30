<?php

namespace Spy\TimelineBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\Config\Definition\Processor;
use Spy\TimelineBundle\Spread\Deployer;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 *
 * @author Stephane PY <py.stephane1@gmail.com>
 */
class SpyTimelineExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $processor = new Processor();
        $configuration = new Configuration();

        $config = $processor->processConfiguration($configuration, $configs);

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config/services'));
        $loader->load('notification.xml');
        $loader->load('spread.xml');
        $loader->load('twig.xml');

        $driver = null;

        if (isset($config['drivers'])) {
            if (isset($config['drivers']['orm'])) {
                $this->loadORMDriver($container, $loader, $config['drivers']['orm']);
                $driver = 'orm';
            } elseif (isset($config['drivers']['odm'])) {
                $this->loadODMDriver($container, $loader, $config['drivers']['odm']);
                $driver = 'odm';
            } elseif (isset($config['drivers']['redis'])) {
                $this->loadRedisDriver($container, $loader, $config['drivers']['redis']);
                $driver = 'redis';
            }
        }

        if (!$driver) {
            $timelineManager = $config['timeline_manager'];
            $actionManager   = $config['action_manager'];
        } else {
            $timelineManager = isset($config['timeline_manager']) ? $config['timeline_manager'] : sprintf('spy_timeline.timeline_manager.%s', $driver);
            $actionManager   = isset($config['action_manager'])   ? $config['action_manager'] : sprintf('spy_timeline.action_manager.%s', $driver);
        }

        $container->setAlias('spy_timeline.timeline_manager', $timelineManager);
        $container->setAlias('spy_timeline.action_manager', $actionManager);

        // spreads
        $container->setParameter('spy_timeline.spread.deployer.delivery', $config['spread']['delivery']);
        $container->setParameter('spy_timeline.spread.on_subject', $config['spread']['on_subject']);
        $container->setParameter('spy_timeline.spread.on_global_context', $config['spread']['on_global_context']);

        //twig
        $render = $config['render'];
        $container->setParameter('spy_timeline.render.path', $render['path']);
        $container->setParameter('spy_timeline.render.fallback', $render['fallback']);
        $container->setParameter('spy_timeline.render.i18n.fallback', isset($render['i18n']) && isset($render['i18n']['fallback']) ? $render['i18n']['fallback'] : null);
        $container->setParameter('spy_timeline.twig.resources', $render['resources']);

        // notifiers
        $notifiers  = $config['notifiers'];
        $definition = $container->getDefinition('spy_timeline.notification_manager');

        foreach ($notifiers as $notifier) {
            $definition->addMethodCall('addNotifier', array(new Reference($notifier)));
        }
    }

    private function loadORMDriver($container, $loader, $config)
    {
        $classes = isset($config['classes']) ? $config['classes'] : array();

        $parameters = array(
            'timeline', 'action', 'component', 'action_component',
        );

        foreach ($parameters as $parameter) {
            if (isset($classes[$parameter])) {
                $container->setParameter(sprintf('spy_timeline.class.%s', $parameter), $classes[$parameter]);
            }
        }

        $container->setAlias('spy_timeline.driver.orm.object_manager', $config['object_manager']);

        $loader->load('driver/orm.xml');
    }

    private function loadODMDriver($container, $loader, $config)
    {
        exit('not yet supported');
        $classes = isset($config['classes']) ? $config['classes'] : array();

        $parameters = array(
            'timeline', 'action', 'component', 'action_component',
        );

        foreach ($parameters as $parameter) {
            if (isset($classes[$parameter])) {
                $container->setParameter(sprintf('spy_timeline.class.%s', $parameter), $classes[$parameter]);
            }
        }

        $container->setAlias('spy_timeline.driver.odm.object_manager', $config['object_manager']);

        $loader->load('driver/odm.xml');
    }

    private function loadRedisDriver($container, $loader, $config)
    {
        $container->setParameter('spy_timeline.driver.redis.pipeline', $config['pipeline']);
        $container->setParameter('spy_timeline.driver.redis.timeline_key_prefix', $config['timeline_key_prefix']);
        $container->setParameter('spy_timeline.driver.redis.action_key_prefix', $config['action_key_prefix']);

        $container->setAlias('spy_timeline.driver.redis.client', $config['client']);

        $loader->load('driver/redis.xml');
    }
}
