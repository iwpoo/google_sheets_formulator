<?php

namespace App\Http\Controllers\API\v1;

use App\Enums\StatusTaskEnum;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessGoogleSheetTask;
use App\Models\Task;
use App\Services\ExcelService;
use App\Services\GoogleSheetService;
use App\Services\XMLService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class TaskController extends Controller
{
    public function __construct(
        protected ExcelService $excelService,
        protected XMLService $xmlService,
        protected GoogleSheetService $googleSheetService,
    ) {}

    public function store(Request $request)
    {
        $request->validate([
            'source' => 'required',
            'result_table_id' => 'required',
        ]);

        if ($request->hasFile('source')) {
            $uniqueId = Str::uuid();
            $source = $request->file('source')->storeAs('excel_files', "excel_$uniqueId.xlsx");
        } else {
            $source = $request->input('source');
        }

        $task = Task::create([
            'source' => $source,
            'result_table_id' => $request->input('result_table_id'),
            'status' => StatusTaskEnum::CREATED,
        ]);

        ProcessGoogleSheetTask::dispatch(
            $task,
            $source,
            $this->excelService,
            $this->xmlService,
            $this->googleSheetService,
        );

        return response()->json(array_merge($task->toArray(), ['task_id' => $task->id]), Response::HTTP_CREATED);
    }

    public function show(Task $task): JsonResponse
    {
        return response()->json($task->toArray());
    }
}
