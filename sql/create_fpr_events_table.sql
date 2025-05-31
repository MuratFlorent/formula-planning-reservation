CREATE TABLE IF NOT EXISTS {prefix}fpr_events (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    name varchar(255) NOT NULL,
    description text,
    color varchar(50),
    status varchar(50) NOT NULL DEFAULT 'active',
    created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) {charset_collate};