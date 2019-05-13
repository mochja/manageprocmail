<?php

class ManageProcmail_Plugin extends PHPUnit_Framework_TestCase
{
    function setUp() {
        include_once __DIR__ . '/vendor/autoload.php';
    }


    function test_lib() {
    	$recipe = new Ingo_Script_Procmail_Recipe(
            array(
                'action' => 'Ingo_Rule_System_Vacation',
                'action-value' => array(
                    'addresses' => ['a@a.com'],
                    'subject' => 'aaaa',
                    'days' => 2,
                    'reason' => 'sdfgadfasdf',
                    'ignorelist' => [],
                    'excludes' => [],
                    'start' => 0,
                    'end' => 0
                ),
                'disable' => 0
            ),
            []
        );

    	var_dump($recipe->generate());
    }
}

