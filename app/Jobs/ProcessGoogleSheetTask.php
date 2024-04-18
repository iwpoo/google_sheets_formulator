<?php

namespace App\Jobs;

use App\Enums\StatusTaskEnum;
use App\Models\Task;
use App\Services\ApiClientService;
use App\Services\ExcelService;
use App\Services\GoogleSheetService;
use App\Services\XMLService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ProcessGoogleSheetTask implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected Task $task,
        protected string|UploadedFile $source,
        protected ExcelService $excelService,
        protected XMLService $xmlService,
        protected GoogleSheetService $googleSheetService,
        protected ApiClientService $apiClientService
    ) {}

    public function handle(): void
    {
        $this->task->status = StatusTaskEnum::IN_PROGRESS->value;
        $this->task->save();

        $result = NULL;

        if ($this->excelService->checkForXlsxFormat($this->source)) {
            $this->source->store('excel_files');

            $filePath = storage_path('app/excel_files/' . $this->source);
            $lists = $this->apiClientService->getDataAboutList($filePath);
            foreach ($lists as $list) {
                $data = $this->excelService->getDataOfCategory($filePath, end($list['tree_path']));
                $result = $this->googleSheetService->insertData($this->task->result_table_id, $list['sheet_name'], $data);
            }
        } else if ($this->xmlService->checkForXmlFormat($this->source)) {
            $lists = $this->apiClientService->getDataAboutList($this->source);
            foreach ($lists as $list) {
                $data = $this->xmlService->getDataOfCategory($this->source, end($list['tree_path']));
                $result = $this->googleSheetService->insertData($this->task->result_table_id, $list['sheet_name'], $data);
            }
        }

        if ($result) {
            $this->task->status = StatusTaskEnum::SUCCESS;
        } else {
            $this->task->status = StatusTaskEnum::FAILED;
        }

        $this->task->save();
    }
}
