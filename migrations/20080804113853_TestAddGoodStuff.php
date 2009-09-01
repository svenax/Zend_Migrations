<?php
class TestAddGoodStuff extends Svenax_Migration_Base
{
    public function up()
    {
        $this->execWithMessage(
            'Creating table migration_test',
            'CREATE TABLE migration_test (
                id INT(10) UNSIGNED NOT NULL PRIMARY KEY auto_increment,
                text VARCHAR(255) NOT NULL DEFAULT "blank",
                another DATETIME
            )'
        );
        $this->queryWithMessage(
            'Adding default data',
            'INSERT INTO migration_test VALUES
            (default, "test", 2008-08-08),
            (3, default, 2008-08-09),
            (9, "laksjdad", 2008-08-07)'
        );
    }
    
    public function down()
    {
        $this->execWithMessage('Dropping table migration_test', 'DROP TABLE migration_test');
    }
}