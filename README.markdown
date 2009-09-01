# Migrations #

## Theory ##

Migrations as a way to handle database updates were first put into common use
in _ActiveRecords_, the database layer for _Ruby on Rails_. The idea is
simple, all changes to the database are made through migration classes that
live in timestamped files so they can be run in order. Each migration class
contains an `up` and a `down` method to apply or remove a change respectively.

A migration can both make changes to the database structure and add or remove
table data. Since migrations will always run in a known order, they only need
to take into account the database structure set by the previous migration.
Each database instance contains a table (which is created automatically), that
keeps track of which migrations that have already been run.

When the migrator interface is called, this table is checked to see which
migrations should be applied and which should be run to downgrade the
database.

## Implementation ##

I have implemented a simple variant of migrations on top of the `Zend_Db`
classes. In the _ActiveRecords_ implementation, the database changes are
described using a platform independent micro language. I have instead used
direct SQL expressions for simplicity. A draft for how a similar structure
helper system could be constructed can be found in the TableProxy class.

But for now, migrations just call `query`, `exec`, `queryWithTime`,
`execWithTime` or `sayWithTime`. See examples in the `migrations` folder.

## Files ##

`lib/Migrations.php` contains the migrator class. It is used like this:

    $migration = new Svenax_Migration();
    $migration->migrate();  // Migrate to most recent version
    $migration->migrate('20080801000000');  // Migrate to a specific version
    $migration->migrate(0);  // Drop all tables and start afresh

All migrations are stored in separate classes in the `migrations` folder, the
path to which can be set in the application config file under _db.migrations_.

The migrations file names myst start with a 14-char timestamp, e.g.
`20090831162212`, followed by an underscore and a descriptive name that should
be the same as the migration class name. Migration classes all inherit from
`Svenax_Migration_Base` and must implement the two methods `up()` and
`down()`.

In `up()`, database changes are applied, in `down()`, changes are reverted. If
changes cannot be reverted, then `down()` should throw a
`Svenax_Migration_Exception`.