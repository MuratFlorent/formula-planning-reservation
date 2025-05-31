CREATE TABLE IF NOT EXISTS {prefix}fpr_customer_bookings (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    customerId mediumint(9) NOT NULL,
    eventPeriodId mediumint(9) NOT NULL,
    status varchar(50) NOT NULL DEFAULT 'pending',
    price decimal(10,2) NOT NULL DEFAULT 0.00,
    persons int(11) NOT NULL DEFAULT 1,
    formula varchar(255),
    order_id bigint(20),
    created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY customerId (customerId),
    KEY eventPeriodId (eventPeriodId),
    KEY order_id (order_id)
) {charset_collate};