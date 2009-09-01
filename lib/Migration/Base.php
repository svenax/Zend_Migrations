<?php
class Svenax_Migration_Exception extends Exception {}

/**
 * Database migrations based on ideas from Ruby on Rails and Google Gears.
 *
 * This class is used to implement the migrations through the up and down
 * methods. Subclass and use these methods for your own migrations.
 *
 * @package Migration
 */
class Svenax_Migration_Base
{
    private $verbose;
    private $version;
    private $name;
    protected $db;
    protected $dbLogger;

    /**
     * undocumented function
     *
     * @param string $version
     * @param string $name
     * @param bool $verbose True if we print status text, false if not
     * @access public
     */
    public function __construct($version, $name, $verbose = true)
    {
        $this->verbose = $verbose;
        if (is_string($version) && $version != '') $version = ' ' . $version;
        $this->version = $version;
        $this->name = $name;
        $this->db = Zend_Registry::get('db');
        $this->dbLogger = Zend_Registry::get('dbLogger');
    }

    public function up()
    {
        // Override in subclass
    }

    public function down()
    {
        // override in subclass
    }

    private function drop()
    {
        foreach ($this->db->listTables() as $table) {
            $this->say("Dropping {$table}");
            $this->db->getConnection()->exec('DROP TABLE ' . $this->db->quoteIdentifier($table));
        }
    }

    /**
     * Run the migrations in this class. The migrate function is the main
     * entry point called from the migrator code.
     *
     * @param string $direction One of 'up', 'down', or 'drop'.
     * @access public
     */
    public function migrate($direction)
    {
        if (!is_callable(array($this, $direction))) return;

        switch ($direction) {
        case 'up':   $this->announce('migrating'); break;
        case 'down': $this->announce('reverting'); break;
        case 'drop': $this->announce('dropping tables'); break;
        }

        $start = microtime(true);
        call_user_func(array($this, $direction));
        $time = microtime(true) - $start;

        switch ($direction) {
        case 'up':   $this->announce('migrated (%.4f s.)', $time); break;
        case 'down': $this->announce('reverted (%.4f s.)', $time); break;
        case 'drop': $this->announce('dropped tables (%.4f s.)', $time); break;
        }

        $this->write();
    }

    /**
     * Factory method that runs a given migration.
     *
     * @param string $fileName
     * @param string $direction
     * @access public
     */
    static public function run($fileName, $direction, $verbose)
    {
        if ($direction == 'drop') {
            $dropper = new self('', __CLASS__);
            $dropper->migrate('drop');
            return;
        }

        $inflector = new Zend_Filter_Inflector(':class', array(':class' => 'Word_DashToCamelCase'));
        preg_match('/(\d+)_(.+)\.php/', basename($fileName), $matches);
        $version = $matches[1];
        $class = $inflector->filter(array(':class' => $matches[2]));

        include $fileName;

        if (class_exists($class)) {
            $migration = new $class($version, $class, $verbose);
            $migration->migrate($direction);
            $migration->updateInstalledVersions($version, $direction);
        } else {
            throw new Exception("Class '{$class}' is not present in the migration file");
        }
    }

    // Info helpers =========================================================

    protected function announce()
    {
        $args = func_get_args();
        $format = array_shift($args);
        $text = vsprintf($format, $args);

        $this->write(str_pad("=={$this->version} {$this->name}: {$text} ", 78, '='));
    }

    protected function say($message, $subitem = false)
    {
        $this->write(($subitem ? '   -> ' : '-- ') . $message);
    }

    protected function sayWithTime($message, $start, $rows = null)
    {
        $this->say($message);
        $this->say(sprintf('%.4f s.', microtime(true) - $start), true);
        if (is_int($rows) && !empty($rows)) $this->say(sprintf('%d rows', $rows), true);

    }

    protected function write($text = '')
    {
        if ($this->verbose) echo "$text\n";
        if (!empty($text)) $this->dbLogger->info($text);
    }

    // Database manipulators ================================================

    // The idea here is to implement a database independent micro language
    // to describe the migrations, like Rails does. But for now just use
    // SQL statements directly in the up and down methods.

    protected function createTable($name, $options = array())
    {
        return new Svenax_Migration_TableProxy($this, $name, $options);
    }

    protected function dropTable($name)
    {
        // TODO: Implement this.
    }

    protected function addField($table, $name, $options = array())
    {
        // TODO: Implement this.
    }

    protected function dropField($table, $name)
    {
        // TODO: Implement this.
    }

    protected function renameField($table, $oldName, $newName)
    {
        // TODO: Implement this.
    }

    protected function dropTimestamps($table, $options)
    {
        extract(array_merge(
            array('created_at' => true, 'updated_at' => true),
            $options
        ));

        $this->dropField($table, $created_at === true ? 'created_at' : $created_at);
        $this->dropField($table, $updated_at === true ? 'updated_at' : $updated_at);
    }

    // Migration helpers ====================================================

    /**
     * Use for data manipulation in the migrations.
     *
     * @param string $statement SQL statement
     * @return mixed
     * @access protected
     */
    protected function query($statement)
    {
        return $this->db->getConnection()->query($statement);
    }

    protected function queryWithMessage($message, $statement)
    {
        $start = microtime(true);
        $res = $this->query($statement);
        $this->sayWithTime($message, $start, $res->rowCount());

        return $res;
    }

    /**
     * Used for schema manipulation in the migrations
     *
     * @param string $statement SQL statement
     * @return mixed
     * @access protected
     */
    protected function exec($statement)
    {
        return $this->db->getConnection()->exec($statement);
    }

    protected function execWithMessage($message, $statement)
    {
        $start = microtime(true);
        $res = $this->exec($statement);
        $this->sayWithTime($message, $start);

        return $res;
    }

    /**
     * Update the schema_versions table with the given version.
     *
     * @param string $version
     * @param string $direction
     * @access private
     */
    private function updateInstalledVersions($version, $direction)
    {
        switch ($direction) {
        case 'up':
            $this->db->insert('schema_versions', array('version' => $version));
            break;
        case 'down':
            $this->db->delete('schema_versions', $this->db->quoteInto('version = ?', $version));
            break;
        }
    }
}
