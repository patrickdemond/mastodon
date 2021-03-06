DROP PROCEDURE IF EXISTS patch_ip_consent_form_entry;
DELIMITER //
CREATE PROCEDURE patch_ip_consent_form_entry()
  BEGIN

    -- determine the @cenozo database name
    SET @cenozo = (
      SELECT unique_constraint_schema
      FROM information_schema.referential_constraints
      WHERE constraint_schema = DATABASE()
      AND constraint_name = "fk_access_site_id"
    );

    SELECT "Creating new ip_consent_form_entry table" AS "";

    SET @sql = CONCAT(
      "CREATE TABLE IF NOT EXISTS ip_consent_form_entry ( ",
        "id INT UNSIGNED NOT NULL AUTO_INCREMENT, ",
        "update_timestamp TIMESTAMP NOT NULL, ",
        "create_timestamp TIMESTAMP NOT NULL, ",
        "ip_consent_form_id INT UNSIGNED NOT NULL, ",
        "user_id INT(10) UNSIGNED NOT NULL, ",
        "submitted TINYINT(1) NOT NULL DEFAULT 0, ",
        "participant_id INT(10) UNSIGNED NULL DEFAULT NULL, ",
        "accept TINYINT(1) NOT NULL DEFAULT 0, ",
        "alternate_id INT(10) UNSIGNED NULL DEFAULT NULL, ",
        "signed TINYINT(1) NOT NULL DEFAULT 0, ",
        "date DATE NULL DEFAULT NULL, ",
        "PRIMARY KEY (id), ",
        "INDEX fk_ip_consent_form_id (ip_consent_form_id ASC), ",
        "INDEX fk_user_id (user_id ASC), ",
        "UNIQUE INDEX uq_ip_consent_form_id_user_id (ip_consent_form_id ASC, user_id ASC), ",
        "INDEX fk_alternate_id (alternate_id ASC), ",
        "INDEX fk_participant_id (participant_id ASC), ",
        "CONSTRAINT fk_ip_consent_form_entry_ip_consent_form_id ",
          "FOREIGN KEY (ip_consent_form_id) ",
          "REFERENCES ip_consent_form (id) ",
          "ON DELETE NO ACTION ",
          "ON UPDATE NO ACTION, ",
        "CONSTRAINT fk_ip_consent_form_entry_user_id ",
          "FOREIGN KEY (user_id) ",
          "REFERENCES ", @cenozo, ".user (id) ",
          "ON DELETE NO ACTION ",
          "ON UPDATE NO ACTION, ",
        "CONSTRAINT fk_ip_consent_form_entry_alternate_id ",
          "FOREIGN KEY (alternate_id) ",
          "REFERENCES ", @cenozo, ".alternate (id) ",
          "ON DELETE NO ACTION ",
          "ON UPDATE NO ACTION, ",
        "CONSTRAINT fk_ip_consent_form_entry_participant_id ",
          "FOREIGN KEY (participant_id) ",
          "REFERENCES ", @cenozo, ".participant (id) ",
          "ON DELETE NO ACTION ",
          "ON UPDATE NO ACTION) ",
      "ENGINE = InnoDB"
    );
    PREPARE statement FROM @sql;
    EXECUTE statement;
    DEALLOCATE PREPARE statement;

  END //
DELIMITER ;

CALL patch_ip_consent_form_entry();
DROP PROCEDURE IF EXISTS patch_ip_consent_form_entry;

DELIMITER $$

DROP TRIGGER IF EXISTS ip_consent_form_entry_AFTER_INSERT$$
CREATE DEFINER = CURRENT_USER TRIGGER ip_consent_form_entry_AFTER_INSERT AFTER INSERT ON ip_consent_form_entry FOR EACH ROW
BEGIN
  CALL update_ip_consent_form_total( NEW.ip_consent_form_id );
END$$

DROP TRIGGER IF EXISTS ip_consent_form_entry_AFTER_UPDATE$$
CREATE DEFINER = CURRENT_USER TRIGGER ip_consent_form_entry_AFTER_UPDATE AFTER UPDATE ON ip_consent_form_entry FOR EACH ROW
BEGIN
  CALL update_ip_consent_form_total( NEW.ip_consent_form_id );
END$$

DROP TRIGGER IF EXISTS ip_consent_form_entry_AFTER_DELETE$$
CREATE DEFINER = CURRENT_USER TRIGGER ip_consent_form_entry_AFTER_DELETE AFTER DELETE ON ip_consent_form_entry FOR EACH ROW
BEGIN
  CALL update_ip_consent_form_total( OLD.ip_consent_form_id );
END$$

DELIMITER ;
