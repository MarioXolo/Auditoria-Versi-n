<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Registro de quién consulta o exporta información sensible (punto 16). */
class AccessLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'module', 'action', 'detail', 'ip'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $module, string $action, ?string $detail = null): void
    {
        self::create(['user_id' => auth()->id(), 'module' => $module, 'action' => $action, 'detail' => $detail ? mb_substr($detail, 0, 255) : null, 'ip' => request()->ip()]);
    }
}
