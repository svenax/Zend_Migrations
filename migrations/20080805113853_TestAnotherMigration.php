<?php
class TestAnotherMigration extends Svenax_Migration_Base
{
    public function up()
    {
        $this->execWithMessage(
            'Creating table migration_node_tbl',
            'CREATE TABLE migration_node_tbl (
                workflow_node_id int(10) unsigned NOT NULL auto_increment,
                workflow_id int(10) unsigned NOT NULL,
                class varchar(255) NOT NULL,
                configuration varchar(64000) NOT NULL,
                PRIMARY KEY (workflow_node_id,workflow_id)
            )'
        );
        $this->execWithMessage(
            'Creating table migration_tbl',
            'CREATE TABLE migration_tbl (
                workflow_id int(10) unsigned NOT NULL auto_increment,
                `name` varchar(32) NOT NULL,
                version int(10) unsigned NOT NULL default "1",
                time_created datetime default NULL,
                PRIMARY KEY  (workflow_id),
                UNIQUE KEY name_version (`name`,version)
            )'
        );
        $this->execWithMessage(
            'Creating table migration_variable_handler_tbl',
            'CREATE TABLE migration_variable_handler_tbl (
                workflow_id int(10) unsigned NOT NULL,
                class varchar(255) NOT NULL,
                variable varchar(255) NOT NULL,
                PRIMARY KEY  (class)
            )'
        );
    }
    
    public function down()
    {
        $start = microtime(true);
        $this->exec('DROP TABLE migration_node_tbl');
        $this->exec('DROP TABLE migration_tbl');
        $this->exec('DROP TABLE migration_variable_handler_tbl');
        $this->sayWithTime('migration tables dropped', $start);
    }
}