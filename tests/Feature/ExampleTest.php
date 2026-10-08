<?php

test('the home page sends visitors to the help center', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('help.index'));
});
