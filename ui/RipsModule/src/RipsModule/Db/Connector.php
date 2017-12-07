<?php

namespace RipsModule\Db;

use ZendServer\FS\FS;
use Zend\Db\Adapter\Adapter;
use ZendServer\Log\Log;
use Bootstrap\Mapper;
use Application\ConfigAwareInterface;
use Zend\ServiceManager\Exception\InvalidArgumentException;

class Connector  {

    const DB_CONTEXT_RIPS = 'ripsdbadapter';

    /**
     * @var array[\Zend\Db\Adapter\Adapter]
     */
    protected static $dbs;

    /**
     * @var array
     */
    protected $dsns = [
        self::DB_CONTEXT_RIPS => 'rips.db',
    ];

    /**
     * @param string $name
     * @return Adapter
     */
    public function createDbAdapter($name) {
        return $this->createSqliteAdapter($name);
    }

    /**
     * @param string $name
     * @return \Zend\Db\Adapter\Adapter
     */
    private function createSqliteAdapter($name) {
        if (!isset($this->dsns[strtolower($name)])) {
            throw new InvalidArgumentException('Unknown database name');
        }

        if (!isset(static::$dbs[$name])) {
            $path = FS::createPath(getCfgVar('zend.data_dir'), 'db', $this->dsns[strtolower($name)]);

            $install = false;
            if (!file_exists($path)) {
                $install = true;
            }

            $pdoConfig = [
                'driver' => 'Pdo',
                'dsn' => "sqlite:{$path}",
                'username' => '',
                'password' => '',
                'driver_options' => [],
            ];

            $adapter = new Adapter($pdoConfig);
            $adapter->query("PRAGMA busy_timeout=30000");

            if ($install) {
                // Create Database
                $installFile = FS::getFileObject(__DIR__ . '/../../../config/install.sql');
                $dataFile = FS::getFileObject(__DIR__ . '/../../../config/data.sql');

                try {
                    Log::notice('Create RIPS settings db');
                    $adapter->query($installFile->readAll(), Adapter::QUERY_MODE_EXECUTE);
                    $adapter->query($dataFile->readAll(), Adapter::QUERY_MODE_EXECUTE);
                } catch (\Exception $e) {
                    Log::err($e->getMessage());
                }
            }

            static::$dbs[$name] = $adapter;
        }

        return static::$dbs[$name];
    }
}
