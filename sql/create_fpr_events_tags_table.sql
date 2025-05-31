CREATE TABLE IF NOT EXISTS {prefix}fpr_events_tags (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    eventId mediumint(9) NOT NULL,
    name varchar(255) NOT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
    PRIMARY KEY (id),
    KEY eventId (eventId)
) {charset_collate};