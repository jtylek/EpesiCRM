<?php 
$I = new AcceptanceTester($scenario);
$I->wantTo('Login');
$I->amOnPage('/');
$I->waitForEpesi();
$I->see('username');
$I->fillField('username', 'admin');
$I->fillField('password', 'admin1');
$I->click('submit_button');
$I->waitForEpesi();
$I->seeElement('.logged_as');
