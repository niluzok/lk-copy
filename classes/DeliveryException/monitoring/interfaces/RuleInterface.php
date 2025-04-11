<?php

declare(strict_types=1);

namespace app\classes\DeliveryException\monitoring\interfaces;

/**
 * Интерфейс для правила, которое содержит условия и действия, выполняемые при
 * выполнении условий
 */
interface RuleInterface
{
    /**
     * Проверяет, должно ли правило вообще рассматриваться
     *
     * @return bool Возвращает true, если правило включено
     */
    public function isEnabled(): bool;

    /**
     * Проверяет, должно ли правило сработать, выполяются ли все условия
     *
     * @return bool Возвращает true, если правило должно сработать
     */
    public function shouldTrigger(): bool;

    /**
     * Выполняет правило, если условия выполнены
     *
     * @return void
     */
    public function evaluate(): void;
}
