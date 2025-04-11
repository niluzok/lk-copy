<?php

declare(strict_types=1);

namespace app\classes\DeliveryException\monitoring\conditions;

use app\classes\DeliveryException\monitoring\interfaces\ConditionInterface;
use app\models\Delivery;

/**
 * Класс для проверки текста последнего сообщения от КС
 * Проверяет, содержит ли текст последнего сообщения заданное сообщение.
 * По умолчанию проверка нечувствительна к регистру, но это можно настроить.
 */
class CSLastMessageCondition implements ConditionInterface
{
    private Delivery $delivery;
    private string $message;
    private bool $caseSensitive = false; // По умолчанию проверка нечувствительна к регистру

    /**
     * Конструктор принимает объект доставки, сообщение, которое должно
     * содержаться в последнем сообщении, и параметр чувствительности к регистру
     *
     * @param Delivery $delivery Объект доставки
     * @param string $message Сообщение, которое должно содержаться в сообщении от КС
     * @param bool $caseSensitive Определяет, должна ли проверка учитывать регистр (по умолчанию - false)
     */
    public function __construct(Delivery $delivery, string $message, ?bool $caseSensitive = null)
    {
        $this->delivery = $delivery;
        $this->message = $message;
        
        if ($caseSensitive !== null) {
            $this->caseSensitive = $caseSensitive;
        }
    }

    /**
     * Проверяет, что текст последнего сообщения от КС содержит заданное сообщение
     *
     * @return bool Возвращает true, если текст последнего сообщения КС содержит сообщение
     */
    public function check(): bool
    {
        $lastComment = $this->delivery->deliveryException?->lastComment;

        if ($lastComment === null) {
            return false;
        }

        if ($this->caseSensitive) {
            return strpos($lastComment->content, $this->message) !== false;
        }

        return stripos($lastComment->content, $this->message) !== false;
    }
}
