<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ExcelImport implements ToCollection, WithHeadingRow
{
    /**
     * @param Collection $collection
     * @return array
     */
    public function collection(Collection $collection): array
    {
        $data = [];
        foreach ($collection as $key => $item) {
            $data[$key] = $item->toArray();
        }
        return $data;
    }
}
