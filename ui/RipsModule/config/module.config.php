<?php
return [
    // Controllers defined by the plugin
    'controllers' => [
        'invokables' => [
            'ripsModuleWebApi-1_12' => 'RipsModule\Controller\WebApiController',
        ],
    ],
    
    // Registered view helpers
    'view_helpers' => [
        'invokables' => [],
    ],
    
    // ACL for the plugin - currently only allowed for admins
    'acl' => [
        'route' => [
            'ripsModuleWebApi' => [
                'role' => \Application\Module::ACL_ROLE_ADMINISTRATOR,
                'allowedMethods' => [],
            ],
        ],
    ],
    
    // Configure where Zend can find the views
    'view_manager' => [
        'template_path_stack' => [
            __DIR__ . '/../views',
        ],
    ],
    
    // Service manager configs
    'service_manager' => [
        'invokables' => [],
    ],
    
    // Placement and settings of the plugin
    // In this case, it is placed in default --> security
    'navigation' => [
        'default' => [
            'security' => [
                'pages' => [
                    [
                        'label' => 'RIPS Code Analysis',
                        'route' => 'extensions',
                        'url' => '/code-analysis',
                        'templateUrl' => '/ZendServer/ModuleResource/RipsModule/templates/rips-module.html',
                        'angularController' => 'ripsModuleController',
                        'resources' => [
                            // JS and CSS dependencies
                            'js' => [
                                '/ZendServer/ModuleResource/RipsModule/libs/d3.min.js',
                                '/ZendServer/ModuleResource/RipsModule/libs/c3.min.js',
                                '/ZendServer/ModuleResource/RipsModule/rips-module.js',
                            ],
                            'css' => [
                                '/ZendServer/ModuleResource/RipsModule/libs/c3.min.css',
                                '/ZendServer/ModuleResource/RipsModule/rips-module.css',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    
    // Defined API routes for the AngularJS interface
    'webapi_routes' => [
        'ripsSettings' => [
            'type'	=> 'Zend\Mvc\Router\Http\Literal',
            'options' => [
                'route'	=> '/Api/ripsSettings',
                'defaults' => [
                    'controller' => 'ripsModuleWebApi',
                    'action'	 => 'settings',
                    'versions'	 => ['1.12'],
                ],
            ],
        ],
        'ripsStoreSettings' => [
            'type'	=> 'Zend\Mvc\Router\Http\Literal',
            'options' => [
                'route'	=> '/Api/ripsStoreSettings',
                'defaults' => [
                    'controller' => 'ripsModuleWebApi',
                    'action'	 => 'storeSettings',
                    'versions'	 => ['1.12'],
                ],
            ],
        ],
        'ripsScan' => [
            'type'	=> 'Zend\Mvc\Router\Http\Literal',
            'options' => [
                'route'	=> '/Api/ripsScan',
                'defaults' => [
                    'controller' => 'ripsModuleWebApi',
                    'action'	 => 'scan',
                    'versions'	 => ['1.12'],
                ],
            ],
        ],
        'ripsScanSpec' => [
            'type'	=> 'Zend\Mvc\Router\Http\Literal',
            'options' => [
                'route'	=> '/Api/ripsScanSpec',
                'defaults' => [
                    'controller' => 'ripsModuleWebApi',
                    'action'	 => 'scanSpec',
                    'versions'	 => ['1.12'],
                ],
            ],
        ],
        'ripsScanDocRoot' => [
            'type'	=> 'Zend\Mvc\Router\Http\Literal',
            'options' => [
                'route'	=> '/Api/ripsScanDocRoot',
                'defaults' => [
                    'controller' => 'ripsModuleWebApi',
                    'action'	 => 'scanDocRoot',
                    'versions'	 => ['1.12'],
                ],
            ],
        ],
        'ripsCurrentApplications' => [
            'type'	=> 'Zend\Mvc\Router\Http\Literal',
            'options' => [
                'route'	=> '/Api/ripsCurrentApplications',
                'defaults' => [
                    'controller' => 'ripsModuleWebApi',
                    'action'	 => 'currentApplications',
                    'versions'	 => ['1.12'],
                ],
            ],
        ],
        'ripsCurrentDocRoots' => [
            'type'	=> 'Zend\Mvc\Router\Http\Literal',
            'options' => [
                'route'	=> '/Api/ripsCurrentDocRoots',
                'defaults' => [
                    'controller' => 'ripsModuleWebApi',
                    'action'	 => 'currentDocRoots',
                    'versions'	 => ['1.12'],
                ],
            ],
        ],
        'ripsScans' => [
            'type'	=> 'Zend\Mvc\Router\Http\Literal',
            'options' => [
                'route'	=> '/Api/ripsScans',
                'defaults' => [
                    'controller' => 'ripsModuleWebApi',
                    'action'	 => 'scans',
                    'versions'	 => ['1.12'],
                ],
            ],
        ],
        'ripsScanDetails' => [
            'type'	=> 'Zend\Mvc\Router\Http\Literal',
            'options' => [
                'route'	=> '/Api/ripsScanDetails',
                'defaults' => [
                    'controller' => 'ripsModuleWebApi',
                    'action'	 => 'scanDetails',
                    'versions'	 => ['1.12'],
                ],
            ],
        ],
        'ripsIssues' => [
            'type'	=> 'Zend\Mvc\Router\Http\Literal',
            'options' => [
                'route'	=> '/Api/ripsIssues',
                'defaults' => [
                    'controller' => 'ripsModuleWebApi',
                    'action'	 => 'issues',
                    'versions'	 => ['1.12'],
                ],
            ],
        ],
    ],
];
