<?php

namespace RipsModule;

use RipsModule\Db\Connector;
use RipsModule\Model\Settings;
use RIPS\Connector\API;
use Zend\Db\TableGateway\TableGateway;
use Zend\Mvc\MvcEvent;
use Zend\ServiceManager\ServiceManager;
use Zend\Log\Logger;
use Zend\Log\Writer\Stream;
use ZendServer\Log\Log;
use RipsModule\Service\RipsApp;

class Module {

	/**
	 * The entry point of the module
     *
	 * @param MvcEvent $e
	 */
	public function onBootstrap(MvcEvent $e) {
        // Include own vendoring folder because of RIPS API dependencies
        require __DIR__ . '/../../vendor/autoload.php';
	}

    /**
     * Initialize the standard ZendServer logger
     *
     * @param MvcEvent $e
     */
    public function initializeLog(MvcEvent $e) {
        // Use the default ui log for errors
        // TODO: Use own log file?
        $writer = new Stream(FS::createPath(dirname(ini_get('error_log')), 'zend_server_ui.log'), 'a+');
        $logger = new Logger();
        $logger->addWriter($writer);

        Logger::registerErrorHandler($logger);
        Logger::registerExceptionHandler($logger);

        if (is_null(Log::getLogger())) {
            Log::init($logger, self::config('logging', 'logVerbosity'));
        }

        Log::debug('log initialized');
    }

	/**
	 * Tell the autoloader where to look for module's source files
     *
	 * @return array
	 */
	public function getAutoloaderConfig() {
		return [
			'Zend\Loader\StandardAutoloader' => [
				'namespaces' => [
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
				],
			],
		];
	}

	/**
	 * Return module's configuration. For convenience, the configuration is defined
	 * in module.config.php file under "config" folder
     *
	 * @return array
	 */
	public function getConfig() {
		return include __DIR__ . '/config/module.config.php';
	}

    /**
     * Service configuration
     *
     * @return array
     */
    public function getServiceConfig() {
        return [
            'invokables' => [
                \RipsModule\Service\DocRoot::class => \RipsModule\Service\DocRoot::class,
                \RipsModule\Service\ScanSpec::class => \RipsModule\Service\ScanSpec::class,
                \RipsModule\Service\Zip::class => \RipsModule\Service\Zip::class
            ],
            'factories' => [
                'RipsModule\Model\Settings' => function(ServiceManager $sm) {
                    $tableGateway = new TableGateway('RIPS_SETTINGS', $sm->get(Connector::DB_CONTEXT_RIPS));

                    $model = new Settings();
                    $model->setTableGateway($tableGateway);

                    return $model;
                },
                Connector::DB_CONTEXT_RIPS => function(ServiceManager $sm) {
                    $connector = new Connector();
                    $adapter = $connector->createDbAdapter(Connector::DB_CONTEXT_RIPS);
                    return $adapter;
                },
                'RIPS\Api' => function(ServiceManager $sm) {
                    // Get RIPS applications
                    $settings = $sm->get('RipsModule\Model\Settings')->getSettings();

                    if (empty($settings['email']) || empty($settings['password'])) {
                        throw new \InvalidArgumentException("Email and password must not be empty");
                    }

                    $api = new API(
                        $settings['email'],
                        $settings['password'],
                        ['base_uri' => $settings['api_url']]
                    );

                    return $api;
                },
                \RipsModule\Service\RipsApp::class => function(ServiceManager $sm) {
                    // Get RIPS applications
                    $api = $sm->get('RIPS\Api');

                    return new \RipsModule\Service\RipsApp($api);
                }
            ],
        ];
    }

}
