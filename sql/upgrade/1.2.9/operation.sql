SELECT "Adding new operations" AS "";

INSERT IGNORE INTO operation( type, subject, name, restricted, description ) VALUES
( "push", "proxy_form", "new", true, "Adds a new proxy form directly into the data entry system." ),
( "pull", "hin_form", "download", true, "Downloads a participant's scanned HIN form." ),
( "push", "hin_form", "adjudicate", true, "Adjudicates conflicts between two entries for a HIN form." ),
( "push", "hin_form", "edit", true, "Edits the details of a scanned HIN form." ),
( "widget", "hin_form", "list", true, "Lists scanned HIN forms." ),
( "widget", "hin_form", "view", true, "View the details of a scanned HIN form." ),
( "push", "hin_form_entry", "defer", true, "Defers entering values for an HIN form." ),
( "push", "hin_form_entry", "edit", true, "Edits the details of entry values for a HIN form." ),
( "widget", "hin_form_entry", "list", true, "Lists entries for scanned HIN forms." ),
( "push", "hin_form_entry", "new", true, "Create new entry values for a HIN form." ),
( "pull", "hin_form_entry", "validate", true, "Validates the entry values for a HIN form." ),
( "widget", "hin_form_entry", "view", true, "View the details of entry values for a HIN form." );
