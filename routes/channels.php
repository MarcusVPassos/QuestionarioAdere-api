<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('usuarios', function ($user) {
    return true; // ou adicione lógica de permissão se necessário
});
