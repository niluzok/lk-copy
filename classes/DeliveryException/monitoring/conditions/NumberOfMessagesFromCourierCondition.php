<?php

declare(strict_types=1);

namespace app\classes\DeliveryException\monitoring\conditions;

use app\classes\DeliveryException\monitoring\interfaces\ConditionInterface;
use app\models\Delivery;
use app\repository\CommentRepository;
use app\models\Comment;

/**
 * Класс для проверки количества сообщений от курьерской службы
 * Оно должно быть равно заданному
 */
class NumberOfMessagesFromCourierCondition implements ConditionInterface
{
    /**
     * @var Delivery Экземпляр модели Delivery
     */
    private Delivery $delivery;

    /**
     * @var int Количество сообщений от курьерской службы
     */
    private int $messagesNumber;

    private CommentRepository $commentRepository;

    /**
     * Конструктор устанавливает объект Delivery и нужное количество сообщений
     *
     * @param Delivery $delivery Объект доставки
     * @param int $messagesNumber Количество сообщений от курьерской службы
     */
    public function __construct(Delivery $delivery, int $messagesNumber, CommentRepository $commentRepository)
    {
        $this->delivery = $delivery;
        $this->messagesNumber = $messagesNumber;
        $this->commentRepository = $commentRepository;
    }

    /**
     * Проверяет, что количество сообщений от курьера
     *
     * @return bool Возвращает true, если количество сообщений равно заданному
     */
    public function check(): bool
    {
        return $this->countMessages() === $this->messagesNumber;
    }

    protected function countMessages()
    {
        return $this->commentRepository->count([
            'key' => Comment::KEY_DELIVERY_EXCEPTION,
            'field_id' => $this->delivery->order_id,
        ]);
    }
}
