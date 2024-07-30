<?php

namespace App\Jobs;

use App\Enums\StatusTaskEnum;
use App\Models\Task;
use App\Services\ExcelService;
use App\Services\GoogleSheetService;
use App\Services\XMLService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessGoogleSheetTask implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public int $timeout = 120;

    /**
     * Indicate if the job should be marked as failed on timeout.
     *
     * @var bool
     */
    public bool $failOnTimeout = TRUE;

    public function __construct(protected Task $task, protected string $source, protected ExcelService $excelService, protected XMLService $xmlService, protected GoogleSheetService $googleSheetService)
    {
    }

    /**
     * @return void
     * @throws \Throwable
     */
    public function handle(): void
    {
        ini_set('memory_limit', '-1');

        $this->task->update([
            'status' => StatusTaskEnum::IN_PROGRESS,
            'message' => 'Заполнение таблицы...',
        ]);

        try {
            if ($this->excelService->checkForXlsxFormat($this->source)) {
                $this->processExcel();
            } elseif ($this->xmlService->checkForXmlFormat($this->source)) {
                $this->processXml();
            } else {
                throw new \RuntimeException('Неподдерживаемый формат файла.');
            }

            $this->task->update([
                'status' => StatusTaskEnum::SUCCESS,
                'message' => 'Таблица заполнена успешно',
            ]);
        } catch (\Throwable $e) {
            Log::error('Ошибка при вставке данных в таблицу: ' . $e->getMessage(), ['e' => $e]);
            $this->task->update([
                'status' => StatusTaskEnum::FAILED,
                'message' => 'Ошибка при вставке данных в таблицу: ' . $e->getMessage(),
            ]);

            $this->fail($e);
        }
    }

    /**
     * @return void
     * @throws \Throwable
     */
    protected function processExcel(): void
    {
        $filePath = storage_path('app/' . $this->source);
        $data = $this->excelService->getData($filePath);

        if (!empty($data)) {
            $lists = $this->excelService->fetchSheetLinkExcel($data);
            foreach ($lists as $list) {
                if (is_null($list)) {
                    continue;
                }
                $dataOfCategory = $this->excelService->getDataOfCategory($list['tree_path'], $data);
                $this->googleSheetService->insertData($this->task->result_table_id, $list['google_sheet_id'], $list['sheet_name'], $dataOfCategory);
            }
        } else {
            throw new \RuntimeException('Не найдены объявления');
        }
        Storage::delete('app/excel_files/' . $this->source);
    }

    /**
     * @return void
     * @throws \Throwable
     */
    protected function processXml(): void
    {
        Log::debug('[ProcessGoogleSheetTask -> processXml] Начало получения данных из XML');
        $data = $this->xmlService->getData($this->source);

        if (!empty($data)) {
            Log::debug('[ProcessGoogleSheetTask -> processXml] Начало получения шаблона.', ['items_count' => count($data)]);
            $lists = $this->xmlService->fetchSheetLinkXML($data) ?? [];

            foreach ($lists as $list) {
                if (is_null($list)) {
                    continue;
                }

                Log::debug("[ProcessGoogleSheetTask -> processXml] Начало работы с листом {$list['sheet_name']}");
                $dataOfCategory = $this->xmlService->getDataOfCategory($list['tree_path'], $data);
                $this->googleSheetService->insertData($this->task->result_table_id, $list['google_sheet_id'], $list['sheet_name'], $dataOfCategory);
            }
        } else {
            throw new \RuntimeException('Не найдены объявления');
        }
    }

    /**
     * Fail the job from the queue.
     *
     * @param \Throwable|null $exception
     * @return void
     */
    public function failed(?\Throwable $exception): void
    {
        if ($exception instanceof \Throwable) {
            $error = $exception->getMessage();
        } else {
            $error = 'Неизвестная ошибка';
        }

        $this->task->update([
            'status' => StatusTaskEnum::FAILED,
            'message' => 'Ошибка при выполнении задачи',
        ]);

        $this->task->status = StatusTaskEnum::FAILED;
        $this->task->message = "Ошибка при вставке данных в таблицу: $error";
    }
}
