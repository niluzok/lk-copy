<?php

declare(strict_types=1);

namespace app\classes\DeliveryException\monitoring\rules;

use app\classes\DeliveryException\monitoring\conditions\CSLastMessageCondition;
use app\classes\DeliveryException\monitoring\conditions\WorkingDaysFromDateCondition;
use app\classes\DeliveryException\monitoring\conditions\DeliveryExceptionExistsCondition;
use app\classes\DeliveryException\commands\HandleExceptionCommand;
use app\classes\DeliveryException\enums\ExceptionOwnerEnum;
use app\classes\DeliveryException\monitoring\rules\GenericRule;
use app\models\Delivery;
use app\repository\CommentRepository;
use app\repository\DeliveryExceptionRepository;
use DateTimeInterface;
use InvalidArgumentException;
use Yii;

/**
 * Правило для обработки ситуации с сообщением от КС и количеством рабочих дней
 */
class CSMessageStuckRule extends GenericRule
{
    private const SYSTEM_USER_ID = 1;

    public function __construct(
        Delivery $delivery,
        string $message,
        DateTimeInterface|string|callable $dateFrom,
        int $days,
        DeliveryExceptionRepository $deliveryExceptionRepository,
        CommentRepository $commentRepository
    ) {
        
        $this->addEnableConditions([
            new DeliveryExceptionExistsCondition($delivery, true),
            new CSLastMessageCondition($delivery, $message),
        ]);
        
        $this->addTriggerConditions([
            new WorkingDaysFromDateCondition($dateFrom, $days),
        ]);

        $this->addActions([
            (new HandleExceptionCommand(
                self::SYSTEM_USER_ID,
                $deliveryExceptionRepository,
                $commentRepository
            ))
            ->setDelivery($delivery)
            ->setMessage(Yii::t('app', 'Cтатус не обновлен'))
        ]);
    }
}
