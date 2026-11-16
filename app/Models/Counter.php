<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** Gap-free sequential numbers (REQ-0001, later quotes and invoices). Call inside a transaction. */
class Counter extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function next(string $key): int
    {
        return DB::transaction(function () use ($key) {
            self::firstOrCreate(['key' => $key], ['value' => 0]);
            $row = self::where('key', $key)->lockForUpdate()->first();
            $row->value++;
            $row->save();

            return $row->value;
        });
    }
}
