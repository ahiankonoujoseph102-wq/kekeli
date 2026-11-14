@include('errors.layout', ['code' => 429, 'title' => __('A little too fast'), 'message' => __('Kekeli received many requests in a short time. Wait a moment, then try again.'), 'action' => 'reload'])
