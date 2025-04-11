<?php

class OneCCreateProductCest
{
    public function _before(ApiTester $I)
    {
    }

    // tests
    public function tryToTest(ApiTester $I)
    {
        // $I->haveHttpHeader('Api-Key', getenv('API_KEY_1C'));
        // var_dump(defined('IS_API_CALL'));

        // die;

        $I->sendPost('onec/v1/product', [
            'guid1c' => '111111111111111111111111111111111111',
            'gtin' => '00012345678905',
            'name' => 'created by 1c api call',
            'units' => 'кг',
        ]);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();
        // $I->seeResponseEquals('"API Logistics service"');
        // $I->seeResponseContainsJson([
        //     'guid1c' => '111111111111111111111111111111111111',
        //     'name' => 'created by 1c api call',
        //     'gtin' => '00012345678905',
        //     'units' => 'кг',
        // ]);
    }
}
