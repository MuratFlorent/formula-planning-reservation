CREATE TABLE IF NOT EXISTS {prefix}fpr_events_periods (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    eventId mediumint(9) NOT NULL,
    periodStart datetime NOT NULL,
    periodEnd datetime NOT NULL,
    capacity int(11) NOT NULL DEFAULT 0,
    status varchar(50) NOT NULL DEFAULT 'active',
    created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY eventId (eventId)
) {charset_collate};