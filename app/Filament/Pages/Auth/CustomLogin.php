<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;

class CustomLogin extends BaseLogin
{
    protected string $view = 'filament.pages.auth.login';
    protected static string $layout = 'filament.pages.auth.layout';

    public function authenticate(): ?LoginResponse
    {
        $response = parent::authenticate();

        if ($response) {
            $this->redirect(filament()->getUrl(), navigate: false);
            return null;
        }

        return $response;
    }
}