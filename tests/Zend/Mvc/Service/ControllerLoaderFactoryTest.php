<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Mvc
 */

namespace ZendTest\Mvc\Service;

use ArrayObject;
use PHPUnit_Framework_TestCase as TestCase;
use Zend\Mvc\Service\ControllerLoaderFactory;
use Zend\Mvc\Service\ControllerPluginManagerFactory;
use Zend\Mvc\Service\DiFactory;
use Zend\Mvc\Service\EventManagerFactory;
use Zend\ServiceManager\Config;
use Zend\ServiceManager\ServiceManager;
use Zend\Mvc\Exception;

class ControllerLoaderFactoryTest extends TestCase
{
    /**
     * @var ServiceManager
     */
    protected $services;

    /**
     * @var \Zend\Mvc\Controller\ControllerManager
     */
    protected $loader;

    public function setUp()
    {
        $loaderFactory  = new ControllerLoaderFactory();
        $config         = new ArrayObject(array('di' => array()));
        $this->services = new ServiceManager();
        $this->services->setService('Zend\ServiceManager\ServiceLocatorInterface', $this->services);
        $this->services->setFactory('ControllerLoader', $loaderFactory);
        $this->services->setService('Config', $config);
        $this->services->setFactory('ControllerPluginBroker', new ControllerPluginManagerFactory());
        $this->services->setFactory('Di', new DiFactory());
        $this->services->setFactory('EventManager', new EventManagerFactory());
        $this->services->setInvokableClass('SharedEventManager', 'Zend\EventManager\SharedEventManager');
    }

    public function testCannotLoadInvalidDispatchable()
    {
        $this->loader = $this->services->get('ControllerLoader');

        // Ensure the class exists and can be autoloaded
        $this->assertTrue(class_exists('ZendTest\Mvc\Service\TestAsset\InvalidDispatchableClass'));

        try {
            $this->loader->get('ZendTest\Mvc\Service\TestAsset\InvalidDispatchableClass');
            $this->fail('Retrieving the invalid dispatchable should fail');
        } catch (\Exception $e) {
            do {
                $this->assertNotContains('Should not instantiate this', $e->getMessage());
            } while ($e = $e->getPrevious());
        }
    }

    public function testCannotLoadControllerFromPeer()
    {
        $this->loader = $this->services->get('ControllerLoader');
        $this->services->setService('foo', $this);

        $this->setExpectedException('Zend\ServiceManager\Exception\ExceptionInterface');
        $this->loader->get('foo');
    }

    public function testControllerLoadedCanBeInjectedWithValuesFromPeer()
    {
        $this->loader = $this->services->get('ControllerLoader');
        $config = array(
            'invokables' => array(
                'ZendTest\Dispatchable' => 'ZendTest\Mvc\Service\TestAsset\Dispatchable',
            ),
        );
        $config = new Config($config);
        $config->configureServiceManager($this->loader);

        $controller = $this->loader->get('ZendTest\Dispatchable');
        $this->assertInstanceOf('ZendTest\Mvc\Service\TestAsset\Dispatchable', $controller);
        $this->assertSame($this->services, $controller->getServiceLocator());
        $this->assertSame($this->services->get('EventManager'), $controller->getEventManager());
        $this->assertSame($this->services->get('ControllerPluginBroker'), $controller->getPluginManager());
    }

    public function testWillInstantiateControllersFromDiAbstractFactoryWhenWhitelisted()
    {
        // rewriting since controller loader does not have the correct config, but is already fetched
        $config         = new ArrayObject(array(
            'di' => array(
                'instance' => array(
                    'alias' => array(
                        'my-controller'   => 'stdClass',
                    ),
                ),
                'allowed_controllers' => array(
                    'my-controller',
                ),
            ),
        ));
        $this->services->setAllowOverride(true);
        $this->services->setService('Config', $config);
        $this->loader = $this->services->get('ControllerLoader');

        $this->assertTrue($this->loader->has('my-controller'));
        // invalid controller exception (because we're getting an \stdClass after all)
        $this->setExpectedException('Zend\Mvc\Exception\InvalidControllerException');
        $this->loader->get('my-controller');
    }

    public function testWillNotInstantiateControllersFromDiAbstractFactoryWhenNotWhitelisted()
    {
        // rewriting since controller loader does not have the correct config, but is already fetched
        $config         = new ArrayObject(array(
            'di' => array(
                'instance' => array(
                    'alias' => array(
                        'evil-controller' => 'stdClass',
                    ),
                ),
                'allowed_controllers' => array(
                    'my-controller',
                ),
            ),
        ));
        $this->services->setAllowOverride(true);
        $this->services->setService('Config', $config);
        $this->loader = $this->services->get('ControllerLoader');
        $this->setExpectedException('Zend\ServiceManager\Exception\ServiceNotFoundException');
        $this->loader->get('evil-controller');
    }
}
