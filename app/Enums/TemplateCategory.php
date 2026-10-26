<?php

namespace App\Enums;

/**
 * Meta's categories for a template, which set its price and how it is
 * judged. Authentication (one-time codes) has its own fixed format and is
 * left out until someone needs it.
 */
enum TemplateCategory: string
{
    /** Offers, news, reminders to come back: priced highest, and never sent to a contact who stopped broadcasts. */
    case Marketing = 'marketing';

    /** About something the customer already asked for or bought: an order, an appointment, a payment. */
    case Utility = 'utility';
}
