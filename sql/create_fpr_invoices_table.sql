CREATE TABLE IF NOT EXISTS {prefix}fpr_invoices (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    user_id bigint(20) NOT NULL,
    subscription_id mediumint(9) NOT NULL,
    order_id bigint(20) NOT NULL,
    invoice_number varchar(50) NOT NULL,
    invoice_date datetime NOT NULL,
    due_date datetime NOT NULL,
    amount decimal(10,2) NOT NULL,
    status varchar(50) NOT NULL DEFAULT 'paid',
    pdf_path varchar(255),
    created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY subscription_id (subscription_id),
    KEY order_id (order_id),
    KEY invoice_number (invoice_number)
) {charset_collate};