<?php

declare(strict_types=1);

namespace app\enums\courier;

use app\enums\contract\HasLabelInterface;
use Yii;

/**
 * Енум - тип сообщения от КС
 */
enum CourierServiceMessageTypeEnum: string implements HasLabelInterface
{
    case Problem = 'problem';      // Проблемное
    case NoProblem = 'no_problem'; // Непроблемное
    case Ignore = 'ignore';        // Игнорировать
    case SetDeliveryDate = 'set_delivery_date'; // Проставить дату доставки из сообщения
    case Unknown = 'unknown';      // Неизвестное

    public function getLabel(): string
    {
        return match ($this) {
            self::Problem => Yii::t('courier-service-message-type', 'problem'),
            self::NoProblem => Yii::t('courier-service-message-type', 'no_problem'),
            self::Ignore => Yii::t('courier-service-message-type', 'ignore'),
            self::SetDeliveryDate => Yii::t('courier-service-message-type', 'set_delivery_date'),
            self::Unknown => Yii::t('courier-service-message-type', 'unknown'),
        };
    }
}
