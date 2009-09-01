<?php
/**
 * This class is used so we can add a bunch of fields and indexes to a table
 * with a fluent interface easily. It makes it possible to code a little like
 * Ruby block syntax.
 *
 * @package default
 */
class Svenax_Migration_TableProxy
{
    private $_migration;
    private $_table;
    
    public function __construct($migration, $name, $options)
    {
        $_migration = $migration;
        $_table = $name;
        
        extract(array_merge(
            array('id' => true),
            $options
        ));
        
        $this->_migration->addField($this->_table, $id === true ? 'id' : $id, 'int', array('primary_key' => true));
    }

    public function addField($name, $type, $options = array())
    {
        $this->_migration->addField($this->_table, $name, $options);
        
        return $this;
    }
    
    public function addTimestamps($options = array())
    {
        extract(array_merge(
            array('created_at' => true, 'updated_at' => true),
            $options
        ));
        
        $this->addField($created_at === true ? 'created_at' : $created_at, 'timestamp');
        $this->addField($updated_at === true ? 'updated_at' : $updated_at, 'timestamp');
        
        return $this;
    }
    
    public function addIndex($field, $name, $options)
    {
        $this->_migration->addIndex($this->_table, $field, $name, $options);
        
        return $this;
    }
}