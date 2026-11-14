<?php

namespace ItmoSoft\WishlistSharing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WishlistShare extends Model
{
    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'immutable_datetime',
        'revoked_at' => 'immutable_datetime',
        'metadata' => 'array',
    ];

    public function getTable(): string
    {
        return config('itmosoft-wishlist-sharing.table_prefix', 'itmosoft_').'wishlist_shares';
    }

    public function shareable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeActive($query)
    {
        return $query->whereNull('revoked_at')
            ->where(function ($builder) {
                $builder->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->where(function ($builder) {
                $builder->whereNull('max_views')->orWhereColumn('view_count', '<', 'max_views');
            });
    }
}
