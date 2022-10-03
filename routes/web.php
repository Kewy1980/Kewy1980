<?php


use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Models\User;
use App\Http\Controllers\ToolsController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/


Auth::routes(['register' => false]);

Route::get('/home', [HomeController::class, 'index'])->name('home');
Route::get('/remove-dataset', [HomeController::class, 'removeDataset'])->name('remove-dataset');
Route::get('/remove-dataset/confirm', [HomeController::class, 'removeDatasetConfirm'])->name('remove-dataset-confirm');
Route::post('/remove-dataset/confirmed', [HomeController::class, 'removeDatasetConfirmed'])->name('remove-dataset-confirmed');
Route::get('/queues', [HomeController::class, 'queues'])->name('queues');
Route::get('/delete-actions', [HomeController::class, 'deleteActions'])->name('delete-actions');

Route::get('/importers', [HomeController::class, 'importers'])->name('importers');
Route::get('/importer/{id}/imports', [HomeController::class, 'importerImports'])->name('importer-imports');
Route::get('/importer/{importer_id}/imports/{import_id}/flow', [HomeController::class, 'importerImportsFlow'])->name('importer-imports-flow');
Route::get('/importer/{importer_id}/imports/{import_id}/log', [HomeController::class, 'importerImportsLog'])->name('importer-imports-log');
Route::get('/importer/{importer_id}/imports/{import_id}/log-export', [HomeController::class, 'exportImportLog'])->name('importer-imports-log-export');
Route::get('/importer/{importer_id}/imports/{import_id}/detail/{source_dataset_identifier_id}', [HomeController::class, 'importerImportsDetail'])->name('importer-imports-detail');

Route::get('tools/convert-keywords', [ToolsController::class, 'convertKeywords'])->name('convert-keywords');
Route::post('tools/convert-keywords', [ToolsController::class, 'processMaterialsFile'])->name('process-materials-file');
Route::post('tools/convert-porefluids', [ToolsController::class, 'processPorefluidsFile'])->name('process-porefluids-file');
Route::post('tools/convert-rockphysics', [ToolsController::class, 'processRockPhysicsFile'])->name('process-rockphysics-file');
Route::post('tools/convert-analogue', [ToolsController::class, 'processAnalogueModellingFile'])->name('process-analogue-file');
Route::post('tools/convert-geological-age', [ToolsController::class, 'processGeologicalAgeFile'])->name('process-geological-age-file');
Route::post('tools/convert-geological-setting', [ToolsController::class, 'processGeologicalSettingFile'])->name('process-geological-setting-file');
Route::post('tools/convert-paleomagnetism', [ToolsController::class, 'processPaleomagnetismFile'])->name('process-paleomagnetism-file');
Route::post('tools/convert-geochemistry', [ToolsController::class, 'processGeochemistryFile'])->name('process-geochemistry-file');
Route::get('tools/convert-excel', [ToolsController::class, 'convertExcel'])->name('convert-excel');
Route::post('tools/convert-excel', [ToolsController::class, 'processExcelToJson'])->name('process-excel-file');
Route::get('tools/filtertree', [ToolsController::class, 'filterTree'])->name('filter-tree');
Route::get('tools/filtertreedownload', [ToolsController::class, 'filterTreeDownload'])->name('filter-tree-download');
Route::get('tools/unmatchedkeywords', [ToolsController::class, 'viewUnmatchedKeywords'])->name('view-unmatched-keywords');
Route::get('tools/unmatchedkeywordsdownload', [ToolsController::class, 'downloadUnmatchedKeywords'])->name('download-unmatched-keywords');
Route::get('tools/abstract-matching', [ToolsController::class, 'abstractMatching'])->name('abstract-matching');
Route::get('tools/abstract-matching-download/{data_repo}', [ToolsController::class, 'abstractMatchingDownload'])->name('abstract-matching-download');
Route::get('tools/view-stats', [ToolsController::class, 'viewStats'])->name('view-stats');

Route::post('/create-import', [HomeController::class, 'createImport'])->name('create-import');
Route::get('/imports', [HomeController::class, 'imports'])->name('imports');
Route::get('/source-dataset-identifiers', [HomeController::class, 'sourceDatasetIdentifiers'])->name('source-dataset-identifiers');
Route::get('/source-datasets', [HomeController::class, 'sourceDatasets'])->name('source-datasets');
Route::get('/source-datasets/{id}', [HomeController::class, 'sourceDataset'])->name('source-dataset');
Route::get('/create-actions', [HomeController::class, 'createActions'])->name('create-actions');
Route::get('/create-action/{id}', [HomeController::class, 'createAction'])->name('create-action');

Route::get('/test', [HomeController::class, 'test'])->name('test');