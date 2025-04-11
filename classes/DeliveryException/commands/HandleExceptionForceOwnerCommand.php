<?php

declare(strict_types=1);

namespace app\classes\DeliveryException\commands;

use app\repository\DeliveryExceptionRepository;
use app\repository\CommentRepository;
use app\classes\DeliveryException\enums\ExceptionOwnerEnum;

/**
 * Команда обработки сообщений проблемной доставки с принудительным проставлением
 * владельца проблемной доставки
 */
class HandleExceptionForceOwnerCommand extends HandleExceptionCommand
{
    /**
     * Конструктор класса
     *
     * @param ExceptionOwnerEnum $exceptionOwner Владелец проблемной доставки
     * @param int|null $userId ID пользователя запускающего команду
     * @param DeliveryExceptionRepository $deliveryExceptionRepository Репозиторий для управления сообщениями проблемной доставки
     * @param CommentRepository $commentRepository Репозиторий для управления комментариями
     */
    public function __construct(
        ExceptionOwnerEnum $exceptionOwner,
        ?int $userId,
        DeliveryExceptionRepository $deliveryExceptionRepository,
        CommentRepository $commentRepository
    ) {
        parent::__construct($userId, $deliveryExceptionRepository, $commentRepository);
        $this->exceptionOwner = $exceptionOwner;
    }

    /**
     * Принудительно не меняет владельца проблемной доставки
     *
     * @param ExceptionOwnerEnum $exceptionOwner
     * @return $this
     */
    public function setExceptionOwner(ExceptionOwnerEnum $exceptionOwner): self
    {
        return $this;
    }
}
