<?php

$baseUrl = getenv('BASE_URL');
if (!$baseUrl) {
	if (PHP_SAPI !== 'cli' && !empty($_SERVER['HTTP_HOST'])) {
		$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
		$baseUrl = $scheme . '://' . $_SERVER['HTTP_HOST'];
	} else {
		$baseUrl = 'http://localhost/ashesi_market';
	}
}

$uploadDir = getenv('UPLOAD_DIR');
if (!$uploadDir) {
	$uploadDir = __DIR__ . '/../assets/uploads/';
}

$uploadUrl = getenv('UPLOAD_URL') ?: (rtrim($baseUrl, '/') . '/assets/uploads/');

define('APP_NAME',    'Ashesi Market');
define('BASE_URL',    rtrim($baseUrl, '/'));
define('UPLOAD_DIR',  rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR);
define('UPLOAD_URL',  rtrim($uploadUrl, '/') . '/');
define('MAX_IMAGES',  5);          // max product images per listing
define('IMG_MAX_MB',  3);          // max MB per image upload
define('SESSION_NAME', 'am_sess');
