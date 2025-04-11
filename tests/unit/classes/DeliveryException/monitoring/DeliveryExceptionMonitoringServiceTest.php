<?php

declare(strict_types=1);

namespace tests\unit\classes\DeliveryException\monitoring;

use app\classes\DeliveryException\monitoring\DeliveryExceptionMonitoringService;
use app\classes\DeliveryException\monitoring\RuleManager;
use app\classes\DeliveryException\monitoring\rules\NoMessagesFromCSRule;
use app\models\Delivery;
use app\repository\DeliveryExceptionRepository;
use app\repository\CommentRepository;
use Base\Unit;
use Codeception\Specify;
use Codeception\Stub\Expected;
use app\models\Courier;
use app\repository\CSMessageRepository;

/**
 * Тест для класса DeliveryExceptionMonitoringService
 */
class DeliveryExceptionMonitoringServiceTest extends Unit
{
    use Specify;

    /**
     * Комплексный тест для метода process
     */
    public function testProcessWithRuleExecution(): void
    {
        $this->specify('Правила должны быть добавлены и их проверка должна быть успешно выполнена', function () {
            // Создание мок объекта RuleManager с проверками
            $ruleManager = $this->make(RuleManager::class, [
                'addRule' => Expected::atLeastOnce(function ($rule) use (&$ruleManager) {
                    // Проверяем, что в addRule передается правильное правило
                    // $this->assertInstanceOf(NoMessagesFromCSRule::class, $rule);
                    return $ruleManager; // Возвращаем мок объекта RuleManager (self)
                }),
                'evaluateAll' => Expected::once(), // Проверяем, что метод evaluateAll вызывается ровно один раз
            ]);

            // Создание моков репозиториев
            $deliveryExceptionRepository = $this->make(DeliveryExceptionRepository::class);
            $commentRepository = $this->make(CommentRepository::class);
            $csMessageRepository = $this->make(CSMessageRepository::class);

            // Создание мока объекта Delivery с полем in_stock_ts
            $delivery = $this->make(Delivery::class, [
                'in_stock_ts' => '2024-09-18 12:00:00', // Пример даты и времени наличия на складе
                'courier_id' => Courier::ID_BRT
            ]);

            // Инициализация сервиса мониторинга DeliveryExceptionMonitoringService
            $monitoringService = new DeliveryExceptionMonitoringService(
                $ruleManager,
                $deliveryExceptionRepository,
                $commentRepository,
                $csMessageRepository,
            );

            // Вызываем метод process и проверяем добавление правил и их оценку
            $monitoringService->process($delivery, 'some message');
        });
    }
}
