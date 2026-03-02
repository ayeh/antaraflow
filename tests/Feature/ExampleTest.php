<?php

test('root redirects unauthenticated users to login', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('login'));
});
