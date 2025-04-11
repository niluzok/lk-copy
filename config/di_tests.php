<?php

/**
 * Файл для настройки DI контейнера для окружения tests
 */

declare(strict_types=1);

use app\components\Rabbit;
use Mocks\RabbitMock;

// У расширения yii2-rabbitmq в композере настроен автоматичей бутстраппинг
// Что бы в тесты его не грузить, переназначаем на пустышку
Yii::$container->set('mikemadisonweb\rabbitmq\DependencyInjection', fn() => new stdClass());
Yii::$container->set(Rabbit::class, fn() => new RabbitMock());
