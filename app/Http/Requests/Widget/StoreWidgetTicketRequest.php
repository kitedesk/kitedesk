<?php

namespace App\Http\Requests\Widget;

use App\Http\Requests\Portal\StoreGuestTicketRequest;

/**
 * A request sent from the website widget: the guest form's fields, with a plain-text message.
 */
class StoreWidgetTicketRequest extends StoreGuestTicketRequest {}
