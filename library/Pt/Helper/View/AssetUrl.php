<?php

class Pt_Helper_View_AssetUrl extends Zend_View_Helper_Abstract
{
    public function assetUrl(string $relativePath): string
    {
        $publicPath = realpath(APPLICATION_PATH . '/../public');
        $absolute = $publicPath . DIRECTORY_SEPARATOR . ltrim($relativePath, '/');
        $version = @filemtime($absolute) ?: APP_VERSION;
        $base = rtrim($this->view->baseUrl(), '/');
        return $base . '/' . ltrim($relativePath, '/') . '?v=' . $version;
    }
}
