<?php

namespace RipsModule\Service;

class ScanSpec {
    
    private $doNotScan = [
        '.', '..', 'docs', '.dockerignore', '.git', '.gitignore', 'composer.json', 'composer.lock', 'README.md', 'vendor'
    ];
    
    private $isVendorRemoved = false;

    public function getByPath($path) {
        $content = scandir($path);
        
        $doNotScan = [];
        
        if (in_array('vendor', $content))  $this->isVendorRemoved = true;
        return array_diff($content, $this->doNotScan);
    }
    
    public function isVendorRemoved() {
        return $this->isVendorRemoved;
    }
}
