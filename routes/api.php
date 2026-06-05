<?php

use App\Domains\Settings\ManageUsers\Api\Controllers\UserController;
use App\Domains\Vault\ManageVault\Api\Controllers\VaultController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the bootstrap/app.php file and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->name('api.')->group(function () {
    // users
    Route::get('user', [UserController::class, 'user']);
    Route::apiResource('users', UserController::class)->only(['index', 'show']);

    // vaults
    Route::apiResource('vaults', VaultController::class);

    // tags
    Route::get('tags', [\App\Http\Controllers\Api\TagApiController::class, 'index']);
    Route::post('tags', [\App\Http\Controllers\Api\TagApiController::class, 'store']);
    Route::put('tags/{id}', [\App\Http\Controllers\Api\TagApiController::class, 'update']);
    Route::delete('tags/{id}', [\App\Http\Controllers\Api\TagApiController::class, 'destroy']);

    // contacts
    Route::get('contacts', [\App\Http\Controllers\Api\ContactApiController::class, 'index']);
    Route::post('contacts/{id}/tags', [\App\Http\Controllers\Api\ContactApiController::class, 'attachTags']);
    Route::delete('contacts/{id}/tags/{tagId}', [\App\Http\Controllers\Api\ContactApiController::class, 'detachTag']);
});
