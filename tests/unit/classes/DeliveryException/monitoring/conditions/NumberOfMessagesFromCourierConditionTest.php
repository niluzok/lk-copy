<?php

declare(strict_types=1);

namespace tests\unit\classes\DeliveryException\monitoring\conditions;

use app\classes\DeliveryException\monitoring\conditions\NumberOfMessagesFromCourierCondition;
use app\models\Delivery;
use app\repository\CommentRepository;
use Base\Unit;
use Codeception\Specify;

/**
 * Тесты для класса NumberOfMessagesFromCourierCondition
 */
class NumberOfMessagesFromCourierConditionTest extends Unit
{
    use Specify;

    /**
     * @var Delivery Экземпляр Delivery для тестов
     */
    protected Delivery $delivery;

    protected function _before()
    {
        // Создаем экземпляр Delivery
        $this->delivery = new Delivery();
        $this->delivery->order_id = 1;
    }

    /**
     * Тест метода check, когда количество сообщений равно заданному
     */
    public function testMethodCheckWhenMessagesEqual(): void
    {
        $this->specify('Когда количество сообщений от курьера равно заданному, check должен вернуть true', function () {
            $commentRepository = $this->make(CommentRepository::class, [
                'count' => function (array $config) {
                    return 3; // Возвращаем 3 сообщения
                }
            ]);

            // Создаем экземпляр условия с порогом в 3 сообщения
            $condition = new NumberOfMessagesFromCourierCondition($this->delivery, 3, $commentRepository);

            // Проверяем, что check возвращает true тк 3 == 3
            expect('check возвращает true', $condition->check())->true();
        });
    }

    /**
     * Тест метода check, когда количество сообщений не равно заданному
     */
    public function testMethodCheckWhenMessagesNotEqual(): void
    {
        $this->specify('Когда количество сообщений от курьера не равно заданному, check должен вернуть false', function () {
            // Мокаем CommentRepository для проверки неравного количества сообщений
            $commentRepository = $this->make(CommentRepository::class, [
                'count' => function (array $config) {
                    return 5; // Возвращаем 5 сообщений
                }
            ]);

            // Создаем экземпляр условия с порогом в 3 сообщения
            $condition = new NumberOfMessagesFromCourierCondition($this->delivery, 3, $commentRepository);

            // Проверяем, что check возвращает false тк 5 != 3
            expect('check возвращает false', $condition->check())->false();
        });
    }
}
