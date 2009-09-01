<?php
class AnotherTest extends Svenax_Migration_Base
{
    public function up()
    {
        $this->execWithMessage('Creating table AnotherTest',
            'CREATE TABLE AnotherTest (
                id INT(10) UNSIGNED NOT NULL PRIMARY KEY auto_increment
            )'
        );
    }

    public function down()
    {
        $this->execWithMessage('Dropping table  AnotherTest', 'DROP TABLE AnotherTest');
    }
}
