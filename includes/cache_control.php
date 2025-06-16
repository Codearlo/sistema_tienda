<?php
/**
 * SISTEMA DE CONTROL DE CACHE
 * Archivo: includes/cache_control.php
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

/**
 * Incluye CSS con versión manual
 */
function includeCssVersion($cssPath, $version = null) {
    if ($version === null) {
        $version = time();
    }
    echo '<link rel="stylesheet" href="' . $cssPath . '?v=' . $version . '">' . PHP_EOL;
}

/**
 * Incluye JS con versión manual
 */
function includeJsVersion($jsPath, $version = null) {
    if ($version === null) {
        $version = time();
    }
    echo '<script src="' . $jsPath . '?v=' . $version . '"></script>' . PHP_EOL;
}

/**
 * Limpia la caché del navegador para un archivo específico
 */
function clearFileCache($filePath) {
    if (file_exists($filePath)) {
        touch($filePath);
        return true;
    }
    return false;
}

/**
 * Genera headers de caché para archivos estáticos
 */
function setStaticCacheHeaders($maxAge = 3600) {
    header("Cache-Control: public, max-age=" . $maxAge);
    header("Expires: " . gmdate('D, d M Y H:i:s', time() + $maxAge) . ' GMT');
    header("Last-Modified: " . gmdate('D, d M Y H:i:s', filemtime(__FILE__)) . ' GMT');
}

/**
 * Genera headers para evitar caché en páginas dinámicas
 */
function setNoCacheHeaders() {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
}

/**
 * Verifica si un archivo ha sido modificado
 */
function isFileModified($filePath, $lastModified) {
    if (!file_exists($filePath)) {
        return false;
    }
    
    return filemtime($filePath) > $lastModified;
}

/**
 * Genera ETag para un archivo
 */
function generateETag($filePath) {
    if (!file_exists($filePath)) {
        return false;
    }
    
    $mtime = filemtime($filePath);
    $size = filesize($filePath);
    
    return md5($filePath . $mtime . $size);
}

/**
 * Verifica headers de caché del navegador
 */
function checkBrowserCache($filePath) {
    if (!file_exists($filePath)) {
        return false;
    }
    
    $etag = generateETag($filePath);
    $lastModified = filemtime($filePath);
    
    // Verificar If-None-Match (ETag)
    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag) {
        header('HTTP/1.1 304 Not Modified');
        header('ETag: ' . $etag);
        exit();
    }
    
    // Verificar If-Modified-Since
    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
        $ifModifiedSince = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
        if ($lastModified <= $ifModifiedSince) {
            header('HTTP/1.1 304 Not Modified');
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
            exit();
        }
    }
    
    return false;
}

/**
 * Incluye múltiples archivos CSS
 */
function includeMultipleCss($cssFiles) {
    foreach ($cssFiles as $cssFile) {
        includeCss($cssFile);
    }
}

/**
 * Incluye múltiples archivos JS
 */
function includeMultipleJs($jsFiles) {
    foreach ($jsFiles as $jsFile) {
        includeJs($jsFile);
    }
}

/**
 * Minifica CSS inline
 */
function minifyCss($css) {
    // Remover comentarios
    $css = preg_replace('/\/\*[^*]*\*+([^\/][^*]*\*+)*\//', '', $css);
    
    // Remover espacios en blanco innecesarios
    $css = preg_replace('/\s+/', ' ', $css);
    $css = preg_replace('/;\s*}/', '}', $css);
    $css = preg_replace('/\s*{\s*/', '{', $css);
    $css = preg_replace('/;\s*/', ';', $css);
    $css = preg_replace('/:\s*/', ':', $css);
    
    return trim($css);
}

/**
 * Minifica JavaScript inline
 */
function minifyJs($js) {
    // Remover comentarios de línea
    $js = preg_replace('/\/\/.*$/m', '', $js);
    
    // Remover comentarios de bloque
    $js = preg_replace('/\/\*[^*]*\*+([^\/][^*]*\*+)*\//', '', $js);
    
    // Remover espacios en blanco innecesarios
    $js = preg_replace('/\s+/', ' ', $js);
    
    return trim($js);
}

/**
 * Combina múltiples archivos CSS en uno
 */
function combineCssFiles($cssFiles, $outputFile) {
    $combinedCss = '';
    
    foreach ($cssFiles as $cssFile) {
        if (file_exists($cssFile)) {
            $css = file_get_contents($cssFile);
            $combinedCss .= "/* " . basename($cssFile) . " */\n";
            $combinedCss .= $css . "\n\n";
        }
    }
    
    // Minificar el CSS combinado
    $combinedCss = minifyCss($combinedCss);
    
    // Guardar el archivo combinado
    file_put_contents($outputFile, $combinedCss);
    
    return $outputFile;
}

/**
 * Combina múltiples archivos JS en uno
 */
function combineJsFiles($jsFiles, $outputFile) {
    $combinedJs = '';
    
    foreach ($jsFiles as $jsFile) {
        if (file_exists($jsFile)) {
            $js = file_get_contents($jsFile);
            $combinedJs .= "/* " . basename($jsFile) . " */\n";
            $combinedJs .= $js . "\n\n";
        }
    }
    
    // Minificar el JS combinado
    $combinedJs = minifyJs($combinedJs);
    
    // Guardar el archivo combinado
    file_put_contents($outputFile, $combinedJs);
    
    return $outputFile;
}

/**
 * Obtiene el tipo MIME de un archivo
 */
function getFileMimeType($filePath) {
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    
    $mimeTypes = [
        'css' => 'text/css',
        'js' => 'text/javascript',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'eot' => 'application/vnd.ms-fontobject'
    ];
    
    return isset($mimeTypes[$extension]) ? $mimeTypes[$extension] : 'application/octet-stream';
}

/**
 * Sirve un archivo estático con headers de caché apropiados
 */
function serveStaticFile($filePath, $maxAge = 86400) {
    if (!file_exists($filePath)) {
        header('HTTP/1.1 404 Not Found');
        exit();
    }
    
    // Verificar caché del navegador
    checkBrowserCache($filePath);
    
    // Configurar headers
    $mimeType = getFileMimeType($filePath);
    $lastModified = filemtime($filePath);
    $etag = generateETag($filePath);
    
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . filesize($filePath));
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
    header('ETag: ' . $etag);
    header('Cache-Control: public, max-age=' . $maxAge);
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $maxAge) . ' GMT');
    
    // Enviar el archivo
    readfile($filePath);
    exit();
}

/**
 * Limpia todos los archivos de caché
 */
function clearAllCache($cacheDir = 'cache/') {
    if (!is_dir($cacheDir)) {
        return false;
    }
    
    $files = glob($cacheDir . '*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    
    return true;
}

/**
 * Genera un hash único para el estado actual de los assets
 */
function getAssetsHash($assetPaths) {
    $hashData = '';
    
    foreach ($assetPaths as $path) {
        if (file_exists($path)) {
            $hashData .= $path . filemtime($path);
        }
    }
    
    return md5($hashData);
}

/**
 * Verifica si los assets necesitan ser regenerados
 */
function assetsNeedRegeneration($assetPaths, $cacheFile) {
    if (!file_exists($cacheFile)) {
        return true;
    }
    
    $currentHash = getAssetsHash($assetPaths);
    $cachedHash = file_get_contents($cacheFile . '.hash');
    
    return $currentHash !== $cachedHash;
}

/**
 * Configuración global de caché para desarrollo/producción
 */
function configureCacheEnvironment($environment = 'development') {
    if ($environment === 'production') {
        // En producción, usar caché agresivo
        ini_set('opcache.enable', 1);
        ini_set('opcache.memory_consumption', 128);
        ini_set('opcache.max_accelerated_files', 4000);
        ini_set('opcache.revalidate_freq', 60);
    } else {
        // En desarrollo, desactivar caché
        ini_set('opcache.enable', 0);
        setNoCacheHeaders();
    }
}

/**
 * Log de actividad de caché
 */
function logCacheActivity($message, $level = 'INFO') {
    $logFile = 'logs/cache.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
    
    // Crear directorio de logs si no existe
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

// Configurar el entorno de caché automáticamente
$environment = defined('APP_ENV') ? APP_ENV : 'development';
configureCacheEnvironment($environment);

// Log de inicialización
logCacheActivity('Cache control system initialized');
?>