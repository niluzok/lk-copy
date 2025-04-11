<?php

declare(strict_types=1);

namespace app\classes\DeliveryException\monitoring\rules;

use app\classes\DeliveryException\monitoring\conditions\DeliveryExceptionExistsCondition;
use app\classes\DeliveryException\monitoring\conditions\WorkingDaysFromDateCondition;
use app\classes\DeliveryException\commands\HandleExceptionCommand;
use app\classes\DeliveryException\monitoring\interfaces\RuleInterface;
use app\models\Delivery;
use app\repository\CommentRepository;
use app\repository\DeliveryExceptionRepository;
use RuntimeException;
use Yii;

/**
 * Правило, срабатывающее, если прошло установленное количество дней с момента отправки на склад и нет сообщений от КС
 */
class NoMessagesFromCSRule implements RuleInterface
{
    public const SYSTEM_USER_ID = 1;

    private GenericRule $rule;

    public function __construct(
        Delivery $delivery,
        int $days,
        DeliveryExceptionRepository $deliveryExceptionRepository,
        CommentRepository $commentRepository
    ) {
        if (!$delivery->in_stock_ts) {
            throw new RuntimeException('Delivery has empty in_stock_ts');
        }

        $this->rule = new GenericRule();

        $this->rule->addEnableCondition(new DeliveryExceptionExistsCondition($delivery, shouldExist: false));

        $this->rule->addTriggerCondition(new WorkingDaysFromDateCondition($delivery->in_stock_ts, $days));

        // Action
        $this->rule->addAction(
            (new HandleExceptionCommand(
                self::SYSTEM_USER_ID,
                $deliveryExceptionRepository,
                $commentRepository
            ))
            ->setDelivery($delivery)
            ->setMessage(Yii::t('app', 'Заказ завис в транзите'))
        );
    }

    /**
     * Выполняет правило, если оно включено и условия срабатывания выполнены
     */
    public function evaluate(): void
    {
        $this->rule->evaluate();
    }

    /**
     * Проверяет, включено ли правило
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->rule->isEnabled();
    }

    /**
     * Проверяет, должно ли правило сработать
     *
     * @return bool
     */
    public function shouldTrigger(): bool
    {
        return $this->rule->shouldTrigger();
    }
}
