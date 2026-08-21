<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Tag berita — PRD 6.10. */
class NewsTag extends Model
{
    use HasFactory;

    protected $fillable = ['village_id', 'nama', 'slug'];

    public function news(): BelongsToMany
    {
        return $this->belongsToMany(News::class, 'news_news_tag');
    }
}
