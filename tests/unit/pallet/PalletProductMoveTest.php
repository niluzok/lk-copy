<?php

declare(strict_types=1);

namespace tests\unit\classes\pallet;

use app\classes\pallet\PalletProductMove;
use app\models\Consignment as ProductRelease;
use app\models\Pallet;
use app\models\PalletRelease;
use app\models\Stock as Warehouse;
use Base\Unit;
use Codeception\Specify;
use assertion\RemainsTrait;
use app\models\Client;
use app\models\GoodsInStock;
use app\models\RemainsOfGoods;
use assertion\PalletTrait;
use Yii;

/**
 * Тестирование класса PalletProductMove
 * 
 * GoodsInStock не нужно проверять, тк этот класс не меняет его, работает только с
 * RemainsOfGoods тк там есть палеты
 */
class PalletProductMoveTest extends Unit
{
    use Specify, RemainsTrait, PalletTrait;

    /**
     * @var PalletProductMove
     */
    protected $palletProductMove;

    /**
     * @var array
     */
    protected $data;

    /**
     * @var int
     */
    protected $palletId1;

    /**
     * @var int
     */
    protected $palletId2;

    /**
     * Подготовка тестов
     */
    protected function _before()
    {
        parent::_before();

        $this->mockEventBehaviorEmpty();

        $this->data = [
            'client' => [
                'id' => 1,
                'name' => 'Test Client',
                'token' => '12345'
            ],
            'warehouse' => [
                'id' => 0, // подставляется
                'name' => 'Test Warehouse'
            ],
            'consignment' => ['id' => 1],
            'pallet1' => [
                'id' => 1,
                'product_release_id' => 1,
                'pcs_per_ca' => 0, // depricated
                'cas_per_pl' => 0, // depricated
            ],
            'pallet2' => [
                'id' => 2,
                'product_release_id' => 1,
                'pcs_per_ca' => 0, // depricated
                'cas_per_pl' => 0, // depricated
            ],
        ];

        $this->data['warehouse']['id'] = $this->tester->haveRecord(Warehouse::class, $this->data['warehouse']);
        $this->tester->haveRecord(Client::class, $this->data['client']);
        $this->tester->haveRecord(ProductRelease::class, $this->data['consignment']);
        $this->tester->haveRecord(Pallet::class, $this->data['pallet1']);
        $this->tester->haveRecord(Pallet::class, $this->data['pallet2']);
        
        $this->resetRemainsAndPalletRelease();

        $this->palletProductMove = new PalletProductMove($this->data['warehouse']['id']);
        $this->palletId1 = $this->data['pallet1']['id'];
        $this->palletId2 = $this->data['pallet2']['id'];

        // Внутри класса есть readonly свойство, а specify пытается его скопировать и записать,
        // что вызывает ошибку
        $this->specifyConfig()->ignore('palletProductMove');
    }

    protected function resetRemainsAndPalletRelease()
    {
        PalletRelease::deleteAll([]);
        Yii::$app->db->createCommand()->setSql('delete from remains_of_goods_logger')->execute();
        RemainsOfGoods::deleteAll([]);
        GoodsInStock::deleteAll([]);

        $this->havePalletRelease($this->data['pallet1']['id'], 10);

        $this->haveRemainOfGoods($this->data['pallet1']['id'], 10);
        $this->haveRemainOfGoods($this->data['pallet2']['id'], 0);
        $this->haveRemainOfGoods(null, 10);
        $this->haveGoodsInStock(10);
    }

    /**
     * Тестирование метода fillEmptyPalletWithProducts
     *
     * Метод должен заполнять пустую паллету продуктами со склада
     */
    public function testMethodFillEmptyPalletWithProducts()
    {
        $this->specify("Метод fillEmptyPalletWithProducts должен заполнять пустую палету продуктами со склада", function() {
            $this->seeRemains(10, 0, 10, checkGoodsInStock: false);
            $this->seePalletRelease($this->data['pallet1']['id'], 10);
            $this->seePalletRelease($this->data['pallet2']['id'], 0);

            $this->palletProductMove->fillEmptyPalletWithProducts(
                warehouseId: $this->data['warehouse']['id'],
                productReleaseId: 1,
                count: 10,
                palletId: 2
            );

            $this->seeRemains(10, 10, 0, checkGoodsInStock: false);
            $this->seePalletRelease($this->data['pallet1']['id'], 10);
            $this->seePalletRelease($this->data['pallet2']['id'], 10);
        });
    }

    /**
     * Тестирование метода moveProductFromFirstPalletOnSecondPallet
     *
     * Метод должен перемещать товар с одной паллеты на другую
     */
    public function testMethodMoveProductFromFirstPalletOnSecondPallet()
    {
        $this->seeRemains(10, 0, 10, checkGoodsInStock: false);
        $this->seePalletRelease($this->data['pallet1']['id'], 10);
        $this->seePalletRelease($this->data['pallet2']['id'], 0);
        
        $this->specify("Метод moveProductFromFirstPalletOnSecondPallet должен перемещать товар с одной паллеты на другую", function() {
            $palletReleaseFirstPallet = $this->tester->grabRecord(PalletRelease::class, ['pallet_id' => $this->data['pallet1']['id']]);

            $this->palletProductMove->moveProductFromFirstPalletOnSecondPallet(
                $palletReleaseFirstPallet, 
                5, 
                $this->data['pallet2']['id']
            );

            $this->seeRemains(5, 5, 10, checkGoodsInStock: false);
            $this->seePalletRelease($this->data['pallet1']['id'], 5);
            $this->seePalletRelease($this->data['pallet2']['id'], 5);
        });
    }

    /**
     * Тестирование метода changeProductOnPallet
     *
     * Метод должен изменять количество товара на паллете и обновлять остатки
     */
    public function testMethodChangeProductOnPallet()
    {
        $this->specify("Метод changeProductOnPallet должен изменять количество товара на палете и обновлять остатки", function() {
            $palletRelease = $this->tester->grabRecord(PalletRelease::class, ['pallet_id' => $this->data['pallet1']['id']]);
            
            $this->seeRemains(10, 0, 10, checkGoodsInStock: false);
            $this->seePalletRelease($this->data['pallet1']['id'], 10);
            $this->seePalletRelease($this->data['pallet2']['id'], 0);

            $this->palletProductMove->changeProductOnPallet($this->data['warehouse']['id'], $palletRelease, 5);

            // Метод делает только половину перемещения – добавляет на палету, но не убирает с пола
            $this->seeRemains(15, 0, 10, checkGoodsInStock: false);
            $this->seePalletRelease($this->data['pallet1']['id'], 15);
            $this->seePalletRelease($this->data['pallet2']['id'], 0);
        });
    }

    /**
     * Тестирование метода changeProductInWarehouseWithoutPallet
     *
     * Метод должен изменять количество товара на складе вне паллет
     */
    public function testMethodChangeProductInWarehouseWithoutPallet()
    {
        $this->specify("Метод changeProductInWarehouseWithoutPallet должен изменять количество товара на складе вне паллет", function() {
            $this->seeRemains(10, 0, 10, checkGoodsInStock: false);
            $this->seePalletRelease($this->data['pallet1']['id'], 10);
            $this->seePalletRelease($this->data['pallet2']['id'], 0);

            $this->palletProductMove->changeProductInWarehouseWithoutPallet(
                $this->data['warehouse']['id'], 
                $this->data['consignment']['id'], 
                5
            );

            $this->seeRemains(10, 0, 5, checkGoodsInStock: false);
            // На палетах ничего не меняется
            $this->seePalletRelease($this->data['pallet1']['id'], 10);
            $this->seePalletRelease($this->data['pallet2']['id'], 0);
        });
    }

    /**
     * Тестирование метода transferProductBetweenWarehouseAndPallet
     *
     * Метод должен изменять количество товара на паллете и на складе
     */
    public function testMethodTransferProductBetweenWarehouseAndPallet()
    {
        $this->specify("Метод transferProductBetweenWarehouseAndPallet должен изменять количество товара на паллете и на складе", function() {
            $palletRelease = $this->tester->grabRecord(PalletRelease::class, ['pallet_id' => $this->data['pallet1']['id']]);

            $this->seeRemains(10, 0, 10, checkGoodsInStock: false);
            $this->seePalletRelease($this->data['pallet1']['id'], 10);
            $this->seePalletRelease($this->data['pallet2']['id'], 0);

            $this->palletProductMove->transferProductBetweenWarehouseAndPallet($palletRelease, 15);

            $this->seeRemains(15, 0, 5, checkGoodsInStock: false);
            $this->seePalletRelease($this->data['pallet1']['id'], 15);
            $this->seePalletRelease($this->data['pallet2']['id'], 0);
        });
    }

    /**
     * Тестирование метода putOnPallet
     *
     * Метод должен класть товар на паллету и обновлять остатки
     */
    public function testMethodPutOnPallet()
    {
        $this->specify("Метод putOnPallet должен класть товар на паллету и обновлять остатки", function() {
            $this->seeRemains(10, 0, 10, checkGoodsInStock: false);
            $this->seePalletRelease($this->data['pallet1']['id'], 10);
            $this->seePalletRelease($this->data['pallet2']['id'], 0);

            $this->palletProductMove->putOnPallet(
                $this->data['consignment']['id'], 
                $this->data['pallet2']['id'], 
                5
            );

            $this->seeRemains(10, 5, 5, checkGoodsInStock: false);
            $this->seePalletRelease($this->data['pallet1']['id'], 10);
            $this->seePalletRelease($this->data['pallet2']['id'], 5);
        });
    }

    /**
     * Тестирование метода removeFromPallet
     *
     * Метод должен удалять продукт с палеты и обновлять остатки на складе
     */
    public function testMethodRemoveFromPallet()
    {
        $this->specify("Метод removeFromPallet должен удалять продукт с палеты и обновлять остатки на складе", function() {
            $palletRelease = $this->tester->grabRecord(PalletRelease::class, ['pallet_id' => $this->data['pallet1']['id']]);

            $this->seeRemains(10, 0, 10, checkGoodsInStock: false);
            $this->seePalletRelease($this->data['pallet1']['id'], 10);
            $this->seePalletRelease($this->data['pallet2']['id'], 0);

            $this->palletProductMove->removeFromPallet($palletRelease, 5);

            $this->seeRemains(5, 0, 15, checkGoodsInStock: false);
            $this->seePalletRelease($this->data['pallet1']['id'], 5);
            $this->seePalletRelease($this->data['pallet2']['id'], 0);
        });
    }
}
