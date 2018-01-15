<?php

namespace RipsModule\Service;

use ZendServer\FS\FS;

class Zip {

    public function create($rootPath, array $fileList, $zipName) {
        // Create temporary zip file with a unique name
        $path = FS::createPath(
            getCfgVar('zend.temp_dir'),
            $zipName
        );
        
        // Create a zip archive from code tracing information
        try {
            $zip = new \ZipArchive();
            $zip->open($path, \ZipArchive::CREATE);
            
            foreach ($fileList as $fileToScan) {
                $fileToScan = $rootPath . '/' . ltrim(trim($fileToScan), '/');
                $zip->addFile($rootPath . '/' . $fileToScan, $fileToScan);
                
                if (is_dir($fileToScan)) {
                    error_log("e: $fileToScan \n", 3, "/tmp/test.log");
                    $files = new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator($fileToScan),
                        \RecursiveIteratorIterator::LEAVES_ONLY
                    );
                    
                    foreach ($files as $name => $file)
                    {
                        // Skip directories (they would be added automatically)
                        if ($file->isDir()) {
                            continue;
                        }
                        
                        $extensions = explode('.', basename($file->getPathname()));
                        if (!in_array(end($extensions), ['php', 'php3', 'php4', 'php5', 'phtml', 'inc'])) {
                            continue;
                        }
                        
                        // Get real and relative path for current file
                        $filePath = $file->getRealPath();
                        
                        $relativePath = substr($filePath, strlen($fileToScan) + 1);
                        
                        // Add current file to archive
                        $zip->addFile($filePath, basename($fileToScan) . '/' . $relativePath);
                    }
                }
                else {
                    $extensions = explode('.', basename($fileToScan));
                    if (!in_array(end($extensions), ['php', 'php3', 'php4', 'php5', 'phtml', 'inc'])) {
                        continue;
                    }
                    $zip->addFile($fileToScan, basename($fileToScan));
                }
            }
            
            $zip->close();
        } catch (\Exception $e) {
            throw new \Exception("Creating zip archive from ZendServer application source code failed: {$e->getMessage()}");
        }
        
        return $path;
    }
}
