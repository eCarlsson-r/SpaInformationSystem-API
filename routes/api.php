<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\BedController;
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
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\TreatmentController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\WalkinController;
use App\Http\Controllers\WalletController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login', [AuthController::class, 'login']);
Route::post('/subscribe', [AuthController::class, 'subscribe']);
Route::get('/dashboard', [DashboardController::class, 'dashboard']);
Route::get('/daily', [DashboardController::class, 'daily']);

Route::apiResource('journal', JournalController::class);
Route::apiResource('income', IncomeController::class);
Route::apiResource('expense', ExpenseController::class);
Route::apiResource('transfer', TransferController::class); 
Route::apiResource('attendance', AttendanceController::class); 
Route::apiResource('period', PeriodController::class);
Route::apiResource('compensation', CompensationController::class);
Route::apiResource('account', AccountController::class);
Route::apiResource('wallet', WalletController::class);
Route::apiResource('discount', DiscountController::class);
Route::apiResource('agent', AgentController::class);
Route::apiResource('bank', BankController::class);
Route::apiResource('room', RoomController::class);
Route::apiResource('bed', BedController::class);
Route::apiResource('branch', BranchController::class);
Route::apiResource('customer', CustomerController::class);
Route::apiResource('employee', EmployeeController::class);
Route::apiResource('bonus', BonusController::class); 
Route::apiResource('category', CategoryController::class);
Route::apiResource('treatment', TreatmentController::class); 
Route::apiResource('supplier', SupplierController::class); 
Route::apiResource('voucher', VoucherController::class); 
Route::apiResource('walkin', WalkinController::class); 
Route::apiResource('session', SessionController::class);
Route::apiResource('sales', SalesController::class);
Route::apiResource('banner', BannerController::class);