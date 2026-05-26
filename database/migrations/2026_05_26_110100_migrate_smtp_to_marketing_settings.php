<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('liefertool_settings')) {
            return;
        }

        DB::table('liefertool_settings')->orderBy('id')->chunkById(500, function ($rows): void {
            foreach ($rows as $row) {
                $hasMailData = ! empty($row->from_email)
                    || ! empty($row->reply_email)
                    || ! empty($row->smtp_host)
                    || ! empty($row->smtp_username)
                    || ! empty($row->smtp_password);

                if (! $hasMailData) {
                    continue;
                }

                DB::table('marketing_settings')->updateOrInsert(
                    ['tenant_id' => $row->tenant_id],
                    [
                        'from_email' => $row->from_email,
                        'reply_email' => $row->reply_email,
                        'smtp_host' => $row->smtp_host,
                        'smtp_port' => $row->smtp_port,
                        'smtp_encryption' => $row->smtp_encryption,
                        'smtp_username' => $row->smtp_username,
                        'smtp_password' => $row->smtp_password,
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => $row->updated_at ?? now(),
                    ],
                );
            }
        });

        Schema::table('liefertool_settings', function (Blueprint $table): void {
            $columns = [
                'smtp_host',
                'smtp_port',
                'smtp_encryption',
                'smtp_username',
                'smtp_password',
                'from_email',
                'reply_email',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('liefertool_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('liefertool_settings')) {
            return;
        }

        Schema::table('liefertool_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('liefertool_settings', 'from_email')) {
                $table->string('from_email')->nullable();
            }
            if (! Schema::hasColumn('liefertool_settings', 'reply_email')) {
                $table->string('reply_email')->nullable();
            }
            if (! Schema::hasColumn('liefertool_settings', 'smtp_host')) {
                $table->string('smtp_host')->nullable();
            }
            if (! Schema::hasColumn('liefertool_settings', 'smtp_port')) {
                $table->unsignedSmallInteger('smtp_port')->nullable();
            }
            if (! Schema::hasColumn('liefertool_settings', 'smtp_encryption')) {
                $table->string('smtp_encryption', 16)->nullable();
            }
            if (! Schema::hasColumn('liefertool_settings', 'smtp_username')) {
                $table->string('smtp_username')->nullable();
            }
            if (! Schema::hasColumn('liefertool_settings', 'smtp_password')) {
                $table->string('smtp_password')->nullable();
            }
        });

        if (Schema::hasTable('marketing_settings')) {
            DB::table('marketing_settings')->orderBy('id')->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('liefertool_settings')
                        ->where('tenant_id', $row->tenant_id)
                        ->update([
                            'from_email' => $row->from_email,
                            'reply_email' => $row->reply_email,
                            'smtp_host' => $row->smtp_host,
                            'smtp_port' => $row->smtp_port,
                            'smtp_encryption' => $row->smtp_encryption,
                            'smtp_username' => $row->smtp_username,
                            'smtp_password' => $row->smtp_password,
                        ]);
                }
            });
        }
    }
};
