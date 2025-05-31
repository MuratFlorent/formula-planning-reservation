CREATE TABLE IF NOT EXISTS {prefix}fpr_payment_plans (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    name varchar(255) NOT NULL,
    frequency varchar(50) NOT NULL,
    term varchar(50) DEFAULT NULL,
    installments int(11) NOT NULL DEFAULT 1,
    description text,
    active tinyint(1) NOT NULL DEFAULT 1,
    is_default tinyint(1) NOT NULL DEFAULT 0,
    created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
    PRIMARY KEY (id)
) {charset_collate};
