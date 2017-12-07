<?php

namespace RipsModule\Controller;

use RIPS\Connector\API;
use ZendServer\Mvc\Controller\WebAPIActionController;
use ZendServer\Log\Log;
use WebAPI\View\WebApiResponseContainer;
use ZendServer\FS\FS;

class WebApiController extends WebAPIActionController {
    /**
     * Just read the settings from the DB and serve them
     *
     * @return array
     */
	public function settingsAction() {
        $this->isMethodGet();

        $settings = $this->getServiceLocator()->get('RipsModule\Model\Settings');
        return [
            'settings' => $settings->getSettings(),
        ];
    }

    /**
     * Store settings in the DB
     *
     * @return WebApiResponseContainer
     */
    public function storeSettingsAction() {
        $this->isMethodPost();

        // Get and check the parameters
        $params = $this->getParameters([
            'username' => '',
            'password' => '',
            'api_url' => '',
            'ui_url' => '',
        ]);

        $this->validateMandatoryParameters($params, ['username', 'password', 'api_url', 'ui_url']);

        // Store the settings
        $settings = $this->getServiceLocator()->get('RipsModule\Model\Settings');
        $settings->storeSettings($params->toArray());

        return new WebApiResponseContainer([
            'success' => '1'
        ]);
    }

    /**
     * Get current applications from Zend and RIPS
     *
     * @return array
     * @throws \Exception
     */
    public function currentApplicationsAction() {
        $this->isMethodGet();

        // Get ZEND applications
        $deploymentModel = $this->getLocator()->get('Deployment\Model');
        $ids = $deploymentModel->getAllApplicationIds();

        $zendApps = [];
        foreach ($ids as $id) {
            $app = $deploymentModel->getApplicationById($id);

            $zendApps[] = [
                'id' => $id,
                'name' => $app->getUserApplicationName(),
                'path' => $app->getInstallPath(),
            ];
        }

        // Get RIPS applications
        $settings = $this->getServiceLocator()->get('RipsModule\Model\Settings');
        $settings = $settings->getSettings();

        $ripsApps = [];

        if (!empty($settings['username']) && !empty($settings['username'])) {
            $api = new API($settings['username'], $settings['password'], ['base_uri' => $settings['api_url']]);

            try {
                $apps = $api->applications->getAll();
            } catch (\Exception $e) {
                throw new \Exception($e->getCode() . ': Getting applications failed: ' . $e->getMessage());
            }

            foreach ($apps as $app) {
                $ripsApps[] = [
                    'id' => $app->id,
                    'name' => $app->name,
                ];
            }
        }

        return [
            'zendApps' => $zendApps,
            'ripsApps' => $ripsApps,
        ];
    }

    /**
     * Start a new scan
     *
     * @return WebApiResponseContainer
     * @throws \Exception
     */
    public function scanAction() {
        $this->isMethodPost();

        // Check the given parameters
        $params = $this->getParameters([
            'rips_id' => 0,
            'zend_path' => '',
            'version' => '',
        ]);

        $this->validateMandatoryParameters($params, ['rips_id', 'zend_path', 'version']);
        $params['rips_id'] = (int)$params['rips_id'];

        if ($params['rips_id'] === 0 || empty($params['zend_path']) || empty($params['version'])) {
            throw new \Exception('Data missing');
        }

        // Create temporary zip file with a unique name
        $path = FS::createPath(
            getCfgVar('zend.temp_dir'),
            'rips_' .  $params['rips_id'] . '_' . (new \DateTime())->getTimestamp() . '.zip'
        );

        // Create a zip archive from the ZendServer application source code
        try {
            $zip = new \ZipArchive();
            $zip->open($path, \ZipArchive::CREATE);

            $directoryIterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($params['zend_path']));
            foreach ($directoryIterator as $file) {
                if ($file->isDir()) {
                    continue;
                }

                $extensions = explode('.', basename($file->getPathname()));
                if (!in_array(end($extensions), ['php', 'php3', 'php4', 'php5', 'phtml', 'inc'])) {
                    continue;
                }

                $localFilename = str_replace($params['zend_path'], '', $file->getPathname());
                if ($localFilename[0] === '/') {
                    $localFilename = substr($localFilename, 1);
                }

                $zip->addFile($file->getPathname(), $localFilename);
            }

            $zip->close();
        } catch (\Exception $e) {
            throw new \Exception("Creating zip archive from ZendServer application source code failed: {$e->getMessage()}");
        }

        // Call the upload and start scan API endpoints
        $settings = $this->getServiceLocator()->get('RipsModule\Model\Settings');
        $settings = $settings->getSettings();

        $api = new API($settings['username'], $settings['password'], ['base_uri' => $settings['api_url']]);

        try {
            $upload = $api->applications->uploads()->create($params['rips_id'], basename($path), $path);
            $api->applications->scans()->create($params['rips_id'], ['version' => $params['version'], 'upload' => (int)$upload->id]);
        } catch (\Exception $e) {
            throw new \Exception($e->getCode() . ': Starting scan failed: ' . $e->getMessage());
        }

        // Remove the temporary archive (was already uploaded)
        unlink($path);

        return new WebApiResponseContainer([
            'success' => '1'
        ]);
    }

    /**
     * Get RIPS scans
     *
     * @return WebApiResponseContainer
     * @throws \Exception
     */
    public function scansAction() {
        $this->isMethodGet();

        $scans = [];
        $settings = $this->getServiceLocator()->get('RipsModule\Model\Settings');
        $settings = $settings->getSettings();

        if (!empty($settings['username']) && !empty($settings['password'])) {
            $api = new API($settings['username'], $settings['password'], ['base_uri' => $settings['api_url']]);

            try {
                $scans = $api->applications->scans()->getAll(null, ['showScanSeverityDistributions' => 1, 'orderBy[id]' => 'desc', 'limit' => 20]);
            } catch (\Exception $e) {
                throw new \Exception($e->getCode() . ': Getting scans failed: ' . $e->getMessage());
            }
        }

        return new WebApiResponseContainer([
            'scans' => $scans,
            'ui_url' => $settings['ui_url'],
        ]);
    }

    /**
     * Get RIPS issues
     *
     * @return WebApiResponseContainer
     * @throws \Exception
     */
    public function issuesAction() {
        $this->isMethodGet();

        $params = $this->getParameters([
            'application_id' => 0,
            'scan_id' => 0,
        ]);

        $this->validateMandatoryParameters($params, ['application_id', 'scan_id']);

        $settings = $this->getServiceLocator()->get('RipsModule\Model\Settings');
        $settings = $settings->getSettings();

        $api = new API($settings['username'], $settings['password'], ['base_uri' => $settings['api_url']]);

        try {
            $issues = $api->applications->scans()->issues()->getAll($params['application_id'], $params['scan_id'], ['minimal' => 1]);
        } catch (\Exception $e) {
            throw new \Exception($e->getCode() . ': Getting issues failed: ' . $e->getMessage());
        }

        return new WebApiResponseContainer([
            'issues' => $issues,
            'ui_url' => $settings['ui_url'],
        ]);
    }

    /**
     * Get single RIPS scan
     *
     * @return WebApiResponseContainer
     * @throws \Exception
     */
    public function scanDetailsAction() {
        $this->isMethodGet();

        $params = $this->getParameters([
            'application_id' => 0,
            'scan_id' => 0,
        ]);

        $this->validateMandatoryParameters($params, array('application_id', 'scan_id'));

        $settings = $this->getServiceLocator()->get('RipsModule\Model\Settings');
        $settings = $settings->getSettings();

        $api = new API($settings['username'], $settings['password'], ['base_uri' => $settings['api_url']]);

        try {
            $scan = $api->applications->scans()->getById($params['application_id'], $params['scan_id']);
            $stats = $api->applications->scans()->getStats($params['application_id'], $params['scan_id']);
        } catch (\Exception $e) {
            throw new \Exception($e->getCode() . ': Getting scan failed: ' . $e->getMessage());
        }

        // Create associative array of scanned issue types
        $typeInfos = [];
        foreach ($scan->issue_types as $type) {
            $typeInfos[$type->tag] = $type;
        }

        // Create type data that is easier to handle by the ui
        $typeData = [];
        foreach ($stats->issue_types as $key => $value) {
            $typeData[] = [
                'type' => $typeInfos[$key],
                'amount' => $value,
            ];
        }

        // Sort the data by severity
        usort($typeData, function($a, $b) {
            return $b['type']->severity - $a['type']->severity;
        });

        return new WebApiResponseContainer([
            'scan' => $scan,
            'stats' => $stats,
            'types' => $typeData,
            'ui_url' => $settings['ui_url'],
        ]);
    }
}
