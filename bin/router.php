<?php
// Alleen voor lokale ontwikkeling: dezelfde afscherming als Apache .htaccess.
$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
if(str_starts_with($path,'/public/assets/') && is_file(dirname(__DIR__).$path))return false;
$_SERVER['SCRIPT_NAME']='/index.php';
if(preg_match('#^/(?:app|database|storage|vendor|tests|bin|plugin)(?:/|$)#',$path)){http_response_code(403);exit;}
require dirname(__DIR__).'/index.php';
