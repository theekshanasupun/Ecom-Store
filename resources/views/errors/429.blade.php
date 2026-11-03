{!! page('error', ['code' => 429, 'heading' => 'Slow down', 'message' => 429 == 419 ? 'Your session expired. Go back, refresh the page and try again.' : ''])->getContent() !!}
