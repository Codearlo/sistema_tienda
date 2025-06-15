<?php
/**
 * SISTEMA DE CONTROL DE CACHE
 * Fuerza la recarga de archivos CSS, JS y PHP
 */

// Headers para evitar cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

/**
 * Genera un timestamp único para cada archivo
 * Basado en la fecha de modificación del archivo
 */
function getCacheBuster($filePath) {
    if (file_exists($filePath)) {
        return filemtime($filePath);
    }
    return time(); // Si el archivo no existe, usa timestamp actual
}

/**
 * Genera URL con cache buster automático
 */
function assetUrl($path) {
    $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($path, '/');
    $version = getCacheBuster($fullPath);
    return $path . '?v=' . $version;
}

/**
 * Incluye CSS con cache buster automático
 */
function includeCss($cssPath) {
    $url = assetUrl($cssPath);
    echo '<link rel="stylesheet" href="' . $url . '">' . PHP_EOL;
}

/**
 * Incluye JS con cache buster automático
 */
function includeJs($jsPath) {
    $url = assetUrl($jsPath);
    echo '<script src="' . $url . '"></script>' . PHP_EOL;
}

/**
 * Fuerza recompilación de archivos CSS en desarrollo
 */
function forceCssReload() {
    echo '<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">' . PHP_EOL;
    echo '<meta http-equiv="Pragma" content="no-cache">' . PHP_EOL;
    echo '<meta http-equiv="Expires" content="0">' . PHP_EOL;
}
?>