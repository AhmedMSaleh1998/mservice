<?php

namespace Modules\Notifications\Resources;

use Illuminate\Http\Request;
use Modules\Core\CustomResource;

class UserNotificationResource extends CustomResource
{
    public function data(Request $request): array
    {
        $notification = $this->resource->adminNotification;

        return [
            // The same id the push payload carries as notification_id.
            'id' => $this->resource->admin_notification_id,
            'title' => $notification?->title,
            'body' => $notification?->body,
            'is_read' => $this->resource->read_at !== null,
            'read_at' => optional($this->resource->read_at)->format('Y-m-d H:i:s'),
            'created_at' => optional($this->resource->created_at)->format('Y-m-d H:i:s'),
        ];
    }
}
