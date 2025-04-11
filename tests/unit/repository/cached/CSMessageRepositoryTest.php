<?php

declare(strict_types=1);

namespace tests\unit\repository\cached;

use app\repository\cached\CSMessageRepository;
use app\models\CourierServiceMessage;
use app\enums\courier\CourierServiceMessageTypeEnum;
use Base\Unit;
use Codeception\Specify;
use yii\caching\CacheInterface;
use yii\caching\TagDependency;
use Codeception\Stub\Expected;

/**
 * Тесты для класса CSMessageRepository (кешируемый репозиторий для сообщений курьерской службы)
 */
class CSMessageRepositoryTest extends Unit
{
    use Specify;

    /**
     * @var CacheInterface
     */
    protected CacheInterface $cache;

    /**
     * @var CSMessageRepository
     */
    protected CSMessageRepository $repository;

    /**
     * @var array Массив данных для тестов
     */
    protected array $data;

    protected function _before(): void
    {
        // Создаем mock кеша
        $this->cache = $this->makeEmpty(CacheInterface::class);
        
        // Массив данных для тестов
        $this->data = [
            1 => $this->make(CourierServiceMessage::class, [
                'id' => 1,
                'courier_id' => 10,
                'type' => CourierServiceMessageTypeEnum::SetDeliveryDate->value,
                'message' => 'Message 1',
            ]),
            2 => $this->make(CourierServiceMessage::class, [
                'id' => 2,
                'courier_id' => 10,
                'type' => CourierServiceMessageTypeEnum::NoProblem->value,
                'message' => 'Message 2',
            ]),
            3 => $this->make(CourierServiceMessage::class, [
                'id' => 3,
                'courier_id' => 20,
                'type' => CourierServiceMessageTypeEnum::SetDeliveryDate->value,
                'message' => 'Message 3',
            ]),
        ];

        // Инициализация репозитория
        $this->repository = new CSMessageRepository($this->cache);
    }

    /**
     * Тест метода findAllByCourier
     */
    public function testMethodFindAllByCourier(): void
    {
        $this->specify('Когда кеш пустой, должно происходить получение данных из родительского метода и кеширование', function () {
            $this->cache = $this->makeEmpty(CacheInterface::class, [
                'getOrSet' => Expected::once(function () {
                    return array_slice($this->data, 0, 2);
                })
            ]);

            $this->repository = new CSMessageRepository($this->cache);

            $messages = $this->repository->findAllByCourier(10);
            
            expect('Сообщений должно быть 2', count($messages))->equals(2);
            expect('Должно быть сообщение 1', $messages[0]->message)->equals('Message 1');
            expect('Должно быть сообщение 2', $messages[1]->message)->equals('Message 2');
        });

        $this->specify('Когда тип указан, возвращаем только сообщения соответствующего типа', function () {
            $this->cache = $this->makeEmpty(CacheInterface::class, [
                'getOrSet' => Expected::once(function () {
                    return $this->data;
                })
            ]);

            $this->repository = new CSMessageRepository($this->cache);

            $messages = $this->repository->findAllByCourier(
                courierServiceId: 10,
                type: CourierServiceMessageTypeEnum::SetDeliveryDate
            );

            expect('Должно быть одно сообщение типа SetDeliveryDate', count($messages))->equals(2);
            expect('Сообщение должно быть "Message 1"', $messages[1]->message)->equals('Message 1');
            expect('Сообщение должно быть "Message 1"', $messages[3]->message)->equals('Message 3');
        });
    }

    /**
     * Тест метода findById
     */
    public function testMethodFindById(): void
    {
        $this->specify('Когда сообщение с заданным ID найдено в кешированных данных', function () {
            $this->cache = $this->makeEmpty(CacheInterface::class, [
                'getOrSet' => Expected::once(function () {
                    return $this->data;
                })
            ]);

            $this->repository = new CSMessageRepository($this->cache);

            $message = $this->repository->findById(1);
            expect('Сообщение должно быть найдено', $message)->notNull();
            expect('Сообщение должно быть "Message 1"', $message->message)->equals('Message 1');
        });

        $this->specify('Когда сообщение с заданным ID не найдено в кешированных данных', function () {
            $this->cache = $this->makeEmpty(CacheInterface::class, [
                'getOrSet' => Expected::once(function () {
                    return $this->data;
                })
            ]);

            $this->repository = new CSMessageRepository($this->cache);

            $message = $this->repository->findById(999);
            expect('Сообщение не должно быть найдено', $message)->null();
        });
    }

    /**
     * Тест метода getSupportedCourierIds
     */
    public function testMethodGetSupportedCourierIds(): void
    {
        $this->specify('Метод должен возвращать уникальные идентификаторы курьерских служб', function () {
            $this->cache = $this->makeEmpty(CacheInterface::class, [
                'getOrSet' => Expected::once(function () {
                    return $this->data;
                })
            ]);

            $this->repository = new CSMessageRepository($this->cache);

            $courierIds = $this->repository->getSupportedCourierIds();
            expect('Должны быть получены уникальные идентификаторы', $courierIds)->equals([
                1 => 10, 
                3 => 20
            ]);
        });
    }

    /**
     * Тест метода findOne
     */
    public function testMethodFindOne(): void
    {
        $this->specify('Когда сообщение найдено по условиям', function () {
            $this->cache = $this->makeEmpty(CacheInterface::class, [
                'getOrSet' => Expected::once(function () {
                    return $this->data[1];
                })
            ]);

            $this->repository = new CSMessageRepository($this->cache);

            $message = $this->repository->findOne(['id' => 1]);
            expect('Сообщение должно быть найдено', $message)->notNull();
            expect('Сообщение должно быть "Message 1"', $message->message)->equals('Message 1');
        });
    }
}
