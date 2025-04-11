<?php 

declare(strict_types=1); 

// В этот файл ничего не добавлять – дописывать в файлы, 
// соответствующие окружению (*_web, *_console, *_tests)

defined('APP_ROOT') or define('APP_ROOT', '/app');

// 0. Настройка php.ini директив для окружение
require(APP_ROOT . '/config/php_ini_set_tests.php');

// 1. Константы для текущего окружения
require(APP_ROOT . '/config/const_env_common.php');
require(APP_ROOT . '/config/const_yii_tests.php');
require(APP_ROOT . '/config/const_request_tests.php');

// 2. Загрузка системных файлов
require(APP_ROOT . '/vendor/autoload.php');
require(APP_ROOT . '/vendor/yiisoft/yii2/Yii.php');
require(APP_ROOT . '/config/bootstrap.php');

// Алиасы для тестового окружения
Yii::setAlias('@tests', '@app/tests');

// 3. Конфиг приложения - это бутстрап файл – тут нет запуска приложенния
// 4. Запуск приложения