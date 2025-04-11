<?php

class RootApiTestCest
{
    // tests
    public function tryToTest(ApiTester $I)
    {
        $I->wantToTest('api модуль доступен по корневому урлу');

        $I->sendGet('/');
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();
        $I->seeResponseEquals('"API Logistics service"');
    }
}
