<?php 

declare(strict_types=1); 

use Page\manager\supplier\IndexPage;
use Page\manager\supplier\UpdatePage;
use tests\fixtures\UserFixture;
use tests\fixtures\AuthItemFixture;
use tests\fixtures\AuthItemChildFixture;
use tests\fixtures\AuthAssignmentFixture;

/**
 * Тест добавленого поля GUID 1С. Таск [#6275770694](https://leadgidwebvork.monday.com/boards/5160193477/pulses/6275770694)
 */
class Field1CCodeExistSupplierCest
{
    public const GUID_1C = '111111111111111111111111111111111111';
    public const NON_EXISTING_GUID_1C = '222222222222222222222222222222222222';

    private $supplierId;

    public function _fixtures()
    {
        return [
            'users' => UserFixture::class,
            'auth_item' => AuthItemFixture::class,
            'auth_item_child' => AuthItemChildFixture::class,
            'auth_assignment' => AuthAssignmentFixture::class,
        ];
    }

    public function _before(FunctionalTester $I)
    {
        $I->amLoggedInAsManager($I);

        // Создаем поставщика

        // Нужен склад, чтобы к нему привязать товар
        $stockId = $I->haveRecord('\app\models\base\Stock', [ 'name' => 'some stock']);

        // Поставщик с заданным полем GUID 1C
        $this->supplierId = $I->haveRecord('\app\models\base\Supplier', [ 
            'name' => 'some supplier',
            'warehouse_id' => $stockId,
            'guid1c' => self::GUID_1C,
        ]);
    }

    // tests

    public function indexPageGuid1CFieldExists(FunctionalTester $I)
    {
        $I->wantToTest('На странице index в фильтре существует поле GUID 1C');

        $I->amOnPage([IndexPage::$URL]);
        $I->seeElement(IndexPage::$filterGuid1CField);
    }

    public function indexPageFilterByGuid1CShowsOneResult(FunctionalTester $I)
    {
        $I->wantToTest('На странице index при фильтре по полю GUID 1C отображается 1 результат с найденным товаром');

        $I->amOnPage([IndexPage::$URL]);
        
        $I->fillField(IndexPage::$filterGuid1CField, self::GUID_1C);
        $I->submitForm(IndexPage::$filterForm, []);

        $I->dontSee('Страница не найдена');
        $I->seeGridViewNumberOfRows($I, 1);
    }

    public function indexPageFilterByNotExistingGuid1CShowsZeroResult(FunctionalTester $I)
    {
        $I->wantToTest('На странице index при фильтре по полю GUID 1C с несуществующим кодом, результатов нет');

        $I->amOnPage([IndexPage::$URL]);

        $I->fillField(IndexPage::$filterGuid1CField, self::NON_EXISTING_GUID_1C);
        $I->submitForm(IndexPage::$filterForm, []);
        
        $I->seeGridViewEmpty($I);
    }

    public function editPageGuid1CIsSaved(FunctionalTester $I)
    {
        $I->wantToTest('На странице редактирования поле GUID 1C сохраняется');

        $I->amOnPage(UpdatePage::route(['id' => $this->supplierId]));

        $I->fillField(UpdatePage::$guid1cField, self::GUID_1C);
        $I->submitForm(UpdatePage::$form, []);
        
        $I->seeResponseCodeIsSuccessful();

        $I->amOnPage(UpdatePage::route(['id' => $this->supplierId]));

        $I->seeInField(UpdatePage::$guid1cField, self::GUID_1C);
    }

    public function editPageGuid1CCanBeEmpty(FunctionalTester $I)
    {
        $I->wantToTest('На странице редактирования поле GUID 1C можно задать пустым');

        $I->amOnPage(UpdatePage::route(['id' => $this->supplierId]));

        $I->fillField(UpdatePage::$guid1cField, null);
        $I->submitForm(UpdatePage::$form, []);
        $I->seeResponseCodeIsSuccessful();

        $I->amOnPage(UpdatePage::route(['id' => $this->supplierId]));
    
        $I->seeInField(UpdatePage::$guid1cField, null);
    }
}
