<?php

namespace RipsModule\Controller;

use ZendServer\Mvc\Controller\WebAPIActionController;
use WebAPI\View\WebApiResponseContainer;

class WebApiController extends WebAPIActionController {
    /**
     * Just read the settings from the DB and serve them
     *
     * @return array
     */
    public function settingsAction() {
        $this->isMethodGet();
        
        $settings = $this->getServiceLocator()->get('RipsModule\Model\Settings')->getSettings();
        
        $settings['password'] = str_pad('', strlen($settings['password']), '-');
        
        return [
            'settings' => $settings,
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
        
        $settings = $this->getLocator()->get('RipsModule\Model\Settings')->getSettings();
        if (!$this->isConfigurationValid($settings)) {
            return [
                'zendApps' => $zendApps,
                'ripsApps' => [],
            ];
        }
        
        $ripsApps = $this->getServiceLocator()->get('RipsModule\Service\RipsApp')->getAll();
        
        return [
            'zendApps' => $zendApps,
            'ripsApps' => $ripsApps,
        ];
    }
    
    /**
     * Get current Traces from Zend Server and RIPS applications
     *
     * @return array
     * @throws \Exception
     */
    public function currentDocRootsAction() {
        $this->isMethodGet();
        
        $vhostMapper = $this->getLocator()->get('Vhost\Mapper\Vhost');
        
        try {
            $vhostsResult = $vhostMapper->getVhosts();
            $vhosts = array();
            
            foreach ($vhostsResult as $vhost) {
                $vhosts[] = $vhost;
            }
            
        } catch (\Exception $ex) {
            throw new \Exception(_t('Could not retrieve vhost information'), \Exception::INTERNAL_SERVER_ERROR, $ex);
        }
        
        $settings = $this->getLocator()->get('RipsModule\Model\Settings')->getSettings();
        if (!$this->isConfigurationValid($settings)) {
            return [
                'vhosts' => $vhosts,
                'ripsApps' => [],
            ];
        }
        
        $ripsApps = $this->getServiceLocator()->get('RipsModule\Service\RipsApp')->getAll();
        
        return [
            'vhosts' => $vhosts,
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
            'new_app_name' => '',
        ]);
        
        $this->validateMandatoryParameters($params, ['rips_id', 'zend_path', 'version']);
        $params['rips_id'] = (int)$params['rips_id'];
        
        if (($params['rips_id'] === 0 && empty($params['new_app_name'])) || empty($params['zend_path']) ||
            empty($params['version'])) {
                throw new \Exception('Data missing');
            }
            
            $zipName = 'rips_' .  $params['rips_id'] . '_' . (new \DateTime())->getTimestamp() . '.zip';
            $zipPath = $this->getLocator()->get(\RipsModule\Service\Zip::class)->create(
                dirname($params['zend_path']),
                [basename($params['zend_path'])],
                $zipName
                );
            
            $api = $this->getLocator()->get('\RIPS\Api');
            
            if ($params['rips_id'] === 0) {
                try {
                    $application = $api->applications->create(['name' => $params['new_app_name']]);
                    $params['rips_id'] = (int)$application->id;
                } catch (\Exception $e) {
                    throw new \Exception($e->getCode() . ': Creating new application failed: ' . $e->getMessage());
                }
            }
            
            try {
                $upload = $api->applications->uploads()->create($params['rips_id'], basename($zipPath), $zipPath);
                $api->applications->scans()->create($params['rips_id'], ['version' => $params['version'], 'upload' => (int)$upload->id]);
            } catch (\Exception $e) {
                throw new \Exception($e->getCode() . ': Starting scan failed: ' . $e->getMessage());
            }
            
            // Remove the temporary archive (was already uploaded)
            unlink($zipPath);
            
            return new WebApiResponseContainer([
                'success' => '1'
            ]);
    }
    
    /**
     * Start a new scan based on a Code Trace
     *
     * @return WebApiResponseContainer
     * @throws \Exception
     */
    public function scanSpecAction() {
        $this->isMethodPost();
        
        // Check the given parameters
        $params = $this->getParameters([
            'vhost_id' => ''
        ]);
        
        $this->validateMandatoryParameters($params, ['vhost_id']);
        
        try {
            $vhostMapper = $this->getLocator()->get('Vhost\Mapper\Vhost');
            $vhost = $vhostMapper->getVhostById($params['vhost_id']);
        } catch (\Exception $ex) {
            throw new \Exception(_t('Could not retrieve vhost information'), \Exception::INTERNAL_SERVER_ERROR, $ex);
        }
        
        $docRoot = $this->getLocator()->get(\RipsModule\Service\DocRoot::class);
        $pathToGuess = $docRoot->getByVhost($vhost);
        
        $scanSpec = $this->getLocator()->get(\RipsModule\Service\ScanSpec::class);
        $content = $scanSpec->getByPath($pathToGuess);
        
        return new WebApiResponseContainer([
            'success' => '1',
            'vendorRemoved' => $scanSpec->isVendorRemoved(),
            'scanSpec' => join("\n", $content)
        ]);
    }
    
    /**
     * Start a new scan based on a Code Trace
     *
     * @return WebApiResponseContainer
     * @throws \Exception
     */
    public function scanDocRootAction() {
        $this->isMethodPost();
        
        // Check the given parameters
        $params = $this->getParameters([
            'rips_id' => 0,
            'scan_spec' => '',
            'vhost_id' => '',
            'version' => '',
            'new_app_name' => '',
        ]);
        
        $this->validateMandatoryParameters($params, ['rips_id', 'scan_spec', 'vhost_id', 'version']);
        $params['rips_id'] = (int)$params['rips_id'];
        
        if (($params['rips_id'] === 0 && empty($params['new_app_name'])) || empty($params['scan_spec']) ||
            empty($params['vhost_id']) || empty($params['version'])) {
                throw new \Exception('Data missing');
            }
            
            $vhostMapper = $this->getLocator()->get('Vhost\Mapper\Vhost');
            
            try {
                $vhostsResult = $vhostMapper->getVhosts();
                $vhost = $vhostMapper->getVhostById($params['vhost_id']);
            } catch (\Exception $ex) {
                throw new \Exception(_t('Could not retrieve vhost information'), \Exception::INTERNAL_SERVER_ERROR, $ex);
            }
            
            $docRoot = $this->getLocator()->get(\RipsModule\Service\DocRoot::class);
            $parent = $docRoot->getByVhost($vhost);
            
            $filesToScan = explode("\n", $params['scan_spec']);
            
            $zipName = 'rips_' .  $params['rips_id'] . '_' . (new \DateTime())->getTimestamp() . '.zip';
            $zipPath = $this->getLocator()->get(\RipsModule\Service\Zip::class)->create($parent, $filesToScan, $zipName);
            
            $api = $this->getLocator()->get('\RIPS\Api');
            
            if ($params['rips_id'] === 0) {
                try {
                    $application = $api->applications->create(['name' => $params['new_app_name']]);
                    $params['rips_id'] = (int)$application->id;
                } catch (\Exception $e) {
                    throw new \Exception($e->getCode() . ': Creating new application failed: ' . $e->getMessage());
                }
            }
            
            try {
                $upload = $api->applications->uploads()->create($params['rips_id'], basename($zipPath), $zipPath);
                $api->applications->scans()->create($params['rips_id'], ['version' => $params['version'], 'upload' => (int)$upload->id]);
            } catch (\Exception $e) {
                throw new \Exception($e->getCode() . ': Starting scan failed: ' . $e->getMessage());
            }
            
            // Remove the temporary archive (was already uploaded)
            unlink($zipPath);
            
            return new WebApiResponseContainer([
                'success' => '1',
                'path' => $zipPath
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
        
        $params = $this->getParameters([
            'offset' => 0,
            'limit' => 20,
        ]);
        
        $settings = $this->getLocator()->get('RipsModule\Model\Settings')->getSettings();
        if (!$this->isConfigurationValid($settings)) {
            return new WebApiResponseContainer([
                'scans' => [],
                'ui_url' => isset($settings['ui_url']) && !empty($settings['ui_url']) ? $settings['ui_url'] : '',
            ]);
        }
        
        $scans = [];
        $api = $this->getLocator()->get('\RIPS\Api');
        
        try {
            $scans = $api->applications->scans()->getAll(null, [
                'showScanSeverityDistributions' => 1,
                'orderBy[id]' => 'desc',
                'offset' => (int)$params['offset'],
                'limit' => (int)$params['limit'] + 1,
            ]);
        } catch (\Exception $e) {
            throw new \Exception($e->getCode() . ': Getting scans failed: ' . $e->getMessage());
        }
        
        $more = false;
        if (count($scans) > (int)$params['limit']) {
            array_pop($scans);
            $more = true;
        }
        
        return new WebApiResponseContainer([
            'scans' => $scans,
            'ui_url' => $settings['ui_url'],
            'count' => count($scans),
            'more' => $more
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
            'offset' => 0,
            'limit' => 200,
        ]);
        
        $this->validateMandatoryParameters($params, ['application_id', 'scan_id']);
        
        $api = $this->getLocator()->get('\RIPS\Api');
        
        try {
            $issues = $api->applications->scans()->issues()->getAll($params['application_id'], $params['scan_id'], [
                'minimal' => 1,
                'orderBy[severity]' => 'desc',
                'orderBy[id]' => 'desc',
                'offset' => (int)$params['offset'],
                'limit' => (int)$params['limit'],
            ]);
        } catch (\Exception $e) {
            throw new \Exception($e->getCode() . ': Getting issues failed: ' . $e->getMessage());
        }
        
        $settings = $this->getLocator()->get('RipsModule\Model\Settings')->getSettings();
        
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
        
        $api = $this->getLocator()->get('\RIPS\Api');
        
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
            
            $settings = $this->getLocator()->get('RipsModule\Model\Settings')->getSettings();
            
            return new WebApiResponseContainer([
                'scan' => $scan,
                'stats' => $stats,
                'types' => $typeData,
                'ui_url' => $settings['ui_url'],
            ]);
    }
    
    /**
     * Check if the given settings parameter are valid configurations to determine if the user already set-up
     * the RIPS account.
     *
     * @param array $settings
     * @return boolean
     */
    private function isConfigurationValid($settings) {
        if (!isset($settings['username']) || empty($settings['username']) ||
            !isset($settings['password']) || empty($settings['password'])) {
                return false;
            }
            return true;
    }
}
