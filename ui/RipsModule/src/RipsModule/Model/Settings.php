<?php

namespace RipsModule\Model;

use \Zend\Db\TableGateway\TableGateway;
use ZendServer\Log\Log;

class Settings {

    /**
     * @var TableGateway
     */
    private $tableGateway;

    public function setTableGateway($tableGateway) {
        $this->tableGateway = $tableGateway;
    }

    public function getSettings() {
        // The settings are all stored in just the first row
        $rows = $this->tableGateway->select(['id' => 1]);
        return $rows->current();
    }

    public function storeSettings(array $data) {
        $this->tableGateway->update($data, ['id' => 1]);
    }
}
