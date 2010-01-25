<?php
/**
 * Database migrations based on ideas from Ruby on Rails and Google Gears.
 *
 * @category  Svenax
 * @package   Migration
 * @author    Sven Axelsson <sven@axelsson.name>
 */

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
    protected $dbLog;

    /**
     * undocumented function
     *
     * @param string $version
     * @param string $name
     * @param bool   $verbose True if we print status text, false if not
     */
    public function __construct($version, $name, $verbose = true)
    {
        $this->verbose = $verbose;
        if (is_string($version) && $version != '') $version = ' ' . $version;
        $this->version = $version;
        $this->name = $name;
        $this->db = Zend_Registry::get('db');
        $this->dbLog = Zend_Registry::get('dbLog');
    }

    public function up()
    {
        throw new Svenax_Migration_Exception('Method up must be implemented in subclass.');
    }

    public function down()
    {
        throw new Svenax_Migration_Exception('Method down must be implemented in subclass.');
    }

    private function drop()
    {
        foreach ($this->db->listTables() as $table) {
            $this->say("Dropping {$table}");
            $this->exec('DROP TABLE ' . $this->db->quoteIdentifier($table));
        }
    }

    /**
     * Run the migrations in this class. The migrate function is the main
     * entry point called from the migrator code.
     *
     * @param string $direction One of 'up', 'down', or 'drop'.
     */
    public function migrate($direction)
    {
        if (!is_callable(array($this, $direction))) {
            throw new Svenax_Migration_Exception("Can not call method '{$direction}'");
        }

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
     */
    public static function run($fileName, $direction, $verbose)
    {
        if ($direction == 'drop') {
            // We create a fake instance here since drop is not associated
            // with any real migration class.
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
            $msg = "Class '{$class}' is not present in the migration file";
            $this->write($msg);
            throw new Svenax_Migration_Exception($msg);
        }
    }

    /**
     * Call this method to inform the user that the migration has been aborted.
     */
    public static function abortMessage()
    {
        $aborter = new self('', 'ERROR');
        $aborter->announce('Migration aborted and changes rolled back');
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
        if (!empty($text)) $this->dbLog->info($text);
    }

    // Migration helpers ====================================================

    /**
     * Use for data manipulation in the migrations.
     *
     * @param  string $statement SQL statement
     * @return mixed
     */
    protected function query($statement)
    {
        $con = $this->db->getConnection();
        $res = $con->query($this->preprocess($statement));
        if ($res === false) {
            $this->write("SQL Error ({$con->errno}): {$con->error}");
            $this->write("When executing query\n{$statement}");
            throw new Svenax_Migration_Exception($con->error);
        }
        return $res;
    }

    protected function queryWithMessage($message, $statement)
    {
        $start = microtime(true);
        $res = $this->query($statement);
        $this->sayWithTime($message, $start, is_object($res) ? $res->num_rows() : null);

        return $res;
    }

    /**
     * Used for schema manipulation in the migrations
     *
     * @param  string $statement SQL statement
     * @return mixed
     */
    protected function exec($statement)
    {
        return $this->query($statement);
    }

    protected function execWithMessage($message, $statement)
    {
        $start = microtime(true);
        $res = $this->exec($statement);
        $this->sayWithTime($message, $start);

        return $res;
    }

    /**
     * Keep some standard types through simple string replacement. This should
     * be implemented in a smarter way.
     *
     * @param  string $statement SQL statement
     * @return string Altered SQL statement
     */
    private function preprocess($statement)
    {
        $repl = array(
            '!primary' => 'INT(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT',
            '!int' => 'INT(11)',
            '!uint' => 'INT(11) UNSIGNED',
            '!string' => 'VARCHAR(255)',
            '!timestamps' => 'updated_at TIMESTAMP NULL, created_at TIMESTAMP NULL',
            // The first timestamp field will be auto-updated
            '!autotimestamps' => 'updated_at TIMESTAMP, created_at TIMESTAMP',
        );

        return str_ireplace(array_keys($repl), array_values($repl), $statement);
    }

    /**
     * Update the schema_versions table with the given version.
     *
     * @param  string  $version
     * @param  string  $direction
     * @access private
     */
    private function updateInstalledVersions($version, $direction)
    {
        switch ($direction) {
        case 'up':
            $this->db->insert('_schema_versions', array('version' => $version));
            break;
        case 'down':
            $this->db->delete('_schema_versions', $this->db->quoteInto('version = ?', $version));
            break;
        }
    }
}
