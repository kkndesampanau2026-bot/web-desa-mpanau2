<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Tautan sosial media resmi desa — PRD 6.17. */
class SocialMediaLink extends Model
{
    protected $fillable = ['village_id', 'platform', 'url', 'urutan_tampil'];
}
