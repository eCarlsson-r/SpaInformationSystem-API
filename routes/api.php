<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\BonusController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CompensationController;
use App\Http\Controllers\PeriodController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscountController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExpenseItemController;
use App\Http\Controllers\ExpensePaymentController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\IncomeItemController;
use App\Http\Controllers\IncomePaymentController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\TreatmentController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\WalkinController;
use App\Http\Controllers\WalletController;

Route::get('/user', function (Request $request) {
    return $request->user()->load('employee');
})->middleware('auth:sanctum');

Route::post('/login', [AuthController::class, 'login']);
Route::post('/subscribe', [AuthController::class, 'subscribe']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'dashboard']);
    Route::get('/daily', [DashboardController::class, 'daily']);
    Route::get('account/lookup', [AccountController::class, 'lookup']);
    Route::post('/files', [AuthController::class, 'upload']);
    Route::post('/attendance/sync', [AttendanceController::class, 'sync']);
    Route::get('/attendance/sync-status', [AttendanceController::class, 'getSyncStatus']);

    Route::apiResource('session', SessionController::class);
    Route::post('/session/{session}/start', [SessionController::class, 'start']);
    Route::post('/session/{session}/finish', [SessionController::class, 'finish']);

    Route::apiResource('journal', JournalController::class);
    Route::apiResource('income', IncomeController::class);
    Route::apiResource('incomeitem', IncomeItemController::class);
    Route::apiResource('incomepayment', IncomePaymentController::class);
    Route::apiResource('expense', ExpenseController::class);
    Route::apiResource('expenseitem', ExpenseItemController::class);
    Route::apiResource('expensepayment', ExpensePaymentController::class);
    Route::apiResource('transfer', TransferController::class); 
    Route::apiResource('attendance', AttendanceController::class); 
    Route::apiResource('period', PeriodController::class);
    Route::apiResource('shift', ShiftController::class);
    Route::apiResource('compensation', CompensationController::class);
    Route::apiResource('account', AccountController::class);
    Route::apiResource('wallet', WalletController::class);
    Route::apiResource('discount', DiscountController::class);
    Route::apiResource('agent', AgentController::class);
    Route::apiResource('bank', BankController::class);
    Route::apiResource('room', RoomController::class);
    Route::apiResource('branch', BranchController::class);
    Route::apiResource('customer', CustomerController::class);
    Route::apiResource('employee', EmployeeController::class);
    Route::apiResource('bonus', BonusController::class); 
    Route::apiResource('category', CategoryController::class);
    Route::apiResource('treatment', TreatmentController::class); 
    Route::apiResource('supplier', SupplierController::class); 
    Route::apiResource('voucher', VoucherController::class); 
    Route::apiResource('walkin', WalkinController::class); 
    Route::apiResource('sales', SalesController::class);
    Route::apiResource('banner', BannerController::class);
});