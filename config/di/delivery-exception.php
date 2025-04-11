<?php

declare(strict_types=1);

use app\classes\DeliveryException\commands\HandleExceptionCommand;
use app\classes\DeliveryException\commands\HandleExceptionWithOwnerCommandInterface;

return [
    // Команда по умолчанию. Автоматически подсовывается в аргументы конструкторов
    // при использовании Yii::createObject()
    HandleExceptionWithOwnerCommandInterface::class => HandleExceptionCommand::class,
];
