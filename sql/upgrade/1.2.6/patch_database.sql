-- Patch to upgrade database to version 1.2.6

SET AUTOCOMMIT=0;

SOURCE operation.sql
SOURCE role_has_operation.sql

COMMIT;