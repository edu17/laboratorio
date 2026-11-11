<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'created_by', 'revoked_by', 'validation_version', 'token_hash', 'token_encrypted', 'expires_at', 'revoked_at'])]
class ReportAccessToken extends Model
{
    protected $hidden = ['token_hash', 'token_encrypted'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    protected function casts(): array
    {
        return [
            'token_encrypted' => 'encrypted',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
