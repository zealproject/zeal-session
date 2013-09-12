<?php

namespace ZealSession;

use Zend\Mvc\ModuleRouteListener;
use ZealSession\Session\SaveHandler\Db as SaveHandler;
use Zend\Session\SessionManager;
use Zend\Session\Container;

class Module
{
    public function onBootstrap($e)
    {
        $eventManager        = $e->getApplication()->getEventManager();
        $serviceManager      = $e->getApplication()->getServiceManager();
        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->attach($eventManager);
        $this->bootstrapSession($e);
    }

    public function bootstrapSession($e)
    {
        $sessionManager = $e->getApplication()
                     ->getServiceManager()
                     ->get('Zend\Session\SessionManager');

        // TODO start session if session cookie exists?
        //$session->start();

        // ensure this session manager is used by default
        Container::setDefaultManager($sessionManager);
    }

    public function getServiceConfig()
    {
        return array(
            'factories' => array(
                'Zend\Session\SessionManager' => function($sm) {
                    $db = $sm->get('Zend\Db\Adapter\Adapter');

                    $saveHandler = new SaveHandler();
                    $saveHandler->setDb($db);

                    $manager = new SessionManager();
                    $manager->setSaveHandler($saveHandler);

                    return $manager;
                }
            )
        );
    }

    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),
        );
    }
}
