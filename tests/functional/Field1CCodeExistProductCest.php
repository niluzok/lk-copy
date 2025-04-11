<?php 

declare(strict_types=1); 

use Page\manager\product\IndexPage;
use Page\manager\product\UpdatePage;
use tests\fixtures\UserFixture;
use tests\fixtures\AuthItemFixture;
use tests\fixtures\AuthItemChildFixture;
use tests\fixtures\AuthAssignmentFixture;

/**
 * Тест добавленого поля GUID 1С. Таск [#6275770694](https://leadgidwebvork.monday.com/boards/5160193477/pulses/6275770694)
 */
class Field1CCodeExistProductCest
{
    public const GUID_1C = '111111111111111111111111111111111111';
    public const NON_EXISTING_GUID_1C = '222222222222222222222222222222222222';

    private $productId;

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

        // Создаем товар

        // Нужен клиент, чтобы к нему привязать товар
        $clientId = $I->haveRecord('\app\models\base\Client', [ 'name' => 'some client', 'token' => 'edsfsdf' ]);

        // Товар с заданным полем  GUID 1C
        $this->productId = $I->haveRecord('\app\models\base\Good', [ 
            'name' => 'some good',
            'client_id' => $clientId,
            'guid1c' => self::GUID_1C,
        ]);
    }

    // tests
    
    public function indexPageGuid1CFieldExist(FunctionalTester $I)
    {
        $I->wantToTest('На странице index в фильтре существует поле GUID 1С');

        $I->amOnPage(IndexPage::$URL);
        $I->seeElement(IndexPage::$filterGuid1CField);
    }

    public function indexPageFilterByGuid1CShowsOneResult(FunctionalTester $I)
    {
        $I->wantToTest('На странице index при фильтре по полю GUID 1C отображается 1 товар с нужным кодом');

        $I->amOnPage(IndexPage::$URL);
        
        $I->fillField(IndexPage::$filterGuid1CField, self::GUID_1C);
        $I->submitForm(IndexPage::$filterForm, []);

        $I->dontSee('Страница не найдена');
        
        $I->seeGridViewNumberOfRows($I, 1);
    }

    public function indexPageFilterByNotExistingGuid1CShowsZeroResult(FunctionalTester $I)
    {
        $I->wantToTest('На странице index при фильтре по полю GUID 1C с несуществующим кодом, результатов нет');

        $I->amOnPage(IndexPage::$URL);

        $I->fillField(IndexPage::$filterGuid1CField, self::NON_EXISTING_GUID_1C);
        $I->submitForm(IndexPage::$filterForm, []);
        $I->seeGridViewEmpty($I);
    }

    public function editPageGuid1CIsSaved(FunctionalTester $I)
    {
        $I->wantToTest('На странице редактирования поле GUID 1C сохраняется');

        $I->amOnPage(UpdatePage::route(['id' => $this->productId]));

        $I->fillField(UpdatePage::$guid1cField, self::GUID_1C);
        $I->submitForm(UpdatePage::$form, []);
        $I->moveBack();
        $I->seeInField(UpdatePage::$guid1cField, self::GUID_1C);
    }

    public function editPageGuid1CCanBeEmpty(FunctionalTester $I)
    {
        $I->wantToTest('На странице редактирования поле GUID 1C можно задать пустым');

        $I->amOnPage(UpdatePage::route(['id' => $this->productId]));

        $I->fillField(UpdatePage::$guid1cField, null);
        $I->submitForm(UpdatePage::$form, []);
        $I->seeResponseCodeIsSuccessful();
    }
}
