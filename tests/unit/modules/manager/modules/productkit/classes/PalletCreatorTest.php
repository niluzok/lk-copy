<?php 

declare(strict_types=1); 

namespace tests\unit\modules\manager\modules\productkit\classes;

use Codeception\Stub\Expected;
use app\modules\manager\modules\productkit\classes\PalletCreator;
use app\models\productkit\ProductKitBox;
use app\models\Pallet;
use yii\web\ServerErrorHttpException;
use Codeception\Specify;
use Yii;
use app\models\BoxSizeType;
use app\models\Consignment;

class PalletCreatorTest extends \Base\Unit
{
    use Specify;

    private $releaseId;

    protected function _before()
    {
        parent::_before();
        $this->releaseId = $this->tester->haveRecord(Consignment::class, []);

        // Мок для поведения палеты, чтобы не запускалось сохранение PalletHistoryRecorder
        $this->mockEventBehaviorEmpty();
    }

    /**
     * Тест успешного создания паллет
     */
    public function testCreatePalletForBoxSuccess()
    {
        $this->specify("Создает паллет успешно при переданном валидном ProductKitBox", function() {
            // Создание мока модели ProductKitBox
            $productKitBox = $this->make(ProductKitBox::class, [
                'consignment_id' => $this->releaseId,
            ]);

            $productKitBox->populateRelation('sizeType', $this->make(BoxSizeType::class, [
                'pcs_per_ca' => 10,
            ]));

            // Создание экземпляра PalletCreator и вызов метода
            $palletCreator = new PalletCreator();
            $pallet = $palletCreator->createPalletForBox($productKitBox);

            // Проверка результата
            $this->assertInstanceOf(Pallet::class, $pallet);
            $this->assertEquals($pallet->status, Pallet::STATUS_DRAFT);
            $this->assertNotEmpty($pallet->sscc);
        });
    }

    /**
     * Тест неудачного создания паллет
     */
    public function testCreatePalletForBoxFailure()
    {
        $this->specify("Выбрасывает исключение при неудачном создании паллет", function() {
            $this->expectException(ServerErrorHttpException::class);
            $this->expectExceptionMessage('Error creating pallet');

            // Создание мока модели ProductKitBox
            $productKitBox = $this->make(ProductKitBox::class, [
                'consignment_id' => $this->releaseId,
            ]);

            $productKitBox->populateRelation('sizeType', $this->make(BoxSizeType::class, [
                'pcs_per_ca' => 10,
            ]));

            // Создание мока модели Pallet
            $pallet = $this->make(Pallet::class, [], [
                'generateSSCC' => Expected::once(),
                'save' => Expected::once(false), // <=== не сохранение
            ]);

            // Создание мока Yii::createObject для возврата мока Pallet
            Yii::$container->set('app\models\Pallet', $pallet);

            // Создание экземпляра PalletCreator и вызов метода
            $palletCreator = new PalletCreator();
            $palletCreator->createPalletForBox($productKitBox);
        });
    }
}
