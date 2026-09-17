<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SetUserRole extends Command
{
    protected $signature = 'users:set-role {email : Email akun yang sudah ada} {role : operator atau viewer}';

    protected $description = 'Tetapkan peran akun yang sudah ada melalui administrator CLI';

    public function handle(): int
    {
        $role = $this->argument('role');

        if (! in_array($role, ['operator', 'viewer'], true)) {
            $this->error('Peran harus operator atau viewer.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('Akun tidak ditemukan. Tidak ada akun yang dibuat.');

            return self::FAILURE;
        }

        $user->role = $role;
        $user->save();

        $label = $user->isOperator() ? 'Operator' : 'Pemantau';
        $this->info("Peran akun berhasil diubah menjadi {$label}.");

        return self::SUCCESS;
    }
}
