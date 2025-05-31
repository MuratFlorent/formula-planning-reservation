CREATE TABLE IF NOT EXISTS {prefix}fpr_users (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    wp_user_id bigint(20),
    amelia_customer_id bigint(20),
    email varchar(255) NOT NULL,
    firstName varchar(255) NOT NULL,
    lastName varchar(255) NOT NULL,
    phone varchar(50),
    status varchar(50) NOT NULL DEFAULT 'active',
    created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY wp_user_id (wp_user_id),
    KEY amelia_customer_id (amelia_customer_id),
    UNIQUE KEY email (email)
) {charset_collate};