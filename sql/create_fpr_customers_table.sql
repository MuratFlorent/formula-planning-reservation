CREATE TABLE IF NOT EXISTS {prefix}fpr_customers (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    user_id bigint(20),
    firstName varchar(255) NOT NULL,
    lastName varchar(255) NOT NULL,
    email varchar(255) NOT NULL,
    phone varchar(50),
    note text,
    status varchar(50) NOT NULL DEFAULT 'active',
    created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY user_id (user_id),
    UNIQUE KEY email (email)
) {charset_collate};