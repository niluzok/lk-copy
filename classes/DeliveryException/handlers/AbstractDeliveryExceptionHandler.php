<?php

declare(strict_types=1);

namespace app\classes\DeliveryException\handlers;

use app\models\Delivery;
use app\classes\DeliveryException\handlers\DeliveryExceptionHandlerInterface;
use app\classes\DeliveryException\commands\HandleExceptionWithOwnerCommandInterface;
use app\enums\courier\CourierServiceMessageTypeEnum;
use app\repository\cached\CSMessageRepository;
use Yii;
use app\models\CourierServiceMessage;

/**
 * Абстрактный обработчик проблемной доставки, реализующий интерфейс DeliveryExceptionHandlerInterface
 *
 * Содержит основные методы по текущему представлению логики работы с проблемными:
 *
 * 1. Инкапсулирует класс-команду для применения нужных изменений в БД
 * 2. Содержит ид КС для которой этот хендлер должен работать
 * 3. Сообщения, которые нужно игнорировать
 * 4. Непроблемные сообщения (проблемные это все остальные)
 * 5. Методы для обработки сообщения для кейсов проблемных+непроблемных сообщений
 *    для существующих и несуществующих проблемных заказов
 *
 */
abstract class AbstractDeliveryExceptionHandler implements DeliveryExceptionHandlerInterface
{
    /**
     * @var HandleExceptionWithOwnerCommandInterface Экземпляр команды обработки исключений
     */
    protected HandleExceptionWithOwnerCommandInterface $command;

    /**
     * @var Delivery Модель доставки
     */
    protected Delivery $delivery;

    /**
     * @var string Сообщение КС
     */
    protected string $message;

    /**
     * Конструктор
     *
     * @param HandleExceptionWithOwnerCommandInterface $handleExceptionCommand
     */
    public function __construct(HandleExceptionWithOwnerCommandInterface $handleExceptionCommand)
    {
        $this->command = $handleExceptionCommand;
    }

    /**
     * Возвращает идентификатор курьерской службы, для которой предназначен этот обработчик исключений
     *
     * @return int
     */
    abstract public function getCourierId(): int;

    /**
     * Обрабатывает новую проблемную доставку
     *
     * @return void
     */
    abstract protected function handleNonExistentExceptionProblem(): void;

    /**
     * Обрабатывает новую проблемную доставку с непроблемным сообщением
     *
     * @return void
     */
    abstract protected function handleNonExistentExceptionNoProblem(): void;

    /**
     * Обрабатывает существующую проблемную доставку с проблемным сообщением
     *
     * @return void
     */
    abstract protected function handleExistingExceptionProblem(): void;

    /**
     * Обрабатывает существующую доставку с непроблемным сообщением
     *
     * @return void
     */
    abstract protected function handleExistingExceptionNoProblem(): void;

    /**
     * Обрабатывает доставку с неизвестным сообщением
     *
     * @return void
     */
    abstract protected function handleUnknownMessage(): void;

    /**
     * Получает сообщение о проблемной доставке
     *
     * @return string|null
     */
    protected function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * Подготовительные действия перед методами обработки и запуском команды
     */
    protected function prepare(): void
    {
        // Логика подготовки может быть добавлена в случае необходимости
    }

    /**
     * Нормализует для сравнения сообщение от КС или массив сообщений от КС
     *
     * @param   array             Массив сообщений
     * @param   string  $message  Сообщение
     *
     * @return  string            Нормализованное сообщение
     */
    protected function normalizeMessage(array|string $message): array|string
    {
        if (is_array($message)) {
            return array_map('mb_strtolower', $message);
        }
        
        return mb_strtolower($message);
    }

    /**
     * Сообщение известное неизвестное - находится в списке неизвестных
     *
     * @return  bool
     */
    protected function isMessageKnownUnknown(): bool
    {
        return $this->checkMessageExist($this->getMessage(), CourierServiceMessageTypeEnum::Unknown);
    }

    /**
     * Проверяет, является ли сообщение не неизвестным
     *
     * @return bool
     */
    protected function isMessageUnknown(): bool
    {
        return !$this->checkMessageExist($this->getMessage());
    }

    /**
     * Проверяет, должно ли сообщение быть проигнорировано
     *
     * @return bool
     */
    protected function shouldMessageBeIgnored(): bool
    {
        return $this->checkMessageExist($this->getMessage(), CourierServiceMessageTypeEnum::Ignore);
    }

    /**
     * Проверяет, является ли сообщение не проблемным
     *
     * @return bool
     */
    protected function isNoProblemMessage(): bool
    {
        return $this->checkMessageExist($this->getMessage(), CourierServiceMessageTypeEnum::NoProblem);
    }

    /**
     * Проверяет, является ли сообщение проблемным
     *
     * @return bool
     */
    protected function isProblemMessage(): bool
    {
        return $this->checkMessageExist($this->getMessage(), CourierServiceMessageTypeEnum::Problem);
    }

    /**
     * Проверяет существование сообщения в бд в списке нужного типа
     *
     * @param   string                         $message  Искомое сообщение
     * @param   CourierServiceMessageTypeEnum  $type     Тип сообщения, если null, то все типы
     *
     * @return  bool
     */
    protected function checkMessageExist(string $message, ?CourierServiceMessageTypeEnum $type = null): bool
    {
        /** @var CSMessageRepository */
        $csMessageRepository = Yii::createObject(CSMessageRepository::class);

        $messagesInDb = $csMessageRepository->getOnlyMessagesTexts($this->getCourierId(), $type);

        foreach ($messagesInDb as $messageInDb) {
            if (mb_strpos($this->normalizeMessage($message), $this->normalizeMessage($messageInDb)) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Создает сообщение в бд как Неизвестное
     *
     * @return ?CourierServiceMessage
     */
    protected function createKnownUnknownMessageInDb(): ?CourierServiceMessage
    {
        $csMessageRepository = Yii::createObject(CSMessageRepository::class);
        
        return $csMessageRepository->create([
            'courier_id' => $this->getCourierId(),
            'message' => preg_replace('/ \d{2}\.\d{2}\.\d{4}$/', '', $this->getMessage()),
            'type' => CourierServiceMessageTypeEnum::Unknown->value,
        ]);
    }

    /**
     * Обрабатывает исключение для доставки
     *
     * @param Delivery $delivery
     * @param string $message Сообщение от КС
     *
     * @return void
     */
    public function handleException(Delivery $delivery, string $message): void
    {
        $this->delivery = $delivery;
        $this->message = $message;

        if (!$this->isMessageKnownUnknown() && $this->isMessageUnknown()) {
            $this->createKnownUnknownMessageInDb();
            return;
        }

        if ($this->isMessageKnownUnknown()) {
            return;
        }
            
        if ($this->shouldMessageBeIgnored()) {
            return;
        }
        
        $this->prepare();

        $existingDeliveryException = $this->delivery->deliveryException;

        if ($this->isProblemMessage()) {
            if ($existingDeliveryException) {
                $this->handleExistingExceptionProblem();
            } else {
                $this->handleNonExistentExceptionProblem();
            }
        } elseif ($this->isNoProblemMessage()) {
            if ($existingDeliveryException) {
                $this->handleExistingExceptionNoProblem();
            } else {
                $this->handleNonExistentExceptionNoProblem();
            }
        }
    }
}
