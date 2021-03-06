<?php

class Frontend_ErrorCest extends CestAbstract
{
    public function seeErrorPages(FunctionalTester $I): void
    {
        $I->wantTo('Verify error code pages.');

        $I->amOnPage('/playfake');
        $I->seeResponseCodeIs(404);
        $I->see('404');
    }
}
