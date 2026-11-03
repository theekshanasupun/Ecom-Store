{!! page('error', ['code' => 404, 'heading' => 'Page not found', 'message' => 404 == 419 ? 'Your session expired. Go back, refresh the page and try again.' : ''])->getContent() !!}
