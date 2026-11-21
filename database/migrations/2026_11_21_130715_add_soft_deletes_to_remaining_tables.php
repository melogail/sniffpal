<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every remaining model soft deletes. `pets`, `conversations` and
     * `messages` already did; `likes`, `saves` and `conversation_user` are
     * toggles and a pivot with a unique (user, target) index, where a trashed
     * row would block the next toggle, so they stay hard.
     *
     * @var list<string>
     */
    private array $tables = [
        'admins', 'breeds', 'categories', 'comments', 'contact_messages',
        'reports', 'reviews', 'ticket_messages', 'tickets', 'users',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropSoftDeletes();
            });
        }
    }
};
