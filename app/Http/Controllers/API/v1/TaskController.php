<?php

namespace App\Http\Controllers\API\v1;

use App\Enums\StatusTaskEnum;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessGoogleSheetTask;
use App\Models\Task;
use App\Services\ApiClientService;
use App\Services\ExcelService;
use App\Services\GoogleSheetService;
use App\Services\XMLService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TaskController extends Controller
{
    public function __construct(
        protected ExcelService $excelService,
        protected XMLService $xmlService,
        protected GoogleSheetService $googleSheetService,
        protected ApiClientService $apiClientService
    ) {}

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'source' => 'required',
            'result_table_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $source = $request->hasFile('source') ? $request->file('source') : $request->input('source');

        $task = Task::create([
            'source' => $source,
            'result_table_id' => $request->input('result_table_id'),
            'status' => StatusTaskEnum::CREATED->value,
        ]);

        ProcessGoogleSheetTask::dispatch(
            $task,
            $source,
            $this->excelService,
            $this->xmlService,
            $this->googleSheetService,
            $this->apiClientService
        );

        return response()->json(['task_id' => $task->id], 201);
    }

    public function show($task_id): JsonResponse
    {
        $task = Task::find($task_id);

        if (!$task) {
            return response()->json(['error' => 'Task not found'], 404);
        }

        return response()->json(['status' => $task->status], 200);
    }
}
