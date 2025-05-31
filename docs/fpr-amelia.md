# FPR Amelia Module

## Overview

The FPR Amelia module provides Amelia-like functionality for the Formula Planning Reservation plugin. It allows you to manage events, customers, and bookings without requiring the Amelia plugin.

## Features

- Create and manage events
- Associate events with seasons (tags)
- Define event periods (specific dates and times)
- Register customers for events
- Track bookings and their statuses
- Admin interface for managing events and bookings

## Database Structure

The module creates the following tables in the WordPress database:

1. `{prefix}fpr_events` - Stores event information
2. `{prefix}fpr_events_tags` - Associates events with tags (seasons)
3. `{prefix}fpr_events_periods` - Stores specific dates and times for events
4. `{prefix}fpr_customers` - Stores customer information
5. `{prefix}fpr_customer_bookings` - Stores bookings (registrations) for customers

## Integration with WooCommerce

The module integrates with WooCommerce to automatically register customers for events when they purchase a course. When a WooCommerce order is processed or completed, the module:

1. Extracts customer information from the order
2. Identifies the selected season from the order meta data
3. For each course in the order, finds or creates the corresponding event and period
4. Registers the customer for the event

## Admin Interface

The module provides an admin interface for managing events and bookings. You can access it from the WordPress admin menu under "Settings > FPR Amelia".

### Events Tab

The Events tab displays a list of all events with their tags and statuses. You can edit events from this tab.

### Bookings Tab

The Bookings tab displays a list of all bookings with customer information, event details, and booking status. You can edit bookings from this tab.

## Usage

### Registering a Customer for a Course

You can programmatically register a customer for a course using the `register_customer_for_course` method:

```php
\FPR\Modules\FPRAmelia::register_customer_for_course(
    $first_name,
    $last_name,
    $email,
    $phone,
    $course_name,
    $formula,
    $order_id,
    $saison_tag
);
```

### Finding or Creating a Customer

You can find or create a customer using the `find_or_create_customer` method:

```php
$customer_id = \FPR\Modules\FPRAmelia::find_or_create_customer(
    $first_name,
    $last_name,
    $email,
    $phone
);
```

### Finding an Event by Name and Tag

You can find an event by name and tag using the `find_event_by_name_and_tag` method:

```php
$event = \FPR\Modules\FPRAmelia::find_event_by_name_and_tag(
    $name,
    $tag
);
```

## Differences from Amelia

While the FPR Amelia module provides similar functionality to the Amelia plugin, there are some key differences:

1. Simplified data model - The FPR Amelia module uses a simpler data model with fewer tables
2. No frontend booking - The FPR Amelia module does not provide frontend booking forms
3. No payment processing - The FPR Amelia module relies on WooCommerce for payment processing
4. No calendar view - The FPR Amelia module does not provide a calendar view of events

## Troubleshooting

If you encounter issues with the FPR Amelia module, check the following:

1. Make sure the database tables have been created correctly
2. Check the WordPress debug log for error messages
3. Verify that the module is being loaded correctly in the Init class
4. Ensure that the WooCommerce order meta data contains the correct season tag