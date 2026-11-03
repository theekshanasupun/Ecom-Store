{!! page('error', ['code' => 405, 'heading' => 'Not allowed', 'message' => 405 == 419 ? 'Your session expired. Go back, refresh the page and try again.' : ''])->getContent() !!}
