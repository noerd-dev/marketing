<?php

use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth', 'verified', 'web']], function (): void {
    Route::livewire('communications', 'marketing::communications-list')->name('communications');
    Route::livewire('communication/{modelId}', 'marketing::communication-detail')->name('communication.detail');
    Route::livewire('marketing-settings', 'marketing::marketing-settings-detail')->name('marketing.settings');

    Route::redirect('/sent-mails', '/communications', 301);
    Route::get('/sent-mail/{mail}', fn ($mail) => redirect("/communication/{$mail}", 301));
});
