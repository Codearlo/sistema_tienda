<?php
/**
 * SISTEMA DE CONTROL DE CACHE OPTIMIZADO
 * Archivo: includes/cache_control.php
 */

// Headers básicos para evitar cache en desarrollo
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

/**
 * Genera cache buster basado en modificación de archivo
 */
function getCacheBuster($filePath) {
    return file_exists($filePath) ? filemtime($filePath) : time();
}

/**
 * Genera URL con versión automática
 */
function assetUrl($path) {
    $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($path, '/');
    $version = getCacheBuster($fullPath);
    return $path . '?v=' . $version;
}

/**
 * Incluir CSS con cache buster
 */
function includeCss($cssPath) {
    $url = assetUrl($cssPath);
    echo '<link rel="stylesheet" href="' . $url . '">' . PHP_EOL;
}

/**
 * Incluir JS con cache buster
 */
function includeJs($jsPath) {
    $url = assetUrl($jsPath);
    echo '<script src="' . $url . '"></script>' . PHP_EOL;
}

/**
 * Meta tags para forzar recarga en desarrollo
 */
function forceCssReload() {
    echo '<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">' . PHP_EOL;
    echo '<meta http-equiv="Pragma" content="no-cache">' . PHP_EOL;
    echo '<meta http-equiv="Expires" content="0">' . PHP_EOL;
}

/**
 * Incluir múltiples archivos CSS
 */
function includeMultipleCss($cssFiles) {
    foreach ($cssFiles as $cssFile) {
        includeCss($cssFile);
    }
}

/**
 * Incluir múltiples archivos JS
 */
function includeMultipleJs($jsFiles) {
    foreach ($jsFiles as $jsFile) {
        includeJs($jsFile);
    }
}

/**
 * Headers de cache para producción
 */
function setProductionCacheHeaders($maxAge = 3600) {
    header("Cache-Control: public, max-age=" . $maxAge);
    header("Expires: " . gmdate('D, d M Y H:i:s', time() + $maxAge) . ' GMT');
    header("Last-Modified: " . gmdate('D, d M Y H:i:s', filemtime(__FILE__)) . ' GMT');
    
    $etag = md5_file(__FILE__);
    header("ETag: " . $etag);
    
    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag) {
        header('HTTP/1.1 304 Not Modified');
        exit();
    }
    
    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
        $ifModifiedSince = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
        $lastModified = filemtime(__FILE__);
        if ($lastModified <= $ifModifiedSince) {
            header('HTTP/1.1 304 Not Modified');
            exit();
        }
    }
}

/**
 * Configuración automática según entorno
 */
function setupCacheEnvironment() {
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
        setProductionCacheHeaders(31536000); // 1 año
    } else {
        forceCssReload();
    }
}
?>