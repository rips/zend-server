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
     * Get current Traces from Zend Server and RIPS applications
     *
     * @return array
     * @throws \Exception
     */
    public function currentDocRootsAction() {
        $this->isMethodGet();
        //exit('stop');
        // Get code traces
        $vhostMapper = $this->getLocator()->get('Vhost\Mapper\Vhost');
        
   
        
        try {
            $vhostsResult = $vhostMapper->getVhosts();
            $vhosts = array();

            foreach ($vhostsResult as $vhost) {
                $vhosts[] = $vhost;
            }

        } catch (\Exception $ex) {
            throw new Exception(_t('Could not retrieve tracefiles\' information'), Exception::INTERNAL_SERVER_ERROR, $ex);
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
        
        $vhostMapper = $this->getLocator()->get('Vhost\Mapper\Vhost');
        
        try {
            $vhostsResult = $vhostMapper->getVhosts();
            $vhost = $vhostMapper->getVhostById($params['vhost_id']);

            
        } catch (\Exception $ex) {
            throw new \Exception(_t('Could not retrieve vhost information'), \Exception::INTERNAL_SERVER_ERROR, $ex);
        }
        
        $docRoot = rtrim($vhost->getDocRoot(),"/");
        $docRoot = (is_link($docRoot)) ? readlink($docRoot) : $docRoot;
        
        $parent = dirname($docRoot);
        $pathToGuess = (is_link($parent)) ? readlink($parent) : $parent;
        
        $content = scandir($pathToGuess);
        
        $vendorRemoved = false;
        $doNotScan = ['.', '..', 'docs', '.dockerignore', '.git', '.gitignore', 'composer.json', 'composer.lock', 'README.md', 'vendor'];
        
        if (in_array('vendor', $content))  $vendorRemoved = true;
        $content = array_diff($content, $doNotScan);
        
        $scanSpec = join("\n", $content);
        
        return new WebApiResponseContainer([
            'success' => '1',
            'vendorRemoved' => $vendorRemoved,
            'scanSpec' => $scanSpec
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
        ]);
        
        $this->validateMandatoryParameters($params, ['rips_id', 'scan_spec', 'vhost_id', 'version']);
        $params['rips_id'] = (int)$params['rips_id'];
        
        if ($params['rips_id'] === 0 || empty($params['scan_spec']) || empty($params['vhost_id']) || empty($params['version'])) {
            throw new \Exception('Data missing');
        }
        
        $this->validateMandatoryParameters($params, ['vhost_id']);
        
        $vhostMapper = $this->getLocator()->get('Vhost\Mapper\Vhost');
        
        try {
            $vhostsResult = $vhostMapper->getVhosts();
            $vhost = $vhostMapper->getVhostById($params['vhost_id']);
            
            
        } catch (\Exception $ex) {
            throw new \Exception(_t('Could not retrieve vhost information'), \Exception::INTERNAL_SERVER_ERROR, $ex);
        }
        
        $docRoot = rtrim($vhost->getDocRoot(),"/");
        $docRoot = (is_link($docRoot)) ? readlink($docRoot) : $docRoot;
        
        $parent = dirname($docRoot);
        $parent = (is_link($parent)) ? readlink($parent) : $parent;
        
        $filesToScan = explode("\n", $params['scan_spec']);
        
        // Create temporary zip file with a unique name
        $path = FS::createPath(
            getCfgVar('zend.temp_dir'),
            'rips_' .  $params['rips_id'] . '_' . (new \DateTime())->getTimestamp() . '.zip'
        );
        
        // Create a zip archive from code tracing information
        try {
            $zip = new \ZipArchive();
            $zip->open($path, \ZipArchive::CREATE);
            
            foreach ($filesToScan as $fileToScan) {
                $fileToScan = $parent . '/' . ltrim($fileToScan, '/');
                
                $zip->addFile($parent . '/' . $fileToScan, $fileToScan);
                
                if (is_dir($fileToScan)) {
                    $files = new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator($fileToScan),
                        \RecursiveIteratorIterator::LEAVES_ONLY
                    );
                    
                    foreach ($files as $name => $file)
                    {
                        // Skip directories (they would be added automatically)
                        if (!$file->isDir())
                        {
                            // Get real and relative path for current file
                            $filePath = $file->getRealPath();
                            $relativePath = substr($filePath, strlen($fileToScan) + 1);

                            // Add current file to archive
                            $zip->addFile($filePath, basename($fileToScan) . '/' . $relativePath);
                        }
                    }
                }
                else {
                    $zip->addFile($fileToScan, basename($fileToScan));
                }
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
            'success' => '1',
            'path' => $path
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
