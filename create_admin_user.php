<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Account;
use App\Models\Vault;
use App\Models\Contact;
use Illuminate\Support\Facades\Hash;

// Clean up existing user if it exists to allow re-running
$existingUser = User::where('email', 'admin@gmail.com')->first();
if ($existingUser) {
    $existingUser->delete();
}

$account = Account::factory()->create();

$user = User::create([
    'account_id' => $account->id,
    'first_name' => 'Admin',
    'last_name' => 'User',
    'email' => 'admin@gmail.com',
    'password' => Hash::make('123456'),
    'email_verified_at' => now(),
    'is_account_administrator' => true,
    'name_order' => '%first_name% %last_name%',
    'date_format' => 'MMM DD, YYYY',
    'default_map_site' => User::MAPS_SITE_GOOGLE_MAPS,
    'help_shown' => true,
    'timezone' => 'UTC',
]);

// Create default vault
$vault = Vault::factory()->create([
    'account_id' => $account->id,
    'description' => "Contacts for {$user->email}",
]);

$contact = Contact::factory()->create([
    'vault_id' => $vault->id,
]);

$vault->users()->save($user, [
    'permission' => Vault::PERMISSION_VIEW,
    'contact_id' => $contact->id,
]);


echo "Successfully created user admin@gmail.com / 123456!\n";
