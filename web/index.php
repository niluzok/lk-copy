<?php

use yii\web\Application;
use yii\web\ForbiddenHttpException;
use yii\web\BadRequestHttpException;
use app\classes\Exception\HttpExceptionWarningFinisher;

// В этот файл ничего не добавлять – дописывать в файлы,
// соответствующие окружению (*_web, *_console, *_tests)

defined('APP_ROOT') or define('APP_ROOT', '/app');

// 0. Настройка php.ini директив для окружение
require(APP_ROOT . '/config/php_ini_set_web.php');

// 1. Константы для текущего окружения
require(APP_ROOT . '/config/const_env_common.php');
require(APP_ROOT . '/config/const_yii_web.php');
require(APP_ROOT . '/config/const_request_web.php');

// 2. Загрузка системных файлов
require(APP_ROOT . '/vendor/autoload.php');
require(APP_ROOT . '/vendor/yiisoft/yii2/Yii.php');
require(APP_ROOT . '/config/bootstrap.php');

// 3. Конфиг приложения
if (IS_API_CALL) {
    $config = require(APP_ROOT . '/config/api.php');
} else {
    $config = require(APP_ROOT . '/config/web.php');
}

// 4. Запуск приложения
try {
    (new Application($config))->run();
} catch (ForbiddenHttpException | BadRequestHttpException $e) {
    (new HttpExceptionWarningFinisher($e))->behave();
}
