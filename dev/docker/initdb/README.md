# Drop a `.sql` file in this directory and the MariaDB container runs it ONCE,
# the first time the `db_data` volume is created.
#
# It is left empty on purpose. The production dump must never be committed -
# root .gitignore already ignores *.sql, so a dump placed here is not tracked,
# but prefer keeping it outside the repository entirely and importing it with:
#
#     dev/scripts/import-live-dump.sh /path/to/live_db.sql
#
# To re-trigger these init scripts you must delete the volume:
#
#     docker compose down -v
