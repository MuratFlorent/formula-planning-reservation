CREATE TABLE IF NOT EXISTS {prefix}fpr_courses (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    name varchar(255) NOT NULL,
    description text,
    duration varchar(50) NOT NULL,
    instructor varchar(255),
    day_of_week varchar(50),
    start_time time,
    end_time time,
    capacity int(11) NOT NULL DEFAULT 0,
    status varchar(50) NOT NULL DEFAULT 'active',
    created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) {charset_collate};