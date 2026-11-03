{!! page('error', ['code' => 403, 'heading' => 'Not allowed', 'message' => 403 == 419 ? 'Your session expired. Go back, refresh the page and try again.' : ''])->getContent() !!}
