<?php
/**
 * Database migrations based on ideas from Ruby on Rails and Google Gears.
 *
 * This class contains the migrator interface used to perform the database
 * migrations. Use it like this:
 *
 *     $migration = new Svenax_Migration();
 *     $migration->migrate();  // Migrate to most recent version
 *     $migration->migrate('20080801000000');  // Migrate to a specific version
 *     $migration->migrate(0);  // Drop all tables and start afresh
 *
 * The code for actual migrations inherit from Svenax_Migration_Base
 */
class Svenax_Migration
{
    private $verbose = true;
    private $hiddenTables = array('schema_versions');
    private $db;

    public function __construct()
    {
        $this->db = Zend_Registry::get('db');
    }

    /**
     * Set to display verbose status information when migrating.
     *
     * @param bool $verbose True if we display status information, false if not
     * @access public
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
     * @param mixed $goToVersion null, 0, or a version string
     * @access public
     */
    public function migrate($goToVersion = null)
    {
        if ($goToVersion === 0) {
            Svenax_Migration_Base::run('', 'drop', $this->verbose);
            return;
        }

        $didMigrate = false;

        $this->createSchemaVersionsIfNotExists();

        $availableMigrations = $this->getAvailableMigrations(Zend_Registry::get('config')->db->migrations);
        $installedMigrations = $this->getInstalledMigrations();

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

        if ($didMigrate) $this->saveCurrentSchema();
    }

    /**
     * Save a dump of the database schema.
     *
     * @access public
     */
    public function saveCurrentSchema()
    {
        $sql = $this->schemaHead()
             . $this->schemaTables()
             . $this->schemaFoot();

        file_put_contents(dirname(Zend_Registry::get('config')->db->migrations) . '/schema_dump.sql', $sql);
    }

    // Migration helpers ====================================================

    /**
     * Return an array with paths to all available migrations.
     *
     * @param string $path Folder where the migration files are found
     * @return array Version => Full path
     * @access private
     */
    private function getAvailableMigrations($path)
    {
        $migrations = array();
        foreach (new DirectoryIterator($path) as $file) {
            if (preg_match('/(\d{14})_(.+)\.php$/', $file->getFilename(), $matches)) {
                $migrations[$matches[1]] = $file->getPathname();
            }
        }

        return $migrations;
    }

    /**
     * Return an array with the version numbers for all installed migrations.
     *
     * @return array
     * @access private
     */
    private function getInstalledMigrations()
    {
        return $this->db->fetchCol('SELECT version FROM schema_versions');
    }

    /**
     * Create the schema versions table if it does not already exist.
     *
     * @access private
     */
    private function createSchemaVersionsIfNotExists()
    {
        try {
            $this->db->describeTable('schema_versions');
        } catch (Exception $e) {
            $this->db->getConnection()->exec(
                'CREATE TABLE schema_versions (version CHAR(14) NOT NULL PRIMARY KEY)'
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