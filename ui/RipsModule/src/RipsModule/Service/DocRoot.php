<?php

namespace RipsModule\Service;

class DocRoot {

    public function getByVhost($vhost) {
        $docRoot = rtrim($vhost->getDocRoot(), "/");
        $docRoot = (is_link($docRoot)) ? readlink($docRoot) : $docRoot;
        
        $parent = dirname($docRoot);
        
        return (is_link($parent)) ? readlink($parent) : $parent;
    }
}
