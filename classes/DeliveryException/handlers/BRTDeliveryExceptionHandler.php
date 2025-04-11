<?php

declare(strict_types=1);

namespace app\classes\DeliveryException\handlers;

use app\models\Courier;
use DateTimeImmutable;
use app\classes\DeliveryException\enums\ExceptionOwnerEnum;
use app\enums\courier\CourierServiceMessageTypeEnum;
use app\classes\DeliveryException\commands\BRTSetIsTransferCommand;
use Yii;

/**
 * Обработчик сообщений проблемной доставки для BRT
 */
class BRTDeliveryExceptionHandler extends AbstractDeliveryExceptionHandler
{
    // Последняя дата вида 00.00.0000 в многострочной строке
    const DATE_REGEX = '/.*(\d{2}\.\d{2}\.\d{4})\s*/s';

    public function getCourierId(): int
    {
        return Courier::ID_BRT;
    }

    protected function handleNonExistentExceptionProblem(): void
    {
        $this->command
            ->setDelivery($this->delivery)
            ->setMessage($this->getMessage())
            ->setExceptionOwner(ExceptionOwnerEnum::Operator)
            ->run();
    }

    protected function handleNonExistentExceptionNoProblem(): void
    {
        $this->command
            ->setDelivery($this->delivery)
            ->setMessage($this->getMessage())
            ->setExceptionOwner(ExceptionOwnerEnum::Logist)
            ->run();
    }

    protected function handleExistingExceptionProblem(): void
    {
        $this->command
            ->setDelivery($this->delivery)
            ->setMessage($this->getMessage())
            ->setExceptionOwner(ExceptionOwnerEnum::Operator)
            ->run();
    }

    protected function handleExistingExceptionNoProblem(): void
    {
        $this->command
            ->setDelivery($this->delivery)
            ->setMessage($this->getMessage());

        // Владельца не меняем согласно регламенту

        $this->command->run();

        /** @see [признак переноса](https://itsalespro.monday.com/boards/7458243906/pulses/7458395690) */
        
        /** @var BRTSetIsTransferCommand */
        $resetIsTransferCommand = Yii::createObject(BRTSetIsTransferCommand::class);
        $resetIsTransferCommand
            ->setDelivery($this->delivery)
            ->setIsTransfer(false)
            ->run()
        ;
    }

    protected function handleUnknownMessage(): void
    {
        $this->command
            ->setDelivery($this->delivery)
            ->setMessage($this->getMessage())
            ->setExceptionOwner(ExceptionOwnerEnum::Logist)
            ->run();
    }

    protected function prepare(): void
    {
        if ($this->shouldUpdateDeliveredDateFromException($this->getMessage())) {
            $dateString = $this->extractLastDateInComment($this->getMessage());

            if ($dateString) {
                $this->command->setDeliveredDate(new DateTimeImmutable($dateString));
            }
        }
    }

    /**
     * Возвращает надо ли проставить дату из сообщения как дату доставки для проблемного заказа
     *
     * @see https://leadgidwebvork.monday.com/boards/3354701439/pulses/6828250014
     *
     * @param   string  $message  Сообщение от КС
     *
     * @return  bool
     */
    protected function shouldUpdateDeliveredDateFromException(?string $message): bool
    {
        if (!$message) {
            return false;
        }

        /** @see https://leadgidwebvork.monday.com/boards/3354701471/pulses/7368122304 */
        if ($this->delivery->deliveryException && (ExceptionOwnerEnum::from($this->delivery->deliveryException->owner) == ExceptionOwnerEnum::Operator)) {
            return false;
        }

        return $this->checkMessageExist($this->getMessage(), CourierServiceMessageTypeEnum::SetDeliveryDate);
    }

    /**
     * Извлекает дату из последней строки текста
     *
     * @param string $comment Входной текст
     * @return string|null Извлеченная дата или null, если дата не найдена
     */
    protected function extractLastDateInComment(?string $comment): ?string
    {
        if (!$comment) {
            return null;
        }
        
        if (preg_match(self::DATE_REGEX, $comment, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
