<?php

namespace App\Models;

use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use HasFactory;

    protected $fillable = ['domain', 'timezone'];

    protected static function booted(): void
    {
        static::creating(function (Site $site): void {
            if (empty($site->public_key)) {
                $site->public_key = 'pk_'.Str::random();
            }
        });
    }
}
