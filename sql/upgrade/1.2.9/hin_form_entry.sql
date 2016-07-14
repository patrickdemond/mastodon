DROP PROCEDURE IF EXISTS patch_hin_form_entry;
DELIMITER //
CREATE PROCEDURE patch_hin_form_entry()
  BEGIN

    -- determine the @cenozo database name
    SET @cenozo = (
      SELECT unique_constraint_schema
      FROM information_schema.referential_constraints
      WHERE constraint_schema = DATABASE()
      AND constraint_name = "fk_role_has_operation_role_id" );

    SELECT "Adding new hin_form_entry table" AS "";

    SET @test = (
      SELECT COUNT(*)
      FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = "hin_form_entry" );
    IF @test = 0 THEN
      SET @sql = CONCAT(
        "CREATE TABLE IF NOT EXISTS hin_form_entry ( ",
          "id INT UNSIGNED NOT NULL AUTO_INCREMENT, ",
          "update_timestamp TIMESTAMP NOT NULL, ",
          "create_timestamp TIMESTAMP NOT NULL, ",
          "hin_form_id INT UNSIGNED NOT NULL, ",
          "user_id INT UNSIGNED NOT NULL, ",
          "deferred TINYINT(1) NOT NULL DEFAULT 1, ",
          "uid VARCHAR(10) NULL DEFAULT NULL, ",
          "accept TINYINT(1) NOT NULL DEFAULT 0, ",
          "signed TINYINT(1) NOT NULL DEFAULT 0, ",
          "date DATE NULL DEFAULT NULL, ",
          "PRIMARY KEY (id), ",
          "INDEX fk_hin_form_id (hin_form_id ASC), ",
          "INDEX fk_user_id (user_id ASC), ",
          "UNIQUE INDEX uq_hin_form_id_user_id (hin_form_id ASC, user_id ASC), ",
          "INDEX dk_uid (uid ASC), ",
          "CONSTRAINT fk_hin_form_entry_hin_form_id ",
            "FOREIGN KEY (hin_form_id) ",
            "REFERENCES hin_form (id) ",
            "ON DELETE NO ACTION ",
            "ON UPDATE NO ACTION, ",
          "CONSTRAINT fk_hin_form_entry_user_id ",
            "FOREIGN KEY (user_id) ",
            "REFERENCES ", @cenozo, ".user (id) ",
            "ON DELETE NO ACTION ",
            "ON UPDATE NO ACTION) ",
        "ENGINE = InnoDB" );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;
    END IF;

  END //
DELIMITER ;

CALL patch_hin_form_entry();
DROP PROCEDURE IF EXISTS patch_hin_form_entry;
