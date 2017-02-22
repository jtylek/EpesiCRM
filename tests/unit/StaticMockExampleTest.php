<?php

use AspectMock\Test;

class StaticMockExampleTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected function _before()
    {
    }

    protected function _after()
    {
    }

    // tests
    public function testExample()
    {
        $aclMock = Test::double('Acl', ['get_user' => 'user']);
        $contactMock = Test::double('CRM_ContactsCommon', ['get_contact_by_user_id' => 'my_contact']);

        $record = \CRM_ContactsCommon::get_my_record();
        $this->assertEquals($record, 'my_contact');

        $aclMock->verifyInvokedOnce('get_user');
        $contactMock->verifyInvoked('get_contact_by_user_id', ['user']);
    }
}