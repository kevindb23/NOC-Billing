<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Encryption\DecryptException;

return new class extends Migration {
    public function up(): void
    {
        DB::table('bng_radius_servers')
            ->select(['id', 'secret', 'database_password'])
            ->orderBy('id')
            ->get()
            ->each(function (object $server): void {
                $updates = [];

                foreach (['secret', 'database_password'] as $column) {
                    $value = $server->{$column};
                    if (blank($value)) {
                        continue;
                    }

                    try {
                        Crypt::decryptString($value);
                    } catch (DecryptException) {
                        $updates[$column] = Crypt::encryptString($value);
                    }
                }

                if ($updates) {
                    DB::table('bng_radius_servers')->where('id', $server->id)->update($updates);
                }
            });
    }

    public function down(): void
    {
        // Credentials remain encrypted intentionally; reverting would make them plaintext again.
    }
};
