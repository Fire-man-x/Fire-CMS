<?php
declare(strict_types=1);

use Nette\Bootstrap\Configurator;

require __DIR__ . '/../vendor/autoload.php';

require __DIR__ . '/config/constants.php';

define("APP_DIR", __DIR__."/");

$configurator = new Configurator();

$configurator->setDebugMode(['FireCmsCore951@::1','FireCmsCore951@127.0.0.1']);
$configurator->enableTracy(__DIR__ . '/../log');

$configurator->setTimeZone('Europe/Prague');
$configurator->setTempDirectory(__DIR__ . '/../temp');

$robotLoader = $configurator->createRobotLoader();
$robotLoader->addDirectory(__DIR__);
if(file_exists(APP_DIR.'../temp/cache/App.Configurator')){
	$robotLoader->addDirectory(APP_DIR.'../temp/cache/App.Configurator');
}
$robotLoader->register();

$configurator->addConfig(__DIR__ . '/config/config.neon');

$themeFolder = __DIR__.'/../theme/config';
$pluginsNeon = $themeFolder . '/plugins.neon';
$themeNeon = $themeFolder . '/theme.neon';
$localNeon = $themeFolder . '/config.local.neon';
if (is_file($pluginsNeon)) {
	$configurator->addConfig($pluginsNeon);
}

if (is_file($themeNeon)) {
	$configurator->addConfig($themeNeon);
}
if (is_file($localNeon)) {
	$configurator->addConfig($localNeon);
}

/*$files = \Nette\Utils\Finder::findFiles("config.plugin.neon")->from(__DIR__);
foreach ($files as $filePath => $file) {
	$configurator->addConfig($filePath);
}*/

$configurator->addStaticParameters(array('wwwDir' => __DIR__.'/../www'));

$container = $configurator->createContainer();

return $container;
