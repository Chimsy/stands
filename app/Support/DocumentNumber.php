<?php

namespace App\Support;

use App\Models\Branch;
use Illuminate\Support\Facades\DB;

/**
 * Allocates the human-facing document numbers - sale agreements, receipts and
 * journal references - that staff and buyers quote.
 *
 * Numbers are sequential per branch and per document type, which is what makes
 * a receipt book auditable. The suffix is zero padded to a fixed width so the
 * lexicographic maximum is also the numeric maximum.
 *
 * Call inside the same transaction as the insert. The unique index on each
 * number column is the real guard: if two requests race, the loser fails on the
 * index rather than silently reusing a number.
 */
final class DocumentNumber
{
    private const WIDTH = 6;

    public static function next(string $prefix, Branch $branch, string $table, string $column): string
    {
        $scope = sprintf('%s-%s-', $prefix, $branch->code);

        $latest = DB::table($table)
            ->where($column, 'like', $scope.'%')
            ->max($column);

        $sequence = $latest === null
            ? 1
            : ((int) substr((string) $latest, strlen($scope))) + 1;

        return $scope.str_pad((string) $sequence, self::WIDTH, '0', STR_PAD_LEFT);
    }
}
