<?php

class OneCV1RootTestCest
{
    // tests
    public function tryToTest(ApiTester $I)
    {
        $I->wantToTest('1С v1 модуль доступен по корневому урлу');
        
        $I->sendGet('/onec/v1');
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();
    }
}
