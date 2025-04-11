<?php

declare(strict_types=1);

namespace app\repository\cached;

use yii\caching\CacheInterface;
use yii\caching\TagDependency;
use app\models\CourierServiceMessage;
use app\repository\CSMessageRepository as BaseCSMessageRepository;
use app\enums\courier\CourierServiceMessageTypeEnum;
use Yii;

/**
 * Кешируемый репозиторий для сообщений курьерской службы.
 * Кеширует методы поиска, включая findAllByCourier.
 */
class CSMessageRepository extends BaseCSMessageRepository
{
    private const CACHE_DURATION = 604800; // 1 неделя в секундах

    /**
     * Тег для кеширования. Инвалидация этого тега приведет к сбросу всех кэш-ключей,
     * связанных с репозиторием
     * @var string
     */
    private static string $cacheTag = self::class;

    private CacheInterface $cache;

    public function __construct(?CacheInterface $cache = null)
    {
        if (!$cache) {
            $cache = Yii::$app->cache;
        }
        
        $this->cache = $cache;
    }

    /**
     * Возвращает массив АР-моделей сообщений курьерской службы из кэша
     * Возвращает результат, индексированный по ID сообщения
     *
     * @param int|null $courierServiceId Идентификатор курьерской службы или null
     *                                   для получения сообщений всех типов одним списком
     * @param CourierServiceMessageTypeEnum|null $type Тип сообщения
     *
     * @return array
     */
    public function findAllByCourier(?int $courierServiceId = null, ?CourierServiceMessageTypeEnum $type = null): array
    {
        $cacheKey = $this->generateCacheKey(__METHOD__, [$courierServiceId]);

        // Кешируем запрос без учета типа
        $messages = $this->cache->getOrSet($cacheKey, function () use ($courierServiceId) {
            return parent::findAllByCourier($courierServiceId);
        }, self::CACHE_DURATION, new TagDependency(['tags' => self::$cacheTag]));

        // Фильтрация по типу, если он указан
        if ($type !== null) {
            $messages = array_filter($messages, fn($message) => $message->type === $type->value);
        }

        return $messages;
    }

    /**
     * Возвращает массив ид поддерживаемых курьерских служб
     *
     * @return array
     */
    public function getSupportedCourierIds(): array
    {
        $allMessages = $this->findAllByCourier(); // Использует кешированную версию findAllByCourier
        return array_unique(array_map(fn($message) => $message->courier_id, $allMessages));
    }

    /**
     * Кешированная версия метода findById
     *
     * @param int $id Идентификатор сообщения
     * @return CourierServiceMessage|null
     */
    public function findById(int $id): ?CourierServiceMessage
    {
        $allMessages = $this->findAllByCourier(); // Использует кешированную версию findAllByCourier
        return $allMessages[$id] ?? null; // Доступ по индексу
    }

    /**
     * Кешированная версия метода findOne
     *
     * @param array $condition Условия поиска
     *
     * @return CourierServiceMessage|null
     */
    public function findOne(array $config = []): ?CourierServiceMessage
    {
        $cacheKey = $this->generateCacheKey(__METHOD__, [$config]);

        return $this->cache->getOrSet($cacheKey, function () use ($config) {
            return parent::findOne($config);
        }, self::CACHE_DURATION, new TagDependency(['tags' => self::$cacheTag]));
    }

    /**
     * Кешированная версия метода findAll
     */
    public function findAll(array $config = []): array
    {
        $cacheKey = $this->generateCacheKey(__METHOD__, [$config]);

        return $this->cache->getOrSet($cacheKey, function () use ($config) {
            return parent::findAll($config);
        }, self::CACHE_DURATION, new TagDependency(['tags' => self::$cacheTag]));
    }

    /**
     * Генерирует уникальный ключ кеша на основе метода и аргументов
     *
     * @param string $method Название метода
     * @param array $args Аргументы вызова метода
     *
     * @return string Сгенерированный ключ кеша
     */
    private function generateCacheKey(string $method, array $args): string
    {
        return self::class . ':' . $method . ':' . md5(serialize($args));
    }
}
