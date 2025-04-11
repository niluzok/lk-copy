<?php

declare(strict_types=1);

namespace app\classes\DeliveryException\monitoring\rules;

use app\classes\DeliveryException\monitoring\conditions\DeliveryExceptionExistsCondition;
use app\classes\DeliveryException\monitoring\conditions\DeliveryExceptionOwnerCondition;
use app\classes\DeliveryException\monitoring\conditions\BRTDeliveryDateChangeCountCondition;
use app\classes\DeliveryException\commands\HandleExceptionCommand;
use app\classes\DeliveryException\enums\ExceptionOwnerEnum;
use app\models\Delivery;
use app\repository\CommentRepository;
use app\repository\CSMessageRepository;
use app\repository\DeliveryExceptionRepository;
use Yii;

/**
 * Правило, срабатывающее, когда доставка переназначена больше раз, чем задано в конфигурации.
 */
class BRTDeliveryRescheduleCountRule extends GenericRule
{
    private const SYSTEM_USER_ID = 1;

    public function __construct(
        Delivery $delivery,
        int $reschedulesCount,
        DeliveryExceptionRepository $deliveryExceptionRepository,
        CommentRepository $commentRepository,
        CSMessageRepository $csMessageRepository
    ) {
        $this->addEnableConditions([
            new DeliveryExceptionExistsCondition($delivery, shouldExist: true),
            new DeliveryExceptionOwnerCondition($delivery, owner: ExceptionOwnerEnum::Logist),
        ]);

        $this->addTriggerConditions([
            new BRTDeliveryDateChangeCountCondition($delivery, uniqueDatesCount: $reschedulesCount, commentRepository: $commentRepository, csMessageRepository: $csMessageRepository),
        ]);

        $this->addActions([
            (new HandleExceptionCommand(
                self::SYSTEM_USER_ID,
                $deliveryExceptionRepository,
                $commentRepository,
            ))
            ->setDelivery($delivery)
            ->setMessage(Yii::t('app', 'Заказ не двигается {days} дня', ['days' => $reschedulesCount]))
        ]);
    }
}
