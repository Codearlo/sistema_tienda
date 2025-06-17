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
    
    // ETag para control de cache más eficiente
    $etag = md5_file(__FILE__);
    header("ETag: " . $etag);
    
    // Verificar If-None-Match (ETag)
    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag) {
        header('HTTP/1.1 304 Not Modified');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime(__FILE__)) . ' GMT');
        header('ETag: ' . $etag);
        exit();
    }
    
    // Verificar If-Modified-Since
    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
        $ifModifiedSince = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
        $lastModified = filemtime(__FILE__);
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
 * Verificar si un archivo necesita ser regenerado
 */
function needsRegeneration($sourceFiles, $targetFile) {
    if (!file_exists($targetFile)) {
        return true;
    }
    
    $targetTime = filemtime($targetFile);
    
    foreach ($sourceFiles as $sourceFile) {
        if (file_exists($sourceFile) && filemtime($sourceFile) > $targetTime) {
            return true;
        }
    }
    
    return false;
}

/**
 * Optimiza imágenes para web
 */
function optimizeImage($sourcePath, $targetPath = null, $quality = 85) {
    if (!$targetPath) {
        $targetPath = $sourcePath;
    }
    
    $imageInfo = getimagesize($sourcePath);
    if (!$imageInfo) {
        return false;
    }
    
    $mimeType = $imageInfo['mime'];
    
    switch ($mimeType) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($sourcePath);
            imagejpeg($image, $targetPath, $quality);
            break;
        case 'image/png':
            $image = imagecreatefrompng($sourcePath);
            imagepng($image, $targetPath, round($quality / 10));
            break;
        case 'image/gif':
            $image = imagecreatefromgif($sourcePath);
            imagegif($image, $targetPath);
            break;
        default:
            return false;
    }
    
    if (isset($image)) {
        imagedestroy($image);
    }
    
    return true;
}

/**
 * Obtiene el tipo MIME de un archivo
 */
function getMimeType($filePath) {
    if (function_exists('finfo_file')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        return $mimeType;
    }
    
    return mime_content_type($filePath);
}

/**
 * Configuración automática basada en el entorno
 */
function setupCacheEnvironment() {
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
        // Producción: Cache agresivo
        header("Cache-Control: public, max-age=31536000"); // 1 año
        header("Expires: " . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
    } else {
        // Desarrollo: Sin cache
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Pragma: no-cache");
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
    }
}

// Ejecutar configuración automáticamente
setupCacheEnvironment();
?>