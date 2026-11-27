<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class CodeGeneratorService
{
    /**
     * Generate a collision-free unique business code (e.g. CND-2026-0001, ORB-USR-005).
     *
     * @param  class-string<Model>  $modelClass
     */
    public static function next(string $modelClass, string $column, string $prefix, int $pad = 4): string
    {
        $usesSoftDeletes = in_array(SoftDeletes::class, class_uses_recursive($modelClass), true);
        $baseQuery = fn () => $usesSoftDeletes ? $modelClass::withTrashed() : $modelClass::query();

        $query = $baseQuery();
        if (DB::transactionLevel() > 0) {
            $query->lockForUpdate();
        }

        $maxId = (int) ($query->max('id') ?? 0);

        $latestCodeQuery = $baseQuery()->where($column, 'like', $prefix.'%');
        if (DB::transactionLevel() > 0) {
            $latestCodeQuery->lockForUpdate();
        }

        $latestCodes = $latestCodeQuery
            ->orderByDesc('id')
            ->limit(25)
            ->pluck($column);

        $maxNumericSuffix = 0;
        foreach ($latestCodes as $existingCode) {
            $suffix = substr((string) $existingCode, strlen($prefix));
            if ($suffix !== '' && ctype_digit($suffix)) {
                $maxNumericSuffix = max($maxNumericSuffix, (int) $suffix);
            }
        }

        $sequence = max($maxId, $maxNumericSuffix) + 1;

        do {
            $candidateCode = sprintf('%s%0'.$pad.'d', $prefix, $sequence);
            $exists = $baseQuery()->where($column, $candidateCode)->exists();
            if ($exists) {
                $sequence++;
            }
        } while ($exists);

        return $candidateCode;
    }
}
