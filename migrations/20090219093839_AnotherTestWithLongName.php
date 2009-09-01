<?php
class AnotherTestWithLongName extends Svenax_Migration_Base
{
    public function up()
    {
        $this->execWithMessage('Creating table AnotherTestWithLongName',
            'CREATE TABLE AnotherTestWithLongName (
                id INT(10) UNSIGNED NOT NULL PRIMARY KEY auto_increment
            )'
        );
    }

    public function down()
    {
        $this->execWithMessage('Dropping table  AnotherTestWithLongName', 'DROP TABLE AnotherTestWithLongName');
    }
}
