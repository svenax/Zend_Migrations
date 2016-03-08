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
 * This class contains the migrator interface used to perform the database
 * migrations. Use it like this:
 *
 *     <pre>
 *     $migration = new Svenax_Migration();
 *     $migration->migrate();  // Migrate to most recent version
 *     $migration->migrate('20080801000000');  // Migrate to a specific version
 *     $migration->migrate(0);  // Drop all tables and start afresh
 *     </pre>
 *
 * The code for actual migrations inherit from Svenax_Migration_Base
 */
class Svenax_Migration
{
    private $verbose = true;
    private $hiddenTables = array('_schema_versions');
    private $migrationsPath;
    private $db;

    public function __construct()
    {
        $this->db = Zend_Registry::get('db');
        $this->migrationsPath = Zend_Registry::get('migrationsPath');
    }

    /**
     * Set to display verbose status information when migrating.
     *
     * @param bool $verbose True if we display status information, false if not
     */
    public function setVerbose($verbose = true)
    {
        $this->verbose = $verbose;
    }

    /**
     * Migrate the database to a given version.
     *
     * The version argument can be one of
     * - null (default): Migrate to the most recent version
     * - string: Migrate to a specific version
     * - 0: Drop all tables and start afresh
     *
     * Try to catch errors inside a transaction, but note that CREATE TABLE
     * et al can not be rolled back.
     *
     * @param mixed $goToVersion null, 0, or a version string
     */
    public function migrate($goToVersion = null)
    {
        if ($goToVersion === 0) {
            Svenax_Migration_Base::run('', 'drop', $this->verbose);
            return;
        }

        $didMigrate = false;

        $this->createSchemaVersionsIfNotExists();

        $availableMigrations = $this->getAvailableMigrations($this->migrationsPath);
        $installedMigrations = $this->getInstalledMigrations();

        try {
            $this->db->beginTransaction();
            if ($goToVersion !== null) {
                // Any down migrations should be executed in reverse order
                krsort($availableMigrations);
                foreach ($availableMigrations as $version => $migration) {
                    if ($version <= $goToVersion) break;
                    if (!in_array($version, $installedMigrations)) continue;
                    Svenax_Migration_Base::run($migration, 'down', $this->verbose);
                    $didMigrate = true;
                }
            }

            ksort($availableMigrations);
            foreach ($availableMigrations as $version => $migration) {
                if ($goToVersion !== null && $version > $goToVersion) break;
                if (in_array($version, $installedMigrations)) continue;
                Svenax_Migration_Base::run($migration, 'up', $this->verbose);
                $didMigrate = true;
            }
            $this->db->commit();
        } catch (Svenax_Migration_Exception $e) {
            Svenax_Migration_Base::abortMessage();
            $didMigrate = false;
            $this->db->rollback();
        }

        if ($didMigrate) $this->saveCurrentSchema();
    }

    /**
     * Get a list of migrations that have not yet been installed.
     *
     * @return array Migrations
     */
    public function getMissingMigrations()
    {
        $this->createSchemaVersionsIfNotExists();

        $availableMigrations = $this->getAvailableMigrations($this->migrationsPath);
        $installedMigrations = $this->getInstalledMigrations(true);

        $missingMigrations = array_diff_key($availableMigrations, $installedMigrations);
        array_walk($missingMigrations, create_function('&$v', '$v = basename($v, ".php");'));
        ksort($missingMigrations);

        return $missingMigrations;
    }

    /**
     * Save a dump of the database schema.
     *
     */
    public function saveCurrentSchema()
    {
        $sql = $this->schemaHead()
             . $this->schemaTables()
             . $this->schemaFoot();

        file_put_contents(dirname($this->migrationsPath) . '/schema_dump.sql', $sql);
    }

    // Migration helpers ====================================================

    /**
     * Return an array with paths to all available migrations.
     *
     * @param  string  $path Folder where the migration files are found
     * @return array   Version => Full path
     * @access private
     */
    private function getAvailableMigrations($path)
    {
        $migrations = array();
        foreach (new DirectoryIterator($path) as $file) {
            if (preg_match('/^(\d{14})_(.+)\.php$/', $file->getFilename(), $matches)) {
                $migrations[$matches[1]] = $file->getPathname();
            }
        }

        return $migrations;
    }

    /**
     * Return an array with the version numbers for all installed migrations.
     *
     * @param  bool    $flipResultArray Flip values and keys in the return value
     * @return array
     * @access private
     */
    private function getInstalledMigrations($flipResultArray = false)
    {
        $ret = $this->db->fetchCol('SELECT version FROM _schema_versions');

        return $flipResultArray ? array_flip($ret) : $ret;
    }

    /**
     * Create the schema versions table if it does not already exist.
     *
     * @access private
     */
    private function createSchemaVersionsIfNotExists()
    {
        try {
            $this->db->describeTable('_schema_versions');
        } catch (Exception $e) {
            $this->db->getConnection()->query(
                'CREATE TABLE _schema_versions (version CHAR(14) NOT NULL PRIMARY KEY)'
            );
        }
    }

    // Schema dump helpers ==================================================

    /**
     * Return a header comment for the generated schema dump.
     *
     * @return string
     * @access private
     */
    private function schemaHead()
    {
        $dbconfig = $this->db->getConfig();
        $dumpDate = date(DATE_ATOM);
        return <<<TEXT
-- Autogenerated dump of database schema for
-- Database:   {$dbconfig['dbname']}
-- Created at: {$dumpDate}


TEXT;
    }

    /**
     * Return the SQL code to create all tables anew.
     *
     * @return string
     * @access private
     */
    private function schemaTables()
    {
        $sql = '';
        foreach ($this->db->listTables() as $table) {
            if (in_array($table, $this->hiddenTables)) continue;
            $res = $this->db->fetchRow('SHOW CREATE TABLE ' . $this->db->quoteIdentifier($table));
            $sql .= $res['Create Table'] . "\n\n";
        }

        return $sql;
    }

    /**
     * Return any footer text for the generated schema dump.
     *
     * @return string
     * @access private
     */
    private function schemaFoot()
    {
        return '';
    }
}
