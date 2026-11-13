<?php

test('the application returns a successful response', function () {
    // Alamat utama mengarah ke halaman masuk; halaman akhirnya harus terbuka normal.
    $response = $this->followingRedirects()->get('/');

    $response->assertStatus(200);
});
