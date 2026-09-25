# How to repair missing tables and columns

Use `sql/init.sql` to restore missing schema before running migrations.

1. Back up the database:

   ```sh
   ept db-backup
   ```

2. Preview the repairs:

   ```sh
   php bin/heal-schema.php
   ```

   The command checks every table and column defined in `sql/init.sql`.
   It prints the proposed SQL without executing it.
   Existing columns retain their types, defaults, and values.
   Extra tables and columns remain unchanged.

3. Apply the repairs and resume migrations:

   ```sh
   ept migrate
   ```

   Both `ept migrate` and `ept post-update` run the repair before migrations.
   To apply only the repairs, run `ept heal-schema` or `php bin/heal-schema.php --apply`.

   New tables include the dump's indexes, foreign keys, and final `AUTO_INCREMENT` definitions.
   New tables are empty. The repair does not import seed rows or recover lost data.
   Restore lost rows from a backup if needed.

   When an old table or column still exists, the repair defers its migration destination.
   Run migrations to move that data before checking again.
   The command prints each deferred destination.

   Existing indexes remain unchanged unless a missing `AUTO_INCREMENT` column needs its index added alongside it.
   Conflicting keys and unsupported schema statements stop the repair with an error.
   Successful DDL persists after a failure. Resolve the reported problem and rerun the command.
   The repair does not advance `app_version`.

4. Verify the repair and migration status:

   ```sh
   php bin/heal-schema.php
   php bin/migrate.php --status
   ```

   Expect `0 repair(s) planned`, no deferred destinations, and the expected application version.
