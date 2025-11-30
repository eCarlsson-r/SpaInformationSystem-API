<?php

use Illuminate\Http\Request;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\CashAccountController;
use App\Http\Controllers\CompensationController;
use App\Http\Controllers\CompensationPeriodController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\BedController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\TreatmentController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\WalkinController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SalesController;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login', [AuthController::class, 'login']);
Route::post('/subscribe', [AuthController::class, 'subscribe']);
Route::get('/dashboard', [AuthController::class, 'dashboard']);

Route::apiResource('income', IncomeController::class);
Route::apiResource('expense', ExpenseController::class);
Route::apiResource('account', AccountController::class);
Route::apiResource('bankacc', BankAccountController::class);
Route::apiResource('cashacc', CashAccountController::class);
Route::apiResource('agent', AgentController::class);
Route::apiResource('bank', BankController::class);
Route::apiResource('room', RoomController::class);
Route::apiResource('bed', BedController::class);
Route::apiResource('branch', BranchController::class);
Route::apiResource('customer', CustomerController::class);
Route::apiResource('employee', EmployeeController::class);
Route::apiResource('category', CategoryController::class);
Route::apiResource('treatment', TreatmentController::class); 
Route::apiResource('supplier', SupplierController::class); 
Route::apiResource('voucher', VoucherController::class); 
Route::apiResource('walkin', WalkinController::class); 
Route::apiResource('session', SessionController::class);
Route::apiResource('sales', SalesController::class);
Route::apiResource('period', CompensationPeriodController::class);
Route::apiResource('compensation', CompensationController::class);