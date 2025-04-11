<?php

declare(strict_types=1);

namespace app\tests\unit\models\validator;

use app\models\validator\DeliveryStatusValidator;
use app\models\Delivery;
use app\models\StatDelivery;
use app\exception\LkNeologisticsException;
use app\exception\StatusChangeForbiddenException;
use Codeception\Specify;
use Base\Unit;

use function Codeception\Extension\codecept_log;

class DeliveryStatusValidatorTest extends Unit
{
    use Specify;

    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var DeliveryStatusValidator
     */
    private DeliveryStatusValidator $validator;

    protected function _before()
    {
        $this->validator = new DeliveryStatusValidator();
    }

    /**
     * Создает макет объекта Delivery с указанным статусом и соответствующим объектом StatDelivery
     *
     * @param string $status
     * @return Delivery
     */
    private function createDeliveryMock(string $status, bool $deficit = false): Delivery
    {
        $statDelivery = $this->make(StatDelivery::class, [
            'delivery_status' => $status,
        ]);

        $delivery = $this->make(Delivery::class, [
            'getStatusDeficit' => $deficit,
            'delivery_status' => $status,
        ]);

        $delivery->populateRelation('statDelivery', $statDelivery);

        return $delivery;
    }

    /**
     * Тестирует метод checkNewStatusValid
     * Проверяет, что выбрасывается исключение при пустом статусе
     */
    public function testCheckNewStatusValidEmpty()
    {
        $this->specify("пустой статус приводит к выбросу LkNeologisticsException", function () {
            $this->expectException(LkNeologisticsException::class);
            $this->validator->checkNewStatusValid('');
        });
    }

    /**
     * Тестирует метод checkNewStatusValid
     * Проверяет, что выбрасывается исключение при неверном статусе
     */
    public function testCheckNewStatusValidInvalid()
    {
        $this->specify("неверный статус приводит к выбросу LkNeologisticsException", function () {
            $this->expectException(LkNeologisticsException::class);
            $this->validator->checkNewStatusValid('INVALID_STATUS');
        });
    }

    /**
     * Тестирует метод checkNewStatusValid
     * Проверяет, что корректный статус не вызывает исключений
     */
    public function testCheckNewStatusValid()
    {
        $this->specify("корректный статус не вызывает исключений", function () {
            $this->validator->checkNewStatusValid(Delivery::STATUS_WAITING);
        });
    }

    /**
     * Тестирует метод checkCorrectChangeStatusForDelivery с начальным статусом STATUS_WAITING
     */
    public function testCheckCorrectChangeStatusForDeliveryFromWaiting()
    {
        $delivery = $this->createDeliveryMock(Delivery::STATUS_WAITING);

        $validStatusesToChangeTo = [
            Delivery::STATUS_IN_STOCK,
            Delivery::STATUS_SEND_CC,
            Delivery::STATUS_CANCEL,
            Delivery::STATUS_DEFICIT,
        ];

        $invalidStatusesToChangeTo = [
            Delivery::STATUS_WAIT_TO_STOCK,
        ];

        foreach ($validStatusesToChangeTo as $newStatus) {
            $this->specify("смена статуса с STATUS_WAITING на $newStatus не вызывает исключений", function () use ($delivery, $newStatus) {
                $this->assertNull($this->validator->checkCorrectChangeStatusForDelivery($delivery, $newStatus));
            });
        }

        foreach ($invalidStatusesToChangeTo as $newStatus) {
            $this->specify("смена статуса с STATUS_WAITING на $newStatus вызывает исключение", function () use ($delivery, $newStatus) {
                $this->expectException(StatusChangeForbiddenException::class);
                $this->validator->checkCorrectChangeStatusForDelivery($delivery, $newStatus);
            });
        }
    }

    /**
     * Тестирует метод checkCorrectChangeStatusForDelivery с начальным статусом STATUS_IN_STOCK
     */
    public function testCheckCorrectChangeStatusForDeliveryFromInStock()
    {
        $delivery = $this->createDeliveryMock(Delivery::STATUS_IN_STOCK);

        $validStatusesToChangeTo = [
            Delivery::STATUS_SEND_CC,
            Delivery::STATUS_CANCEL,
        ];

        $invalidStatusesToChangeTo = [
            Delivery::STATUS_WAITING,
            Delivery::STATUS_DEFICIT,
            Delivery::STATUS_WAIT_TO_STOCK,
        ];

        foreach ($validStatusesToChangeTo as $newStatus) {
            $this->specify("смена статуса с STATUS_IN_STOCK на $newStatus не вызывает исключений", function () use ($delivery, $newStatus) {
                $this->assertNull($this->validator->checkCorrectChangeStatusForDelivery($delivery, $newStatus));
            });
        }

        foreach ($invalidStatusesToChangeTo as $newStatus) {
            $this->specify("смена статуса с STATUS_IN_STOCK на $newStatus вызывает исключение", function () use ($delivery, $newStatus) {
                $this->expectException(StatusChangeForbiddenException::class);
                $this->validator->checkCorrectChangeStatusForDelivery($delivery, $newStatus);
            });
        }
    }

    /**
     * Тестирует метод checkCorrectChangeStatusForDelivery с начальным статусом STATUS_WAIT_TO_STOCK
     */
    public function testCheckCorrectChangeStatusForDeliveryFromWaitToStock()
    {
        $delivery = $this->createDeliveryMock(Delivery::STATUS_WAIT_TO_STOCK);

        $validStatusesToChangeTo = [
            Delivery::STATUS_SEND_CC,
            Delivery::STATUS_WAITING,
            Delivery::STATUS_CANCEL,
        ];

        $invalidStatusesToChangeTo = [
            Delivery::STATUS_IN_STOCK,
            Delivery::STATUS_DEFICIT,
        ];

        foreach ($validStatusesToChangeTo as $newStatus) {
            $this->specify("смена статуса с STATUS_WAIT_TO_STOCK на $newStatus не вызывает исключений", function () use ($delivery, $newStatus) {
                $this->assertNull($this->validator->checkCorrectChangeStatusForDelivery($delivery, $newStatus));
            });
        }

        foreach ($invalidStatusesToChangeTo as $newStatus) {
            $this->specify("смена статуса с STATUS_WAIT_TO_STOCK на $newStatus вызывает исключение", function () use ($delivery, $newStatus) {
                $this->expectException(StatusChangeForbiddenException::class);
                $this->validator->checkCorrectChangeStatusForDelivery($delivery, $newStatus);
            });
        }
    }

    /**
     * Тестирует метод checkCorrectChangeStatusForDelivery с начальным статусом getStatusDeficit() == true
     */
    public function testCheckCorrectChangeStatusForDeliveryWithStatusDeficit()
    {
        $delivery = $this->createDeliveryMock(Delivery::STATUS_WAITING, true);

        $validStatusesToChangeTo = [
            Delivery::STATUS_WAITING,
        ];

        $invalidStatusesToChangeTo = [
            Delivery::STATUS_IN_STOCK,
            Delivery::STATUS_SEND_CC,
            Delivery::STATUS_CANCEL,
        ];

        foreach ($validStatusesToChangeTo as $newStatus) {
            $this->specify("смена статуса с STATUS_DEFICIT на $newStatus не вызывает исключений", function () use ($delivery, $newStatus) {
                $this->assertNull($this->validator->checkCorrectChangeStatusForDelivery($delivery, $newStatus));
            });
        }

        $this->specify("смена статуса с STATUS_DEFICIT на $newStatus вызывает исключение", function () use ($delivery, $newStatus, $invalidStatusesToChangeTo) {
            foreach ($invalidStatusesToChangeTo as $newStatus) {
                codecept_debug("### newStatus = {$newStatus} ###");
                $this->expectException(StatusChangeForbiddenException::class);
                $this->validator->checkCorrectChangeStatusForDelivery($delivery, $newStatus);
            }
        });
    }
}
