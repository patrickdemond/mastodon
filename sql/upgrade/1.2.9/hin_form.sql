DROP PROCEDURE IF EXISTS patch_hin_form;
DELIMITER //
CREATE PROCEDURE patch_hin_form()
  BEGIN

    -- determine the @cenozo database name
    SET @cenozo = (
      SELECT unique_constraint_schema
      FROM information_schema.referential_constraints
      WHERE constraint_schema = DATABASE()
      AND constraint_name = "fk_role_has_operation_role_id" );

    SELECT "Adding new hin_form table" AS "";

    SET @test = (
      SELECT COUNT(*)
      FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = "hin_form" );
    IF @test = 0 THEN
      SET @sql = CONCAT(
        "CREATE TABLE IF NOT EXISTS hin_form ( ",
          "id INT UNSIGNED NOT NULL AUTO_INCREMENT, ",
          "update_timestamp TIMESTAMP NOT NULL, ",
          "create_timestamp TIMESTAMP NOT NULL, ",
          "complete TINYINT(1) NOT NULL DEFAULT 0, ",
          "invalid TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'If true then the form cannot be processed.', ",
          "participant_id INT UNSIGNED NULL DEFAULT NULL, ",
          "validated_hin_form_entry_id INT UNSIGNED NULL DEFAULT NULL COMMENT 'The entry data which has been validated and accepted.', ",
          "date DATE NOT NULL, ",
          "PRIMARY KEY (id), ",
          "INDEX fk_validated_hin_form_entry_id (validated_hin_form_entry_id ASC), ",
          "INDEX fk_participant_id (participant_id ASC), ",
          "CONSTRAINT fk_hin_form_validated_hin_form_entry_id ",
            "FOREIGN KEY (validated_hin_form_entry_id) ",
            "REFERENCES hin_form_entry (id) ",
            "ON DELETE NO ACTION ",
            "ON UPDATE NO ACTION, ",
          "CONSTRAINT fk_hin_form_participant_id ",
            "FOREIGN KEY (participant_id) ",
            "REFERENCES ", @cenozo, ".participant (id) ",
            "ON DELETE NO ACTION ",
            "ON UPDATE NO ACTION) ",
        "ENGINE = InnoDB" );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;
    END IF;

  END //
DELIMITER ;

CALL patch_hin_form();
DROP PROCEDURE IF EXISTS patch_hin_form;
