<?php

declare(strict_types=1);

namespace app\classes\DeliveryException\monitoring\conditions;

use app\classes\DeliveryException\monitoring\interfaces\ConditionInterface;
use DateTime;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Класс для проверки, прошли ли [[days]] или более рабочих дней с указанной даты
 */
class WorkingDaysFromDateCondition implements ConditionInterface
{
    /**
     * @var DateTimeInterface|string|callable Дата для проверки
     */
    private $date;

    /**
     * @var int Количество рабочих дней
     */
    private int $days;

    /**
     * Конструктор принимает объект DateTimeInterface или строку с датой и количество рабочих дней
     *
     * @param DateTimeInterface|string $date Дата, с которой начинается отсчет
     * @param int $days Количество рабочих дней для проверки
     */
    public function __construct(DateTimeInterface|string|callable $date, int $days)
    {
        $this->date = $date;
        $this->days = $days;
    }

    /**
     * Проверяет, прошло ли [[days]] или более рабочих дней с даты
     *
     * @return bool Возвращает true, если прошло [[days]] или более рабочих дней
     */
    public function check(): bool
    {
        $this->ensureDateIsDateTime();

        $currentDate = new DateTime('now');
        $dateWithWorkingDays = $this->addWorkingDays($this->date, $this->days);

        return $currentDate >= $dateWithWorkingDays;
    }

    protected function ensureDateIsDateTime()
    {
        if (is_callable($this->date)) {
            $this->date = call_user_func($this->date);

            // Проверяем, что возвращаемое значение является строкой или объектом DateTimeInterface
            if (!($this->date instanceof DateTimeInterface) && !is_string($this->date)) {
                throw new InvalidArgumentException('Callable must return a DateTimeInterface or string');
            }
        }
        
        $this->date = is_string($this->date) ? new DateTime($this->date) : $this->date;
    }

    /**
     * Добавляет указанное количество рабочих дней к дате
     *
     * @param DateTimeInterface $date Исходная дата
     * @param int $daysToAdd Количество рабочих дней для добавления
     * @return DateTimeInterface Дата с добавленными рабочими днями
     */
    protected function addWorkingDays(DateTimeInterface $date, int $daysToAdd): DateTimeInterface
    {
        $date = new DateTime($date->format('Y-m-d'));
        
        while ($daysToAdd > 0) {
            $date->modify('+1 day');

            // Если это не выходной, уменьшаем счетчик рабочих дней
            if ($date->format('N') < 6) {
                $daysToAdd--;
            }
        }

        return $date;
    }
}
