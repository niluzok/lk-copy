<?php

namespace app\classes\DeliveryException;

use app\models\Log;
use yii\log\Logger;

class DeliveryExceptionLogger
{
    public static function log(string $prefix, string $message, int $logLevel = Logger::LEVEL_INFO): void
    {
        $log = new Log();
        $log->level = $logLevel;
        $log->category = 'delivery.exception.command';
        $log->log_time = time();
        $log->prefix = $prefix;
        $log->message = $message;
        $log->save();
    }
}
