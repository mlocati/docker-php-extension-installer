<?php

$imageWidth = 8;
$imageHeight = 16;
$image = imagecreatetruecolor($imageWidth, $imageHeight);
$formats = array(
    'gd2',
    'gif',
    'jpeg',
    'png',
    'wbmp',
    'webp',
    'wbmp',
    'xbm',
    'xpm',
    'gd',
);
if (PHP_VERSION_ID >= 70200) {
    $formats = array_merge($formats, array(
        'bmp',
    ));
}
$tempFile = null;
$image2 = null;
try {
    foreach ($formats as $format) {
        $loadFuntion = "imagecreatefrom${format}";
        if (!function_exists($loadFuntion)) {
            throw new Exception("$loadFuntion() function is missing");
        }
        if ($format === 'xpm') {
            continue;
        }
        $saveFuntion = "image${format}";
        if (!function_exists($saveFuntion)) {
            throw new Exception("$saveFuntion() function is missing");
        }
        $tempFile = tempnam(sys_get_temp_dir(), 'dpei');
        if ($saveFuntion($image, $tempFile) === false) {
            throw new Exception("$saveFuntion() failed");
        }
        if (!is_file($tempFile) || filesize($tempFile) < 1) {
            throw new Exception("$saveFuntion() created an empty file");
        }
        $image2 = $loadFuntion($tempFile);
        unlink($tempFile);
        $tempFile = null;
        if (!is_resource($image2) || imagesx($image2) !== $imageWidth || imagesy($image2) !== $imageHeight) {
            throw new Exception("$loadFuntion() failed");
        }
        imagedestroy($image2);
    }
} finally {
    imagedestroy($image);
    if (is_resource($image2)) {
        imagedestroy($image2);
    }
    if($tempFile !== null) {
        unlink($tempFile);
    }
}

if (!function_exists('imagefttext')) {
    throw new Exception("imagefttext() function is missing");    
}
if (!function_exists('imageantialias')) {
    throw new Exception("imageantialias() function is missing");    
}
return true;
