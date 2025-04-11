<?php

declare(strict_types=1);

namespace app\classes\DeliveryException\monitoring;

use app\repository\DeliveryExceptionRepository;
use app\repository\CommentRepository;
use app\models\Delivery;
use app\classes\DeliveryException\monitoring\RuleManager;
use app\classes\DeliveryException\monitoring\rules\NoMessagesFromCSRule;
use app\classes\DeliveryException\monitoring\rules\BRTDeliveryRescheduleCountRule;
use app\classes\DeliveryException\monitoring\rules\CSMessageStuckRule;
use app\models\Courier;
use app\repository\CSMessageRepository;

/**
 * Сервис для мониторинга проблемных заказов
 *
 * Использует набор правил для проверки состояния доставки и выполнения
 * необходимых действий на основе заданных условий
 */
class DeliveryExceptionMonitoringService
{
    /**
     * @param RuleManager $ruleManager Менеджер правил для мониторинга доставок
     * @param DeliveryExceptionRepository $deliveryExceptionRepository Репозиторий исключений доставки
     * @param CommentRepository $commentRepository Репозиторий комментариев
     * @param CSMessageRepository $csMessageRepository Репозиторий сообщений КС
     */
    public function __construct(
        private RuleManager $ruleManager,
        private DeliveryExceptionRepository $deliveryExceptionRepository,
        private CommentRepository $commentRepository,
        private CSMessageRepository $csMessageRepository,
    ) {
    }

    /**
     * Возвращает массив зарегистрированных ID курьерских служб
     *
     * @return array Массив зарегистрированных ID курьерских служб
     */
    public function registeredCS(): array
    {
        return [
            Courier::ID_BRT,
            Courier::ID_SDA,
        ];
    }

    /**
     * Обрабатывает доставку, добавляя соответствующие правила в зависимости от курьерской службы
     *
     * @param Delivery $delivery Объект доставки
     * @return void
     */
    public function process(Delivery $delivery): void
    {
        // Получаем правила для курьерской службы, основываясь на доставке
        $rules = $this->rulesByCS($delivery);

        // Получаем ID курьерской службы
        $courierId = $delivery->courier_id;

        // Проверяем, есть ли правила для курьерской службы
        if (isset($rules[$courierId])) {
            // Добавляем правила в RuleManager и выполняем их
            $this->ruleManager->addRules($rules[$courierId]);
            $this->ruleManager->evaluateAll();
        }
    }

    /**
     * Возвращает набор правил для каждой курьерской службы
     *
     * @param Delivery $delivery Объект доставки
     * @return array Массив правил, где ключ - это ID курьерской службы, а значение - массив правил
     */
    private function rulesByCS(Delivery $delivery): array
    {
        return [
            Courier::ID_BRT => [
                new CSMessageStuckRule(
                    $delivery,
                    message: 'In consegna',
                    dateFrom: function () use ($delivery) {
                        return $delivery->deliveryException->lastComment->created_ts;
                    },
                    days: 1,
                    deliveryExceptionRepository: $this->deliveryExceptionRepository,
                    commentRepository: $this->commentRepository
                ),
                new BRTDeliveryRescheduleCountRule(
                    $delivery,
                    reschedulesCount: 3,
                    deliveryExceptionRepository: $this->deliveryExceptionRepository,
                    commentRepository: $this->commentRepository,
                    csMessageRepository: $this->csMessageRepository,
                ),
                new NoMessagesFromCSRule(
                    $delivery,
                    days: 3,
                    deliveryExceptionRepository: $this->deliveryExceptionRepository,
                    commentRepository: $this->commentRepository
                ),
            ],
            Courier::ID_SDA => [
                new NoMessagesFromCSRule(
                    $delivery,
                    days: 3,
                    deliveryExceptionRepository: $this->deliveryExceptionRepository,
                    commentRepository: $this->commentRepository
                ),
            ],
        ];
    }
}
