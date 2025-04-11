<?php

declare(strict_types=1);

namespace tests\unit\classes\DeliveryException\monitoring\conditions;

use app\classes\DeliveryException\monitoring\conditions\WorkingDaysFromDateCondition;
use Base\Unit;
use Codeception\Specify;
use DateTime;

/**
 * Тесты для класса WorkingDaysFromDateCondition
 */
class WorkingDaysFromDateConditionTest extends Unit
{
    use Specify;

    /**
     * Тест метода check, когда прошло достаточное количество рабочих дней
     */
    public function testMethodCheckWhenSufficientWorkingDaysHavePassed(): void
    {
        $this->specify('Когда прошло достаточное количество рабочих дней, check должен вернуть true', function () {
            // Устанавливаем дату 5 рабочих дней назад
            $date = (new DateTime('now'))->modify('-7 days')->format('Y-m-d');

            // Создаем экземпляр условия с порогом в 5 рабочих дней
            $condition = new WorkingDaysFromDateCondition(date: $date, days: 5);

            // Проверяем, что check возвращает true
            expect('check возвращает true', $condition->check())->true();
        });
    }

    /**
     * Тест метода check, когда прошло недостаточное количество рабочих дней
     */
    public function testMethodCheckWhenNotEnoughWorkingDaysHavePassed(): void
    {
        $this->specify('Когда прошло недостаточное количество рабочих дней, check должен вернуть false', function () {
            // Устанавливаем дату 2 рабочих дня назад
            $date = (new DateTime('now'))->modify('-3 days')->format('Y-m-d');

            // Создаем экземпляр условия с порогом в 5 рабочих дней
            $condition = new WorkingDaysFromDateCondition(date: $date, days: 5);

            // Проверяем, что check возвращает false
            expect('check возвращает false', $condition->check())->false();
        });
    }

    /**
     * Тест метода check с объектом DateTimeInterface
     */
    public function testMethodCheckWithDateTimeInterface(): void
    {
        $this->specify('Когда используется объект DateTimeInterface и прошло достаточно рабочих дней, check должен вернуть true', function () {
            $date = new DateTime('now');
            $date->modify('-7 days');

            $condition = new WorkingDaysFromDateCondition(date: $date, days: 5);

            expect('check возвращает true', $condition->check())->true();
        });
    }
}
