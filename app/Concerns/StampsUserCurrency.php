<?php

namespace App\Concerns;

use App\Models\User;
use App\Support\Currency;
use Illuminate\Database\Eloquent\Model;

/**
 * Stamps new records with their owner's currency instead of the column
 * default, so a row always records which currency its amount was entered in.
 *
 * Nothing converts between currencies: the value is metadata for exports and
 * audits, while the UI always renders amounts in the owner's current currency.
 */
trait StampsUserCurrency
{
    public static function bootStampsUserCurrency(): void
    {
        static::creating(function (Model $model): void {
            if (! empty($model->getAttribute('currency'))) {
                return;
            }

            $owner = $model->relationLoaded('user')
                ? $model->getRelation('user')
                : User::query()->whereKey($model->getAttribute('user_id'))->first(['id', 'currency']);

            $model->setAttribute('currency', Currency::normalise(
                $owner instanceof User ? $owner->currency : null,
            ));
        });
    }
}
