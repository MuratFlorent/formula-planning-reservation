CREATE TABLE IF NOT EXISTS {prefix}fpr_subscription_courses (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    subscription_id mediumint(9) NOT NULL,
    course_id mediumint(9) NOT NULL,
    status varchar(50) NOT NULL DEFAULT 'active',
    created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY subscription_id (subscription_id),
    KEY course_id (course_id)
) {charset_collate};