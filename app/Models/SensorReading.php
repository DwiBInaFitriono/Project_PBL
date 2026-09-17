<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\SensorReadingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class SensorReading extends Model
{
    /** @use HasFactory<SensorReadingFactory> */
    use HasFactory;

    protected $fillable = ['node_id', 'sensor_id', 'value', 'recorded_at'];

    /**
     * SQLite accepts text and infinities in numeric columns; exclude them before casting.
     *
     * @param  Builder<SensorReading>  $query
     * @return Builder<SensorReading>
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->whereRaw("typeof(value) IN ('integer', 'real')")
            ->whereBetween('value', [-PHP_FLOAT_MAX, PHP_FLOAT_MAX])
            ->whereIn('node_id', array_column(config('monitoring.nodes'), 'id'))
            ->whereIn('sensor_id', array_column(config('monitoring.sensors'), 'id'));
    }

    public function setValueAttribute(mixed $value): void
    {
        if (! is_numeric($value) || ! is_finite((float) $value)) {
            throw new InvalidArgumentException('Nilai pembacaan harus berupa angka berhingga.');
        }

        $this->attributes['value'] = (float) $value;
    }

    public function setRecordedAtAttribute(mixed $value): void
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            throw new InvalidArgumentException('Waktu pembacaan wajib diisi.');
        }

        $this->attributes['recorded_at'] = CarbonImmutable::parse($value, 'UTC')->utc()->format($this->getDateFormat());
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['node_id' => 'string', 'value' => 'float', 'recorded_at' => 'immutable_datetime'];
    }
}
