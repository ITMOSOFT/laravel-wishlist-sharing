<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('itmosoft-wishlist-sharing.table_prefix', 'itmosoft_');

        Schema::create($prefix.'wishlist_shares', function (Blueprint $table) {
            $table->id();
            $table->morphs('shareable');
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->unsignedInteger('max_views')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['shareable_type', 'shareable_id', 'revoked_at'], 'wishlist_shares_owner_status_idx');
        });
    }

    public function down(): void
    {
        $prefix = config('itmosoft-wishlist-sharing.table_prefix', 'itmosoft_');
        Schema::dropIfExists($prefix.'wishlist_shares');
    }
};
