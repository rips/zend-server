<?php

namespace RipsModule\Service;

use RIPS\Connector\API;

class RipsApp {

    private $api;

    public function __construct(API $api) {
        $this->api = $api;
    }

    public function getAll() {
        try {
            $apps = $this->api->applications->getAll(['orderBy' => '{"name":"asc"}'])->getDecodedData();
        } catch (\Exception $e) {
            throw new \Exception($e->getCode() . ': Getting applications failed: ' . $e->getMessage());
        }

        $ripsApps = [];

        foreach ($apps as $app) {
            $ripsApps[] = [
                'id' => $app->id,
                'name' => $app->name,
            ];
        }

        return $ripsApps;
    }
}


