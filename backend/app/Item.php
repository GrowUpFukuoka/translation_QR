<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Translatable;

class Item extends Model
{
    use Translatable;

    public static $rules = [
        'lang' => 'required',
        'item_name' => 'required|max:60',
        'item_key' => 'required|max:60',
        'item_desc' => 'required|min:10',
        'item_price' => 'required|integer',
        'item_order' => 'integer',
        'genre_id' => 'required',
            // アップロードしたファイルのバリデーション設定
        'upfile' => [
            'required',
            'file',
            'image',
            'mimes:jpeg,png',
            'dimensions:min_width=100,min_height=100,max_width=5000,max_height=5000',
        ],
    ];

    public static $update_rules = [
        'lang' => 'required',
        'item_name' => 'required|max:60',
        'item_key' => 'required|max:60',
        'item_desc' => 'required|min:10',
        'item_price' => 'required|integer',
        'item_order' => 'integer',
        'genre_id' => 'required',
    ];

    public static $copy_rules = [
        'lang' => 'required',
        'item_name' => 'required|max:60',
        'item_key' => 'required|max:60',
        'item_desc' => 'required|min:10',
    ];

    protected $fillable = [
        'lang',
        'item_name',
        'item_key',
        'item_desc',
        'item_order',
        'item_price',
        'genre_id',
        'image_path',
        'is_out_of_stock',
    ];

    public function genre(): object
    {
        return $this->belongsTo('App\Genre');
    }
    public function orders(): object
    {
        return $this->hasMany('App\Order');
    }
    public function allergens(): object
    {
        return $this->belongsToMany('App\Allergen', 'allergen_item');
    }
    public function getHashAttribute(): int
    {
        return crc32($this->item_key);
    }

    public static function allForLangAndGenre(string $lang, string $genreKey): object
    {
        $item_query = self::query();
        $item_query->mySelect();
        $item_query->where('lang', 'like', $lang. '%');
        $item_query->whereHas('genre', function($q) use($genreKey) {
            $q->where('genre_key', $genreKey);
        } );
        return $item_query->orderBy('item_order', 'DESC')->orderBy('id', 'DESC')->withAllergens();
    }

    public function scopeMySelect(object $query): object
    {
        return $query->select(['id', 'image_path', 'item_name', 'item_price', 'item_desc', 'is_out_of_stock']);
    }

    public function scopeWithAllergens(object $query): object
    {
        return $query->with('allergens:allergen_name,allergen_key');
    }


    public function allergenIds(): array
    {
        return $this->allergens()->get()->modelKeys();
    }

    public function allergenCopy(array $ids, string $lang): bool
    {
        $allergens = collect($ids)->map(function ($item, $key) use($lang) {
            $allergen = \App\Allergen::find($item);
            $allergen_lang = \App\Allergen::where('allergen_key', $allergen->allergen_key)->where('lang', $lang)->first();
            return $allergen_lang->id;
        });
        return $this->allergenSet($allergens->toArray());
    }

    public function allergenSet(?array $allergens): bool
    {
        if (is_array($allergens)) {
            $allergenIds = $this->allergenIds();
            $add = collect($allergens)->diff($allergenIds);
            $delete = collect($allergenIds)->diff($allergens);

            // dd($add);
            // dump($delete);

            // ひとつでも送られた時
            $this->allergens()->detach($delete);
            $this->allergens()->attach($add);
            return true;
        } else {
            // 送られないとき
            $this->allergens()->detach(); //ユーザの登録済みのアレルゲンを全て削除
            return false;
        }
    }

    public function jp(): object
    {
        if( $this->lang !== 'ja_JP' ) {
            return self::whereItemKey($this->item_key);
        } else {
            return $this;
        }
    }

    public static function whereItemKey(string $item_key): ?object
    {
        $model = self::where('item_key', $item_key)->where('lang', 'ja_JP')->first();
        if (!$model) {
            $model = self::where('item_key', $item_key)->where('lang', 'en_US')->first();
        }
        if (!$model) {
            $model = self::where('item_key', $item_key)->where('lang', 'zh_CN')->first();
        }
        if (!$model) {
            $model = self::where('item_key', $item_key)->where('lang', 'ko_KR')->first();
        }

        return $model;
    }
}
