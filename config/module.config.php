<?php

return array(
    'console' => array(
        'router' => array(
            'routes' => array(
                'expire-sessions' => array(
                    'options' => array(
                        'route'    => 'expire-sessions [--verbose]',
                        'defaults' => array(
                            'controller' => 'ZealSession\Controller\Cli',
                            'action'     => 'expire'
                        )
                    )
                )
            )
        )
    ),

    'controllers' => array(
        'invokables' => array(
            'ZealSession\Controller\Cli' => 'ZealSession\Controller\CliController'
        )
    ),
);
