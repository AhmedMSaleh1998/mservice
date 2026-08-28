<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Province extends Model
{
    use HasTranslations;

    /**
     * Fixed delivery region codes mirroring the Oracle delivery regions
     * table — keep in sync with it, do not renumber.
     */
    public const DELIVERY_REGIONS = [
        1 => 'داخل القاهرة الكبري',
        2 => 'الاسكندرية والدلتا والقناة',
        3 => 'الصعيد',
        4 => 'مطروح والبحر الاحمر وسفاجا وسيناء',
        5 => 'الوادي الجديد ودهب ونويبع وطابا والقصير مرسي علم - ابو سمبل',
        6 => 'بدون توصيل',
    ];

    protected $table = 'provinces';
    protected $fillable = [
        'code',
        'name',
        'shipping_cost',
        'delivery_region_id',
        'active',
    ];

    protected $casts = [
        'shipping_cost' => 'decimal:2',
        'delivery_region_id' => 'integer',
        'active' => 'boolean',
    ];

    public $translatable = ['name'];

    /**
     * "id - name" pairs for select fields.
     */
    public static function deliveryRegionOptions(): array
    {
        $options = [];

        foreach (self::DELIVERY_REGIONS as $id => $name) {
            $options[$id] = $id . ' - ' . $name;
        }

        return $options;
    }
}
